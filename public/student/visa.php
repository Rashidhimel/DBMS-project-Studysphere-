<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Visa Status";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$success = '';
$error = '';

$visas = $db->fetchAll(
    "SELECT sv.*, vt.name as visa_type_name, vt.country_code, 
            c.name as country_name, c.flag_url, p.program_name, u.name as university_name,
            e.name as embassy_name
     FROM student_visas sv
     JOIN visa_types vt ON sv.visa_type_id = vt.visa_type_id
     JOIN countries c ON vt.country_code = c.iso2
     LEFT JOIN programs p ON sv.program_id = p.program_id
     LEFT JOIN universities u ON p.university_id = u.university_id
     LEFT JOIN embassy_consulate_records e ON sv.embassy_id = e.embassy_id
     WHERE sv.student_id = ?
     ORDER BY sv.created_at DESC",
    [$student_id]
);

$visa_types = $db->fetchAll(
    "SELECT vt.*, c.name as country_name, c.flag_url
     FROM visa_types vt
     JOIN countries c ON vt.country_code = c.iso2
     ORDER BY c.name, vt.name"
);

$programs = $db->fetchAll("
    SELECT p.program_id, p.program_name, u.name as university_name, u.country_code
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON p.university_id = u.university_id
    WHERE a.student_id = ? AND a.deleted_at IS NULL
    GROUP BY p.program_id
    ORDER BY p.program_name
", [$student_id]);

if (empty($programs)) {
    $programs = $db->fetchAll("
        SELECT p.program_id, p.program_name, u.name as university_name, u.country_code
        FROM programs p
        JOIN universities u ON p.university_id = u.university_id
        WHERE p.degree_level_id = (SELECT degree_level_id FROM students WHERE student_id = ?)
        LIMIT 10
    ", [$student_id]);
}

// Compute stats
$total_visas = count($visas);
$approved = count(array_filter($visas, fn($v) => $v['status'] == 'approved'));
$pending = count(array_filter($visas, fn($v) => in_array($v['status'], ['applied','processing'])));
$rejected = count(array_filter($visas, fn($v) => $v['status'] == 'rejected'));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_visa'])) {
        $visa_type_id = intval($_POST['visa_type_id']);
        $program_id = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $application_date = Helpers::sanitize($_POST['application_date']);
        $status = Helpers::sanitize($_POST['status']);
        $notes = Helpers::sanitize($_POST['notes']);
        
        $db->insert('student_visas', [
            'student_id' => $student_id,
            'visa_type_id' => $visa_type_id,
            'program_id' => $program_id,
            'application_date' => $application_date,
            'status' => $status,
            'notes' => $notes,
            'created_by' => $user['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $visa_id = $db->getConnection()->lastInsertId();
        
        $db->insert('visa_status_history', [
            'visa_id' => $visa_id,
            'new_status' => $status,
            'changed_by' => $user['user_id'],
            'changed_at' => date('Y-m-d H:i:s'),
            'note' => 'Visa application created'
        ]);
        
        Helpers::redirect('visa.php', 'Visa application added successfully!');
    }
    
    if (isset($_FILES['visa_document']) && $_FILES['visa_document']['error'] == 0) {
        $visa_id = intval($_POST['visa_id']);
        $document_type = Helpers::sanitize($_POST['document_type']);
        
        $visa = $db->fetchOne(
            "SELECT visa_id FROM student_visas WHERE visa_id = ? AND student_id = ?",
            [$visa_id, $student_id]
        );
        
        if ($visa) {
            $result = $upload_helper->uploadFile($_FILES['visa_document'], $user['user_id'], 'visa');
            
            if (isset($result['success']) && $result['success']) {
                try {
                    $db->insert('visa_documents', [
                        'visa_id' => $visa_id,
                        'attachment_id' => $result['attachment_id'],
                        'document_type' => $document_type,
                        'uploaded_by' => $user['user_id']
                    ]);
                    
                    Helpers::redirect('visa.php', 'Visa document uploaded successfully!');
                } catch (PDOException $e) {
                    $error = 'Failed to link document. Please try again.';
                }
            } else {
                $error = $result['error'] ?? 'Upload failed';
            }
        }
    }
    
    if (isset($_POST['update_status'])) {
        $visa_id = intval($_POST['visa_id']);
        $new_status = Helpers::sanitize($_POST['new_status']);
        $status_note = Helpers::sanitize($_POST['status_note']);
        $approval_date = !empty($_POST['approval_date']) ? $_POST['approval_date'] : null;
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        $current = $db->fetchOne(
            "SELECT status FROM student_visas WHERE visa_id = ? AND student_id = ?",
            [$visa_id, $student_id]
        );
        
        if ($current) {
            $update_data = [
                'status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $user['user_id']
            ];
            
            if ($approval_date) {
                $update_data['approval_date'] = $approval_date;
            }
            if ($expiry_date) {
                $update_data['expiry_date'] = $expiry_date;
            }
            
            $db->update('student_visas', $update_data, "visa_id = $visa_id");
            
            $db->insert('visa_status_history', [
                'visa_id' => $visa_id,
                'old_status' => $current['status'],
                'new_status' => $new_status,
                'changed_by' => $user['user_id'],
                'changed_at' => date('Y-m-d H:i:s'),
                'note' => $status_note
            ]);
            
            Helpers::redirect('visa.php', 'Visa status updated successfully!');
        }
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-passport"></i> Visa Status Tracker</h1>
    <p>Track your visa applications, upload documents, and stay organized</p>
</div>

<?php
$flash = Helpers::getFlash();
if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $flash['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid modern" style="margin-bottom: 2rem;">
    <div class="stat-item">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-passport"></i>
        </div>
        <div class="stat-content">
            <h4><?php echo $total_visas; ?></h4>
            <p>Total Applications</p>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h4><?php echo $approved; ?></h4>
            <p>Approved</p>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h4><?php echo $pending; ?></h4>
            <p>Pending</p>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h4><?php echo $rejected; ?></h4>
            <p>Rejected</p>
        </div>
    </div>
</div>

<!-- Add New Visa Card -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Visa Application</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" class="modern-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="visa_type_id">Visa Type *</label>
                    <select id="visa_type_id" name="visa_type_id" required class="form-control">
                        <option value="">Select Visa Type</option>
                        <?php foreach ($visa_types as $type): ?>
                            <option value="<?php echo $type['visa_type_id']; ?>">
                                <?php echo htmlspecialchars($type['country_name']); ?> - <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="program_id">For Program <small>(optional)</small></label>
                    <select id="program_id" name="program_id" class="form-control">
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['program_id']; ?>">
                                <?php echo htmlspecialchars($program['program_name']); ?> - <?php echo htmlspecialchars($program['university_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($programs)): ?>
                        <small class="text-muted">No programs found. You can still apply for a visa without selecting a program.</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="application_date">Application Date</label>
                    <input type="date" id="application_date" name="application_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Current Status *</label>
                    <select id="status" name="status" required class="form-control">
                        <option value="not_started">Not Started</option>
                        <option value="applied" selected>Applied</option>
                        <option value="processing">Processing</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="expired">Expired</option>
                        <option value="withdrawn">Withdrawn</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Any additional notes about this visa application..."></textarea>
            </div>
            
            <button type="submit" name="add_visa" class="btn btn-primary">
                <i class="fas fa-save"></i> Add Visa Application
            </button>
        </form>
    </div>
</div>

<!-- Visa Applications List -->
<?php if (empty($visas)): ?>
    <div class="empty-state">
        <i class="fas fa-passport fa-4x"></i>
        <h4>No visa applications yet</h4>
        <p>Add your first visa application using the form above.</p>
    </div>
<?php else: ?>
    <div class="visa-timeline">
        <?php foreach ($visas as $visa): 
            $status_color = [
                'approved' => '#27ae60',
                'rejected' => '#e74c3c',
                'processing' => '#3498db',
                'applied' => '#f39c12',
                'not_started' => '#95a5a6',
                'expired' => '#7f8c8d',
                'withdrawn' => '#7f8c8d'
            ][$visa['status']] ?? '#95a5a6';
            
            // Fetch documents for this visa
            $documents = $db->fetchAll(
                "SELECT vd.*, a.filename, a.file_url, a.file_type, a.file_size, a.created_at as uploaded_at
                 FROM visa_documents vd
                 JOIN attachments a ON vd.attachment_id = a.attachment_id
                 WHERE vd.visa_id = ? AND a.deleted_at IS NULL
                 ORDER BY a.created_at DESC",
                [$visa['visa_id']]
            );
            
            // Fetch status history
            $history = $db->fetchAll(
                "SELECT h.*, u.username as changed_by_name 
                 FROM visa_status_history h
                 LEFT JOIN users u ON h.changed_by = u.user_id
                 WHERE visa_id = ? 
                 ORDER BY changed_at DESC",
                [$visa['visa_id']]
            );
        ?>
        <div class="visa-card" style="border-left-color: <?php echo $status_color; ?>;">
            <div class="visa-card-header">
                <div class="visa-title">
                    <h4>
                        <?php echo htmlspecialchars($visa['visa_type_name']); ?>
                        <span class="country-badge">
                            <i class="fas fa-globe"></i> <?php echo htmlspecialchars($visa['country_name']); ?>
                        </span>
                    </h4>
                    <?php if ($visa['program_name']): ?>
                        <p class="program-info">
                            <i class="fas fa-graduation-cap"></i> 
                            <?php echo htmlspecialchars($visa['program_name']); ?> - <?php echo htmlspecialchars($visa['university_name']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="visa-status">
                    <span class="status-badge" style="background: <?php echo $status_color; ?>; color: white;">
                        <?php echo ucfirst($visa['status']); ?>
                    </span>
                    <button class="btn-icon" onclick="toggleSection('update-<?php echo $visa['visa_id']; ?>')" data-tooltip="Update Status">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon" onclick="toggleSection('upload-<?php echo $visa['visa_id']; ?>')" data-tooltip="Upload Document">
                        <i class="fas fa-upload"></i>
                    </button>
                </div>
            </div>
            
            <div class="visa-card-body">
                <div class="visa-dates">
                    <?php if ($visa['application_date']): ?>
                        <span><i class="far fa-calendar-alt"></i> Applied: <?php echo Helpers::formatDate($visa['application_date']); ?></span>
                    <?php endif; ?>
                    <?php if ($visa['approval_date']): ?>
                        <span><i class="fas fa-check-circle"></i> Approved: <?php echo Helpers::formatDate($visa['approval_date']); ?></span>
                    <?php endif; ?>
                    <?php if ($visa['expiry_date']): ?>
                        <span><i class="fas fa-hourglass-end"></i> Expires: <?php echo Helpers::formatDate($visa['expiry_date']); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($visa['notes']): ?>
                    <div class="visa-notes">
                        <i class="fas fa-sticky-note"></i> <?php echo nl2br(htmlspecialchars($visa['notes'])); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Collapsible Sections -->
                <div id="update-<?php echo $visa['visa_id']; ?>" class="collapse-section">
                    <h5>Update Status</h5>
                    <form method="POST" action="" class="inline-form">
                        <input type="hidden" name="visa_id" value="<?php echo $visa['visa_id']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <select name="new_status" required class="form-control">
                                    <option value="not_started" <?php echo $visa['status'] == 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                    <option value="applied" <?php echo $visa['status'] == 'applied' ? 'selected' : ''; ?>>Applied</option>
                                    <option value="processing" <?php echo $visa['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="approved" <?php echo $visa['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $visa['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="expired" <?php echo $visa['status'] == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="withdrawn" <?php echo $visa['status'] == 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <input type="date" name="approval_date" class="form-control" placeholder="Approval date" value="<?php echo $visa['approval_date'] ?? ''; ?>">
                            </div>
                            <div class="form-group">
                                <input type="date" name="expiry_date" class="form-control" placeholder="Expiry date" value="<?php echo $visa['expiry_date'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <textarea name="status_note" rows="2" class="form-control" placeholder="Reason for change (optional)"></textarea>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSection('update-<?php echo $visa['visa_id']; ?>')">Cancel</button>
                    </form>
                </div>
                
                <div id="upload-<?php echo $visa['visa_id']; ?>" class="collapse-section">
                    <h5>Upload Document</h5>
                    <form method="POST" enctype="multipart/form-data" class="inline-form">
                        <input type="hidden" name="visa_id" value="<?php echo $visa['visa_id']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" name="document_type" class="form-control" placeholder="Document type (e.g., Passport)" required>
                            </div>
                            <div class="form-group">
                                <input type="file" name="visa_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSection('upload-<?php echo $visa['visa_id']; ?>')">Cancel</button>
                    </form>
                </div>
                
                <!-- Documents List -->
                <?php if (!empty($documents)): ?>
                    <div class="visa-documents">
                        <h5><i class="fas fa-paperclip"></i> Documents (<?php echo count($documents); ?>)</h5>
                        <ul>
                            <?php foreach ($documents as $doc): ?>
                                <li>
                                    <i class="fas fa-file-<?php echo strpos($doc['file_type'],'pdf')!==false?'pdf':(strpos($doc['file_type'],'image')!==false?'image':'file'); ?>"></i>
                                    <strong><?php echo htmlspecialchars($doc['document_type']); ?>:</strong>
                                    <a href="<?php echo $doc['file_url']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($doc['filename']); ?>
                                    </a>
                                    <small>(<?php echo round($doc['file_size']/1024,1); ?> KB, uploaded <?php echo Helpers::formatDate($doc['uploaded_at'], 'M j, Y'); ?>)</small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Status History -->
                <?php if (!empty($history)): ?>
                    <div class="visa-history">
                        <h5><i class="fas fa-history"></i> Status History</h5>
                        <ul>
                            <?php foreach ($history as $hist): ?>
                                <li>
                                    <?php if ($hist['old_status']): ?>
                                        <span class="badge" style="background:#e74c3c;"><?php echo ucfirst($hist['old_status']); ?></span>
                                        → <span class="badge" style="background:#27ae60;"><?php echo ucfirst($hist['new_status']); ?></span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#27ae60;"><?php echo ucfirst($hist['new_status']); ?></span>
                                    <?php endif; ?>
                                    on <?php echo Helpers::formatDate($hist['changed_at'], 'M j, Y g:i A'); ?>
                                    <?php if ($hist['note']): ?>
                                        <br><small>Note: <?php echo htmlspecialchars($hist['note']); ?></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Tips Card -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-lightbulb"></i> Visa Application Tips</h3>
    </div>
    <div class="card-body">
        <ul class="tips-list">
            <li><i class="fas fa-check-circle"></i> Start your visa application as soon as you receive your acceptance letter</li>
            <li><i class="fas fa-check-circle"></i> Check the specific requirements for your destination country</li>
            <li><i class="fas fa-check-circle"></i> Keep all documents organized and readily accessible</li>
            <li><i class="fas fa-check-circle"></i> Upload copies of all submitted documents for reference</li>
            <li><i class="fas fa-check-circle"></i> Track important dates (application submission, interview, decision)</li>
            <li><i class="fas fa-check-circle"></i> Set reminders for expiry dates if your visa is time-limited</li>
        </ul>
    </div>
</div>

<style>
/* Modern Stats Grid */
.stats-grid.modern {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.stat-item {
    background: white;
    border-radius: 16px;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}
.stat-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}
.stat-content h4 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Visa Timeline */
.visa-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.visa-card {
    background: white;
    border-radius: 18px;
    border-left: 6px solid;
    box-shadow: 0 6px 18px rgba(0,0,0,0.04);
    overflow: hidden;
    transition: all 0.3s ease;
}
.visa-card:hover {
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
}
.visa-card-header {
    padding: 1.5rem 1.8rem 0.8rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
}
.visa-title h4 {
    margin: 0 0 0.3rem 0;
    font-size: 1.2rem;
    font-weight: 600;
}
.country-badge {
    background: #eef2f6;
    color: #2c3e50;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 0.5rem;
}
.program-info {
    margin: 0;
    color: #5d6d7e;
    font-size: 0.9rem;
}
.visa-status {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.status-badge {
    padding: 0.3rem 1rem;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid #e0e7ef;
    background: white;
    color: #4a5568;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-icon:hover {
    background: #f8fafc;
    transform: translateY(-2px);
    border-color: #cbd5e0;
}
.visa-card-body {
    padding: 0 1.8rem 1.5rem;
}
.visa-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem;
    font-size: 0.9rem;
    color: #4a5568;
    margin-bottom: 1rem;
}
.visa-dates i {
    margin-right: 0.3rem;
    color: #667eea;
}
.visa-notes {
    background: #f8fafc;
    padding: 0.8rem 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    border-left: 3px solid #667eea;
}
.collapse-section {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.2rem;
    margin: 1rem 0;
    display: none;
}
.collapse-section h5 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: #2d3748;
}
.inline-form {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
.visa-documents, .visa-history {
    margin-top: 1.2rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 1rem;
}
.visa-documents h5, .visa-history h5 {
    font-size: 0.95rem;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.8rem;
}
.visa-documents ul, .visa-history ul {
    margin: 0;
    padding-left: 1.2rem;
}
.visa-documents li, .visa-history li {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #4a5568;
}
.visa-documents a {
    color: #3182ce;
    text-decoration: none;
}
.visa-documents a:hover {
    text-decoration: underline;
}
.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    margin-right: 0.3rem;
}
.tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.8rem;
}
.tips-list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #2d3748;
    font-size: 0.9rem;
}
.tips-list i {
    color: #48bb78;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}
.empty-state h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
    font-size: 1.3rem;
}
.empty-state p {
    color: #718096;
}

/* Utility */
.mb-4 { margin-bottom: 2rem; }
.mt-4 { margin-top: 2rem; }
.btn-sm { padding: 0.3rem 1rem; font-size: 0.85rem; }

/* Responsive */
@media (max-width: 768px) {
    .stats-grid.modern {
        grid-template-columns: repeat(2, 1fr);
    }
    .visa-card-header {
        flex-direction: column;
    }
    .visa-status {
        align-self: flex-start;
    }
}
</style>

<script>
function toggleSection(id) {
    var section = document.getElementById(id);
    if (section.style.display === 'none' || section.style.display === '') {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
    }
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>