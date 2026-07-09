<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Visa Management";
require_once '../../app/templates/header.php';

$db = new Database();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

switch($action) {
    case 'view':
        viewVisa($id);
        break;
    case 'update':
        updateVisa($id);
        break;
    default:
        listVisas();
}

function listVisas() {
    global $db;
    
    $status = $_GET['status'] ?? '';
    $country = $_GET['country'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $where = "1=1";
    $params = [];
    
    if ($status && $status != 'all') {
        $where .= " AND v.status = ?";
        $params[] = $status;
    }
    
    if ($country) {
        $where .= " AND vt.country_code = ?";
        $params[] = $country;
    }
    
    if ($search) {
        $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR vt.name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $visas = $db->fetchAll("
        SELECT v.*, vt.name as visa_type_name, vt.country_code,
               u.username as student_name, u.email as student_email,
               p.program_name, u2.name as university_name,
               e.name as embassy_name,
               DATE_FORMAT(v.application_date, '%Y-%m-%d') as app_date,
               DATE_FORMAT(v.expiry_date, '%Y-%m-%d') as exp_date
        FROM student_visas v
        JOIN visa_types vt ON v.visa_type_id = vt.visa_type_id
        JOIN students s ON v.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN programs p ON v.program_id = p.program_id
        LEFT JOIN universities u2 ON p.university_id = u2.university_id
        LEFT JOIN embassy_consulate_records e ON v.embassy_id = e.embassy_id
        WHERE $where
        ORDER BY v.created_at DESC
        LIMIT 100
    ", $params);
    
    // Compute stats
    $total = count($visas);
    $stats = [
        'approved' => count(array_filter($visas, fn($v) => $v['status'] == 'approved')),
        'rejected' => count(array_filter($visas, fn($v) => $v['status'] == 'rejected')),
        'processing' => count(array_filter($visas, fn($v) => $v['status'] == 'processing')),
        'applied' => count(array_filter($visas, fn($v) => $v['status'] == 'applied')),
        'not_started' => count(array_filter($visas, fn($v) => $v['status'] == 'not_started'))
    ];
    
    $countries = $db->fetchAll("SELECT DISTINCT country_code FROM visa_types ORDER BY country_code");
    ?>
    
    <div class="page-header">
        <h1><i class="fas fa-passport"></i> Visa Management</h1>
        <p class="text-muted">Oversee all student visa applications</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
                <i class="fas fa-passport"></i>
            </div>
            <div class="stat-content">
                <h3><?= $total ?></h3>
                <p>Total Visas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['approved'] ?></h3>
                <p>Approved</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['processing'] + $stats['applied'] ?></h3>
                <p>In Progress</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['rejected'] ?></h3>
                <p>Rejected</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
                <i class="fas fa-hourglass-start"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['not_started'] ?></h3>
                <p>Not Started</p>
            </div>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filter Visas</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search student or visa..." 
                       value="<?= htmlspecialchars($search) ?>">
                <select name="status">
                    <option value="all">All Statuses</option>
                    <option value="not_started" <?= $status == 'not_started' ? 'selected' : '' ?>>Not Started</option>
                    <option value="applied" <?= $status == 'applied' ? 'selected' : '' ?>>Applied</option>
                    <option value="processing" <?= $status == 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="approved" <?= $status == 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <select name="country">
                    <option value="">All Countries</option>
                    <?php foreach($countries as $c): ?>
                        <option value="<?= $c['country_code'] ?>" <?= $country == $c['country_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['country_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="visa_management.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
    </div>
    
    <!-- Visas Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Visa Applications (<?= $total ?>)</h3>
        </div>
        <div class="card-body p-0">
            <div class="scrollable-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Visa Type</th>
                            <th>Country</th>
                            <th>Program</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($visas as $visa): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($visa['student_name']) ?></strong><br>
                                <small><?= htmlspecialchars($visa['student_email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($visa['visa_type_name']) ?></td>
                            <td><?= htmlspecialchars($visa['country_code']) ?></td>
                            <td>
                                <?= $visa['program_name'] ? htmlspecialchars($visa['program_name']) : 'N/A' ?><br>
                                <small><?= $visa['university_name'] ? htmlspecialchars($visa['university_name']) : '' ?></small>
                            </td>
                            <td><?= $visa['app_date'] ? date('M d, Y', strtotime($visa['app_date'])) : 'N/A' ?></td>
                            <td>
                                <span class="visa-status status-<?= strtolower($visa['status']) ?>">
                                    <?= ucfirst($visa['status']) ?>
                                </span>
                            </td>
                            <td><?= $visa['exp_date'] ? date('M d, Y', strtotime($visa['exp_date'])) : 'N/A' ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=view&id=<?= $visa['visa_id'] ?>" 
                                       class="btn-icon-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?action=update&id=<?= $visa['visa_id'] ?>" 
                                       class="btn-icon-sm btn-primary" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

function viewVisa($id) {
    global $db;
    
    $visa = $db->fetchOne("
        SELECT v.*, vt.name as visa_type_name, vt.country_code, vt.requirements,
               u.username as student_name, u.email as student_email,
               up.first_name, up.last_name, up.dob, up.nationality,
               p.program_name, u2.name as university_name,
               e.name as embassy_name, e.city as embassy_city, e.address as embassy_address
        FROM student_visas v
        JOIN visa_types vt ON v.visa_type_id = vt.visa_type_id
        JOIN students s ON v.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        LEFT JOIN programs p ON v.program_id = p.program_id
        LEFT JOIN universities u2 ON p.university_id = u2.university_id
        LEFT JOIN embassy_consulate_records e ON v.embassy_id = e.embassy_id
        WHERE v.visa_id = ?
    ", [$id]);
    
    if (!$visa) {
        echo "<div class='alert alert-error'>Visa not found.</div>";
        return;
    }
    
    $documents = $db->fetchAll("
        SELECT vd.*, a.filename, a.file_url, a.file_type
        FROM visa_documents vd
        JOIN attachments a ON vd.attachment_id = a.attachment_id
        WHERE vd.visa_id = ?
    ", [$id]);
    
    $history = $db->fetchAll("
        SELECT h.*, u.username as changed_by_name
        FROM visa_status_history h
        LEFT JOIN users u ON h.changed_by = u.user_id
        WHERE h.visa_id = ?
        ORDER BY h.changed_at DESC
    ", [$id]);
    ?>
    
    <div class="page-header">
        <h1><i class="fas fa-eye"></i> Visa #<?= $id ?> Details</h1>
        <a href="visa_management.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Visa Overview</h3>
        </div>
        <div class="card-body">
            <div class="details-grid">
                <div>
                    <h4>Student Information</h4>
                    <p><strong>Name:</strong> <?= htmlspecialchars($visa['first_name'] ?? '') ?> <?= htmlspecialchars($visa['last_name'] ?? '') ?></p>
                    <p><strong>Username:</strong> <?= htmlspecialchars($visa['student_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($visa['student_email']) ?></p>
                    <p><strong>DOB:</strong> <?= $visa['dob'] ? Helpers::formatDate($visa['dob']) : 'N/A' ?></p>
                    <p><strong>Nationality:</strong> <?= htmlspecialchars($visa['nationality'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <h4>Visa Information</h4>
                    <p><strong>Type:</strong> <?= htmlspecialchars($visa['visa_type_name']) ?></p>
                    <p><strong>Country:</strong> <?= htmlspecialchars($visa['country_code']) ?></p>
                    <p><strong>Status:</strong> 
                        <span class="visa-status status-<?= strtolower($visa['status']) ?>">
                            <?= ucfirst($visa['status']) ?>
                        </span>
                    </p>
                    <p><strong>Applied:</strong> <?= $visa['application_date'] ? Helpers::formatDate($visa['application_date']) : 'N/A' ?></p>
                    <p><strong>Expiry:</strong> <?= $visa['expiry_date'] ? Helpers::formatDate($visa['expiry_date']) : 'N/A' ?></p>
                </div>
                <div>
                    <h4>Program Information</h4>
                    <p><strong>Program:</strong> <?= $visa['program_name'] ? htmlspecialchars($visa['program_name']) : 'N/A' ?></p>
                    <p><strong>University:</strong> <?= $visa['university_name'] ? htmlspecialchars($visa['university_name']) : 'N/A' ?></p>
                </div>
                <div>
                    <h4>Embassy Information</h4>
                    <p><strong>Embassy:</strong> <?= $visa['embassy_name'] ? htmlspecialchars($visa['embassy_name']) : 'N/A' ?></p>
                    <p><strong>City:</strong> <?= $visa['embassy_city'] ? htmlspecialchars($visa['embassy_city']) : 'N/A' ?></p>
                    <p><strong>Address:</strong> <?= $visa['embassy_address'] ? htmlspecialchars($visa['embassy_address']) : 'N/A' ?></p>
                </div>
            </div>
            
            <?php if($visa['notes']): ?>
                <div class="notes">
                    <h4>Notes</h4>
                    <p><?= nl2br(htmlspecialchars($visa['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Documents</h3>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <p class="text-muted">No documents uploaded yet.</p>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($documents as $doc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doc['filename']) ?></td>
                                    <td><?= htmlspecialchars($doc['document_type'] ?: 'Document') ?></td>
                                    <td><?= Helpers::formatDate($doc['uploaded_at']) ?></td>
                                    <td>
                                        <a href="<?= $doc['file_url'] ?>" target="_blank" class="btn-icon-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Status History</h3>
        </div>
        <div class="card-body">
            <?php if (empty($history)): ?>
                <p class="text-muted">No status history available.</p>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Changed By</th>
                                <th>Old Status</th>
                                <th>New Status</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $h): ?>
                                <tr>
                                    <td><?= Helpers::formatDate($h['changed_at'], 'M d, Y H:i') ?></td>
                                    <td><?= $h['changed_by_name'] ?: 'System' ?></td>
                                    <td><?= $h['old_status'] ? ucfirst($h['old_status']) : 'N/A' ?></td>
                                    <td><?= ucfirst($h['new_status']) ?></td>
                                    <td><?= htmlspecialchars($h['note'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <a href="?action=update&id=<?= $id ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Update Visa Status
    </a>
    <?php
}

function updateVisa($id) {
    global $db;
    
    $visa = $db->fetchOne("SELECT * FROM student_visas WHERE visa_id = ?", [$id]);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $status = $_POST['status'];
        $approval_date = $_POST['approval_date'] ?: null;
        $expiry_date = $_POST['expiry_date'] ?: null;
        $notes = $_POST['notes'];
        
        $data = [
            'status' => $status,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['user_id']
        ];
        
        if ($approval_date) {
            $data['approval_date'] = $approval_date;
        }
        
        if ($expiry_date) {
            $data['expiry_date'] = $expiry_date;
        }
        
        $db->update('student_visas', $data, "visa_id = $id");
        
        if ($visa['status'] != $status) {
            $db->insert('visa_status_history', [
                'visa_id' => $id,
                'old_status' => $visa['status'],
                'new_status' => $status,
                'changed_by' => $_SESSION['user_id'],
                'changed_at' => date('Y-m-d H:i:s'),
                'note' => $_POST['status_note']
            ]);
        }
        
        echo "<script>alert('Visa updated!'); location.href='?action=view&id=$id';</script>";
    }
    ?>
    
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Update Visa #<?= $id ?></h1>
        <a href="?action=view&id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Details
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Update Status & Details</h3>
        </div>
        <div class="card-body">
            <form method="POST" class="update-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="not_started" <?= $visa['status'] == 'not_started' ? 'selected' : '' ?>>Not Started</option>
                            <option value="applied" <?= $visa['status'] == 'applied' ? 'selected' : '' ?>>Applied</option>
                            <option value="processing" <?= $visa['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="approved" <?= $visa['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $visa['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="expired" <?= $visa['status'] == 'expired' ? 'selected' : '' ?>>Expired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status_note">Status Change Note</label>
                        <input type="text" id="status_note" name="status_note" class="form-control" placeholder="Reason for status change...">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="approval_date">Approval Date</label>
                        <input type="date" id="approval_date" name="approval_date" class="form-control" 
                               value="<?= $visa['approval_date'] ? date('Y-m-d', strtotime($visa['approval_date'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date" class="form-control" 
                               value="<?= $visa['expiry_date'] ? date('Y-m-d', strtotime($visa['expiry_date'])) : '' ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Admin Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"><?= htmlspecialchars($visa['notes'] ?? '') ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Visa</button>
                    <a href="?action=view&id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>

<style>
/* Reuse styles from other admin pages for consistency */
.page-header {
    margin-bottom: 2rem;
}
.page-header h1 {
    margin-bottom: 0.25rem;
}

/* Stats grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 18px;
    padding: 1rem 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.stat-content h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Cards */
.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    margin-bottom: 2rem;
    overflow: hidden;
}
.card-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-body {
    padding: 1.5rem;
}
.card-body.p-0 {
    padding: 0;
}

/* Filter form */
.filter-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.filter-form input,
.filter-form select {
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    font-size: 0.9rem;
    min-width: 180px;
}
.filter-form .btn {
    border-radius: 30px;
    padding: 0.6rem 1.5rem;
}

/* Scrollable table */
.scrollable-table {
    max-height: 500px;
    overflow-y: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}
.table th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    padding: 0.75rem 0.75rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
    z-index: 2;
}
.table td {
    padding: 0.75rem 0.75rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}
.table tr:hover td {
    background: #f8fafc;
}

/* Status badges */
.visa-status {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
}
.status-approved { background: #d4edda; color: #155724; }
.status-applied,
.status-processing { background: #fff3cd; color: #856404; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-not_started { background: #e2e3e5; color: #383d41; }

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}
.btn-icon-sm {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.btn-icon-sm.btn-info { background: #3498db; }
.btn-icon-sm.btn-info:hover { background: #2980b9; }
.btn-icon-sm.btn-primary { background: #3498db; }
.btn-icon-sm.btn-primary:hover { background: #2980b9; }

/* View details page */
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 1.5rem;
}
.details-grid h4 {
    margin-bottom: 1rem;
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 0.5rem;
}
.notes {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
}

/* Update form */
.update-form {
    max-width: 700px;
}
.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.form-group {
    flex: 1;
    min-width: 200px;
}
.form-group label {
    display: block;
    margin-bottom: 0.3rem;
    font-weight: 500;
    color: #4a5568;
}
.form-control {
    width: 100%;
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
}
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}
.btn {
    border-radius: 30px;
    padding: 0.5rem 1.5rem;
    border: none;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: #3498db;
    color: white;
}
.btn-primary:hover {
    background: #2980b9;
}
.btn-secondary {
    background: #e2e8f0;
    color: #2c3e50;
}
.btn-secondary:hover {
    background: #cbd5e0;
}
.btn-outline-secondary {
    background: transparent;
    border: 1px solid #cbd5e0;
    color: #4a5568;
}
.btn-outline-secondary:hover {
    background: #e2e8f0;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-form input,
    .filter-form select {
        width: 100%;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>