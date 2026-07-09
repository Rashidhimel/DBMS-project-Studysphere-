<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Education History";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$success = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

$education_records = $db->fetchAll(
    "SELECT er.*, c.name as country_name 
     FROM education_records er 
     LEFT JOIN countries c ON er.country_code = c.iso2 
     WHERE er.student_id = ? AND er.deleted_at IS NULL 
     ORDER BY er.end_date DESC",
    [$student_id]
);

$countries = Helpers::getCountries();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_education'])) {
        $institution = Helpers::sanitize($_POST['institution_name']);
        $country = Helpers::sanitize($_POST['country_code']);
        $degree = Helpers::sanitize($_POST['degree_name']);
        $major = Helpers::sanitize($_POST['major'] ?? ''); // new optional field
        $level = Helpers::sanitize($_POST['level']);
        $gpa = Helpers::sanitize($_POST['gpa']);
        $start_date = Helpers::sanitize($_POST['start_date']);
        $end_date = Helpers::sanitize($_POST['end_date']);
        
        $db->insert('education_records', [
            'student_id' => $student_id,
            'level' => $level,
            'institution_name' => $institution,
            'country_code' => $country,
            'degree_name' => $degree,
            'major' => $major, // store major
            'gpa' => $gpa,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        Helpers::redirect('education.php', 'Education record added successfully!');
    }
    
    if (isset($_POST['delete_record'])) {
        $record_id = intval($_POST['record_id']);
        
        $db->update('education_records', 
            ['deleted_at' => date('Y-m-d H:i:s')],
            "record_id = $record_id AND student_id = $student_id"
        );
        
        Helpers::redirect('education.php', 'Education record deleted successfully!');
    }
    
    if (isset($_FILES['transcript']) && $_FILES['transcript']['error'] == 0) {
        $record_id = intval($_POST['record_id']);
        
        $record = $db->fetchOne(
            "SELECT record_id FROM education_records WHERE record_id = ? AND student_id = ?",
            [$record_id, $student_id]
        );
        
        if ($record) {
            $result = $upload_helper->uploadFile($_FILES['transcript'], $user['user_id'], 'transcript');
            
            if (isset($result['success']) && $result['success']) {
                $link_result = $upload_helper->linkToEducation($result['attachment_id'], $record_id);
                
                if (isset($link_result['success']) && $link_result['success']) {
                    Helpers::redirect('education.php', 'Transcript uploaded successfully!');
                } else {
                    $error = $link_result['error'] ?? 'Failed to link transcript to education record';
                }
            } else {
                $error = $result['error'] ?? 'Upload failed';
            }
        } else {
            $error = 'Invalid education record.';
        }
    }
}

$flash = Helpers::getFlash();
?>

<div class="page-header">
    <h1><i class="fas fa-graduation-cap"></i> Education History</h1>
    <p>Manage your academic background and upload transcripts</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Add Education Record - Full width card -->
<div class="card modern-card full-width">
    <h3><i class="fas fa-plus-circle"></i> Add Education Record</h3>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="institution_name">Institution Name *</label>
                <input type="text" id="institution_name" name="institution_name" required placeholder="e.g., University of Example">
            </div>
            <div class="form-group">
                <label for="country_code">Country</label>
                <select id="country_code" name="country_code">
                    <option value="">Select Country</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo $country['iso2']; ?>"><?php echo $country['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="level">Level of Education *</label>
                <select id="level" name="level" required>
                    <option value="">Select Level</option>
                    <option value="High School">High School</option>
                    <option value="UG">Undergraduate</option>
                    <option value="GRAD">Graduate</option>
                    <option value="PHD">PhD</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="degree_name">Degree/Diploma Name *</label>
                <input type="text" id="degree_name" name="degree_name" required placeholder="e.g., Bachelor of Science">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="gpa">GPA/Score</label>
                <input type="number" step="0.01" id="gpa" name="gpa" placeholder="e.g., 3.5">
            </div>
            <div class="form-group">
                <label for="major">Major (Optional)</label>
                <input type="text" id="major" name="major" placeholder="e.g., Computer Science">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date">
            </div>
            <div class="form-group">
                <label for="end_date">End Date (or Expected)</label>
                <input type="date" id="end_date" name="end_date">
            </div>
        </div>
        
        <button type="submit" name="add_education" class="btn btn-primary">Add Education</button>
    </form>
</div>

<!-- Existing Records -->
<?php if (empty($education_records)): ?>
    <div class="empty-state">
        <i class="fas fa-graduation-cap fa-4x"></i>
        <h4>No education records yet</h4>
        <p>Add your first education record using the form above.</p>
    </div>
<?php else: ?>
    <h3 style="margin-top: 2rem;">Your Education History</h3>
    <div class="records-grid">
        <?php foreach ($education_records as $record): ?>
            <div class="record-card">
                <div class="record-header">
                    <h4><?php echo htmlspecialchars($record['institution_name']); ?></h4>
                    <span class="record-level"><?php echo $record['level']; ?></span>
                </div>
                <div class="record-body">
                    <div class="record-detail">
                        <i class="fas fa-graduation-cap"></i>
                        <span><?php echo htmlspecialchars($record['degree_name']); ?></span>
                    </div>
                    <?php if (!empty($record['major'])): ?>
                        <div class="record-detail">
                            <i class="fas fa-book"></i>
                            <span>Major: <?php echo htmlspecialchars($record['major']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($record['country_name']): ?>
                        <div class="record-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo $record['country_name']; ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($record['gpa']): ?>
                        <div class="record-detail">
                            <i class="fas fa-star"></i>
                            <span>GPA: <?php echo $record['gpa']; ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($record['start_date']): ?>
                        <div class="record-detail">
                            <i class="far fa-calendar-alt"></i>
                            <span>
                                <?php echo Helpers::formatDate($record['start_date'], 'M Y'); ?> - 
                                <?php echo $record['end_date'] ? Helpers::formatDate($record['end_date'], 'M Y') : 'Present'; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Transcripts section -->
                <?php 
                $transcripts = $db->fetchAll(
                    "SELECT a.* FROM attachments a
                     JOIN education_documents ed ON a.attachment_id = ed.attachment_id
                     WHERE ed.education_record_id = ? AND a.deleted_at IS NULL",
                    [$record['record_id']]
                );
                ?>
                <?php if (!empty($transcripts)): ?>
                    <div class="transcript-list">
                        <strong>Transcripts:</strong>
                        <ul>
                            <?php foreach ($transcripts as $doc): ?>
                                <li>
                                    <a href="<?php echo $doc['file_url']; ?>" target="_blank" class="transcript-link">
                                        <i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($doc['filename']); ?>
                                        <small>(<?php echo round($doc['file_size']/1024, 1); ?> KB)</small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="record-footer">
                    <button class="btn btn-small btn-primary" onclick="toggleUpload(<?php echo $record['record_id']; ?>)">
                        <i class="fas fa-upload"></i> Upload Transcript
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this education record?');">
                        <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                        <button type="submit" name="delete_record" class="btn btn-small btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Hidden upload form -->
                <div id="upload-<?php echo $record['record_id']; ?>" class="upload-form" style="display:none;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                        <div class="form-group">
                            <label>Select Transcript File</label>
                            <input type="file" name="transcript" accept=".pdf,.doc,.docx,.jpg,.png" required>
                            <small>PDF, DOC, JPG, PNG (Max 5MB)</small>
                        </div>
                        <div class="upload-actions">
                            <button type="submit" class="btn btn-small btn-success">Upload</button>
                            <button type="button" class="btn btn-small btn-secondary" onclick="toggleUpload(<?php echo $record['record_id']; ?>)">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function toggleUpload(id) {
    var uploadDiv = document.getElementById('upload-' + id);
    if (uploadDiv.style.display === 'none' || uploadDiv.style.display === '') {
        uploadDiv.style.display = 'block';
    } else {
        uploadDiv.style.display = 'none';
    }
}
</script>

<style>
/* Full-width card */
.full-width {
    width: 100%;
    margin-bottom: 2rem;
}

/* Form styling (reused from previous) */
.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.9rem;
}
.form-group input,
.form-group select {
    width: 100%;
    padding: 0.7rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}

/* Records grid (unchanged) */
.records-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}
.record-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
}
.record-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.1);
}
.record-header {
    padding: 1.5rem 1.5rem 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.record-header h4 {
    margin: 0;
    font-size: 1.2rem;
    color: #2c3e50;
}
.record-level {
    background: #3498db;
    color: white;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.record-body {
    padding: 0 1.5rem 1rem;
}
.record-detail {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
    font-size: 0.95rem;
    color: #4a5568;
}
.record-detail i {
    width: 20px;
    color: #3498db;
    font-size: 1rem;
}
.transcript-list {
    padding: 0.8rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
}
.transcript-list strong {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #2c3e50;
}
.transcript-list ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
}
.transcript-list li {
    margin: 0.3rem 0;
}
.transcript-link {
    color: #3498db;
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.transcript-link:hover {
    text-decoration: underline;
}
.transcript-link i {
    font-size: 1rem;
}
.record-footer {
    padding: 1rem 1.5rem;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    background: white;
}
.upload-form {
    padding: 1rem 1.5rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e9ecef;
}
.upload-form .form-group {
    margin-bottom: 1rem;
}
.upload-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
.btn {
    padding: 0.6rem 1.2rem;
    border-radius: 40px;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(52,152,219,0.3);
}
.btn-danger {
    background: #e74c3c;
    color: white;
}
.btn-danger:hover {
    background: #c0392b;
    transform: translateY(-2px);
}
.btn-secondary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
}
.btn-secondary:hover {
    background: #e9ecef;
}
.btn-success {
    background: #27ae60;
    color: white;
}
.btn-success:hover {
    background: #229954;
}
.btn-small {
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
}
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    margin-top: 2rem;
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}
.empty-state h4 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}
.empty-state p {
    color: #7f8c8d;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>