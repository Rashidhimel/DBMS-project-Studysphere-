<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Application Management";
require_once '../../app/templates/header.php';

$db = new Database();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

switch($action) {
    case 'view':
        viewApplication($id);
        break;
    case 'update':
        updateApplication($id);
        break;
    default:
        listApplications();
}

function listApplications() {
    global $db;
    
    $status = $_GET['status'] ?? '';
    $program = $_GET['program'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $where = "a.deleted_at IS NULL";
    $params = [];
    
    if ($status) {
        $where .= " AND a.application_status_id = ?";
        $params[] = $status;
    }
    if ($program) {
        $where .= " AND a.program_id = ?";
        $params[] = $program;
    }
    if ($search) {
        $where .= " AND (us.username LIKE ? OR us.email LIKE ? OR p.program_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $applications = $db->fetchAll("
        SELECT a.*, p.program_name, u.name as university_name, 
               us.username as student_name, st.name as status_name,
               u2.username as reviewer_name,
               up.first_name, up.last_name
        FROM applications a
        JOIN programs p ON a.program_id = p.program_id
        JOIN universities u ON a.university_id = u.university_id
        JOIN students st2 ON a.student_id = st2.student_id
        JOIN users us ON st2.user_id = us.user_id
        LEFT JOIN user_profiles up ON us.user_id = up.user_id
        JOIN application_statuses st ON a.application_status_id = st.application_status_id
        LEFT JOIN users u2 ON a.decision_by = u2.user_id
        WHERE $where
        ORDER BY a.applied_at DESC
        LIMIT 50
    ", $params);
    
    $statuses = $db->fetchAll("SELECT * FROM application_statuses ORDER BY name");
    $programs = $db->fetchAll("SELECT program_id, program_name FROM programs WHERE deleted_at IS NULL ORDER BY program_name");
    
    // Compute stats
    $total = count($applications);
    $submitted = count(array_filter($applications, fn($a) => $a['status_name'] == 'Submitted'));
    $under_review = count(array_filter($applications, fn($a) => $a['status_name'] == 'Under Review'));
    $accepted = count(array_filter($applications, fn($a) => $a['status_name'] == 'Accepted'));
    ?>
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Application Management</h1>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= $total ?></h3>
                <p>Total Applications</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?= $submitted ?></h3>
                <p>Submitted</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #17a2b8, #138496);">
                <i class="fas fa-search"></i>
            </div>
            <div class="stat-content">
                <h3><?= $under_review ?></h3>
                <p>Under Review</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #28a745, #218838);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $accepted ?></h3>
                <p>Accepted</p>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filter Applications</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search student, email, program..." 
                       value="<?= htmlspecialchars($search) ?>" class="form-control">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach($statuses as $s): ?>
                        <option value="<?= $s['application_status_id'] ?>" <?= $status == $s['application_status_id'] ? 'selected' : '' ?>>
                            <?= $s['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="program" class="form-control">
                    <option value="">All Programs</option>
                    <?php foreach($programs as $p): ?>
                        <option value="<?= $p['program_id'] ?>" <?= $program == $p['program_id'] ? 'selected' : '' ?>>
                            <?= $p['program_name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="applications.php" class="btn btn-outline-secondary">Clear</a>
            </form>
        </div>
    </div>

    <!-- Applications Table Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Applications (<?= count($applications) ?>)</h3>
            <small>Showing last 50 applications</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Program</th>
                            <th>University</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Reviewer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($applications as $app): 
                            $status_color = [
                                'Draft' => '#6c757d',
                                'Submitted' => '#f39c12',
                                'Under Review' => '#17a2b8',
                                'Accepted' => '#28a745',
                                'Rejected' => '#dc3545',
                                'Withdrawn' => '#6c757d'
                            ][$app['status_name']] ?? '#6c757d';
                        ?>
                        <tr>
                            <td><span class="badge badge-secondary">#<?= $app['application_id'] ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($app['student_name']) ?></strong><br>
                                <small><?= $app['first_name'] ? htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) : '' ?></small>
                            </td>
                            <td><?= htmlspecialchars($app['program_name']) ?></td>
                            <td><?= htmlspecialchars($app['university_name']) ?></td>
                            <td><?= Helpers::formatDate($app['applied_at'], 'M d, Y') ?></td>
                            <td>
                                <span class="status-badge" style="background: <?= $status_color ?>;">
                                    <?= $app['status_name'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($app['reviewer_name'] ?: 'Not assigned') ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="application_view.php?id=<?= $app['application_id'] ?>" 
                                       class="btn-icon btn-info" title="Detailed View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="applications.php?action=update&id=<?= $app['application_id'] ?>" 
                                       class="btn-icon btn-primary" title="Quick Update">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt fa-4x"></i>
                    <h4>No applications found</h4>
                    <p>Try adjusting your filters or check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function viewApplication($id) {
    global $db;
    
    $app = $db->fetchOne("
        SELECT a.*, p.program_name, u.name as university_name, 
               us.username as student_name, st.name as status_name,
               us.email as student_email,
               up.first_name, up.last_name
        FROM applications a
        JOIN programs p ON a.program_id = p.program_id
        JOIN universities u ON a.university_id = u.university_id
        JOIN students st2 ON a.student_id = st2.student_id
        JOIN users us ON st2.user_id = us.user_id
        LEFT JOIN user_profiles up ON us.user_id = up.user_id
        JOIN application_statuses st ON a.application_status_id = st.application_status_id
        WHERE a.application_id = ?
    ", [$id]);
    
    if (!$app) {
        echo "<div class='alert alert-error'>Application not found.</div>";
        return;
    }
    ?>
    <div class="page-header">
        <h1><i class="fas fa-eye"></i> Application #<?= $id ?></h1>
        <a href="applications.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Application Details</h3>
                </div>
                <div class="card-body">
                    <table class="info-table">
                        <tr><td><strong>Student:</strong></td><td><?= $app['first_name'] ?? '' ?> <?= $app['last_name'] ?? '' ?> (<?= $app['student_name'] ?>)</td></tr>
                        <tr><td><strong>Email:</strong></td><td><?= $app['student_email'] ?></td></tr>
                        <tr><td><strong>Program:</strong></td><td><?= $app['program_name'] ?></td></tr>
                        <tr><td><strong>University:</strong></td><td><?= $app['university_name'] ?></td></tr>
                        <tr><td><strong>Applied:</strong></td><td><?= Helpers::formatDate($app['applied_at'], 'M d, Y H:i') ?></td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="status-badge" style="background: <?= 
                            ['Draft'=>'#6c757d','Submitted'=>'#f39c12','Under Review'=>'#17a2b8','Accepted'=>'#28a745','Rejected'=>'#dc3545','Withdrawn'=>'#6c757d'][$app['status_name']] ?? '#6c757d' ?>">
                            <?= $app['status_name'] ?>
                        </span></td></tr>
                        <tr><td><strong>Deadline:</strong></td><td><?= $app['deadline'] ? Helpers::formatDate($app['deadline']) : 'N/A' ?></td></tr>
                        <tr><td><strong>Decision Date:</strong></td><td><?= $app['decision_date'] ? Helpers::formatDate($app['decision_date']) : 'Pending' ?></td></tr>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Admin Notes</h3>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($app['admin_notes'] ?? 'No notes yet.')) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <a href="applications.php?action=update&id=<?= $id ?>" class="btn btn-primary btn-block">
                        <i class="fas fa-edit"></i> Update Application
                    </a>
                    <a href="mailto:<?= $app['student_email'] ?>" class="btn btn-outline-primary btn-block">
                        <i class="fas fa-envelope"></i> Email Student
                    </a>
                    <a href="user_edit.php?id=<?= $app['student_id'] ?>" class="btn btn-outline-secondary btn-block">
                        <i class="fas fa-user"></i> View Student Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function updateApplication($id) {
    global $db;
    
    $app = $db->fetchOne("SELECT * FROM applications WHERE application_id = ?", [$id]);
    $statuses = $db->fetchAll("SELECT * FROM application_statuses ORDER BY name");
    $stages = $db->fetchAll("SELECT * FROM application_stages ORDER BY order_index");
    $admins = $db->fetchAll("SELECT user_id, username FROM users WHERE default_role_id IN (2,4) ORDER BY username");
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = [
            'application_status_id' => $_POST['application_status_id'],
            'application_stage_id' => $_POST['application_stage_id'] ?: null,
            'decision_by' => $_POST['decision_by'] ?: null,
            'admin_notes' => $_POST['admin_notes'],
            'status_reason' => $_POST['status_reason'],
            'decision_date' => $_POST['decision_date'] ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['user_id']
        ];
        
        if ($_POST['application_status_id'] == 5) { // accepted
            $data['decision_date'] = date('Y-m-d H:i:s');
        }
        
        $db->update('applications', $data, "application_id = $id");
        
        $db->insert('application_events', [
            'application_id' => $id,
            'actor_user_id' => $_SESSION['user_id'],
            'event_type' => 'status_change',
            'event_data' => json_encode([
                'old_status' => $app['application_status_id'],
                'new_status' => $_POST['application_status_id'],
                'notes' => $_POST['status_reason']
            ]),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<script>alert('Application updated!'); location.href='applications.php?action=view&id=$id';</script>";
    }
    ?>
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Update Application #<?= $id ?></h1>
        <a href="applications.php?action=view&id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> View Application
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Update Status</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="application_status_id" required class="form-control">
                            <?php foreach($statuses as $s): ?>
                                <option value="<?= $s['application_status_id'] ?>" <?= ($app['application_status_id'] ?? '') == $s['application_status_id'] ? 'selected' : '' ?>>
                                    <?= $s['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stage</label>
                        <select name="application_stage_id" class="form-control">
                            <option value="">Select Stage</option>
                            <?php foreach($stages as $st): ?>
                                <option value="<?= $st['application_stage_id'] ?>" <?= ($app['application_stage_id'] ?? '') == $st['application_stage_id'] ? 'selected' : '' ?>>
                                    <?= $st['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Decision By</label>
                        <select name="decision_by" class="form-control">
                            <option value="">Not Assigned</option>
                            <?php foreach($admins as $a): ?>
                                <option value="<?= $a['user_id'] ?>" <?= ($app['decision_by'] ?? '') == $a['user_id'] ? 'selected' : '' ?>>
                                    <?= $a['username'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Decision Date</label>
                        <input type="date" name="decision_date" class="form-control"
                               value="<?= $app['decision_date'] ? date('Y-m-d', strtotime($app['decision_date'])) : '' ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status Change Reason</label>
                    <input type="text" name="status_reason" class="form-control"
                           value="<?= htmlspecialchars($app['status_reason'] ?? '') ?>" 
                           placeholder="Reason for status change">
                </div>
                
                <div class="form-group">
                    <label>Admin Notes</label>
                    <textarea name="admin_notes" class="form-control" rows="4"><?= htmlspecialchars($app['admin_notes'] ?? '') ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Application</button>
                    <a href="applications.php?action=view&id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>

<style>
/* Reuse modern styles from other admin pages */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.06);
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
.stat-content h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.8rem;
    font-weight: 500;
}

.card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.02);
    margin-bottom: 2rem;
    overflow: hidden;
}
.card-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
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

.filter-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-form .form-control {
    min-width: 200px;
    flex: 1;
}
.btn-outline-secondary {
    border-color: #ced4da;
    color: #6c757d;
}
.btn-outline-secondary:hover {
    background: #e2e6ea;
}

.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
}
.table td {
    padding: 1rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}
.table tbody tr:last-child td {
    border-bottom: none;
}
.table-hover tbody tr:hover {
    background: #f9fbfd;
}

.status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
}
.badge-secondary {
    background: #e2e3e5;
    color: #383d41;
    padding: 0.2rem 0.5rem;
    border-radius: 30px;
    font-size: 0.8rem;
}

.action-buttons {
    display: flex;
    gap: 0.4rem;
}
.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-icon.btn-info { background: #17a2b8; }
.btn-icon.btn-primary { background: #3498db; }
.btn-icon:hover {
    opacity: 0.8;
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #718096;
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}
.empty-state h4 {
    margin-bottom: 0.5rem;
    color: #4a5568;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}
.info-table td {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f1f1;
}
.info-table td:first-child {
    font-weight: 600;
    color: #4a5568;
}
.info-table tr:last-child td {
    border-bottom: none;
}

.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.form-group {
    flex: 1;
    min-width: 200px;
}
.form-group label {
    display: block;
    margin-bottom: 0.4rem;
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
.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    border-radius: 30px;
    padding: 0.6rem 2rem;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52,152,219,0.3);
}
.btn-outline-secondary {
    border-radius: 30px;
    padding: 0.6rem 2rem;
}
.btn-block {
    display: block;
    width: 100%;
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form .form-control,
    .filter-form .btn {
        width: 100%;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>