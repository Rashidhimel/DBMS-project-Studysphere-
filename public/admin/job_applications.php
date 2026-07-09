<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Job Applications";
require_once '../../app/templates/header.php';

$db = new Database();

// Update status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $application_id = intval($_POST['application_id']);
    $status = Helpers::sanitize($_POST['status']);
    $db->update('job_applications', ['status' => $status], "application_id = $application_id");
    $success = "Application status updated.";
}

// Filtering
$status_filter = $_GET['status'] ?? '';
$job_filter = $_GET['job_id'] ?? 0;

$sql = "
    SELECT ja.*, j.title as job_title, j.company, j.country_code, j.description as job_description,
           c.name as country_name,
           s.student_id, u.user_id, u.username, up.first_name, up.last_name, u.email
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    JOIN countries c ON j.country_code = c.iso2
    JOIN students s ON ja.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE 1=1
";
$params = [];

if ($status_filter) {
    $sql .= " AND ja.status = ?";
    $params[] = $status_filter;
}
if ($job_filter) {
    $sql .= " AND ja.job_id = ?";
    $params[] = $job_filter;
}

$sql .= " ORDER BY ja.applied_at DESC";
$applications = $db->fetchAll($sql, $params);

$jobs = $db->fetchAll("SELECT job_id, title FROM jobs WHERE is_active = 1 ORDER BY title");

// Compute stats
$total_apps = count($applications);
$applied = count(array_filter($applications, fn($a) => $a['status'] == 'applied'));
$interviewing = count(array_filter($applications, fn($a) => $a['status'] == 'interviewing'));
$offered = count(array_filter($applications, fn($a) => $a['status'] == 'offered'));
$rejected = count(array_filter($applications, fn($a) => $a['status'] == 'rejected'));
$withdrawn = count(array_filter($applications, fn($a) => $a['status'] == 'withdrawn'));
?>

<div class="page-header">
    <h1><i class="fas fa-briefcase"></i> Job Applications</h1>
    <p class="text-muted">Review and manage student job applications</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total_apps ?></h3>
            <p>Total Applications</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?= $applied ?></h3>
            <p>Applied</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #17a2b8, #138496);">
            <i class="fas fa-comments"></i>
        </div>
        <div class="stat-content">
            <h3><?= $interviewing ?></h3>
            <p>Interviewing</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #28a745, #218838);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $offered ?></h3>
            <p>Offered</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #dc3545, #c82333);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $rejected ?></h3>
            <p>Rejected</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #6c757d, #5a6268);">
            <i class="fas fa-undo"></i>
        </div>
        <div class="stat-content">
            <h3><?= $withdrawn ?></h3>
            <p>Withdrawn</p>
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
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="applied" <?= $status_filter == 'applied' ? 'selected' : '' ?>>Applied</option>
                <option value="interviewing" <?= $status_filter == 'interviewing' ? 'selected' : '' ?>>Interviewing</option>
                <option value="offered" <?= $status_filter == 'offered' ? 'selected' : '' ?>>Offered</option>
                <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="withdrawn" <?= $status_filter == 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
            </select>
            <select name="job_id" class="form-control">
                <option value="">All Jobs</option>
                <?php foreach ($jobs as $j): ?>
                    <option value="<?= $j['job_id'] ?>" <?= $job_filter == $j['job_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($j['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="job_applications.php" class="btn btn-outline-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Applications Table Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Applications (<?= count($applications) ?>)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Job</th>
                        <th>Company</th>
                        <th>Country</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): 
                        $student_name = $app['first_name'] ? $app['first_name'] . ' ' . $app['last_name'] : $app['username'];
                        $status_colors = [
                            'applied' => '#f39c12',
                            'interviewing' => '#17a2b8',
                            'offered' => '#28a745',
                            'rejected' => '#dc3545',
                            'withdrawn' => '#6c757d'
                        ];
                        $status_color = $status_colors[$app['status']] ?? '#6c757d';
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($student_name) ?></strong><br>
                                <small><?= $app['email'] ?></small>
                            </td>
                            <td><?= htmlspecialchars($app['job_title']) ?></td>
                            <td><?= htmlspecialchars($app['company']) ?></td>
                            <td><?= $app['country_name'] ?></td>
                            <td><?= Helpers::formatDate($app['applied_at']) ?></td>
                            <td>
                                <span class="status-badge" style="background: <?= $status_color ?>;">
                                    <?= ucfirst($app['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="user_edit.php?id=<?= $app['user_id'] ?>" 
                                       class="btn-icon btn-info" title="View Student">
                                        <i class="fas fa-user"></i>
                                    </a>
                                    <a href="job_view.php?id=<?= $app['job_id'] ?>" 
                                       class="btn-icon btn-primary" title="View Job">
                                        <i class="fas fa-briefcase"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="applied" <?= $app['status']=='applied'?'selected':'' ?>>Applied</option>
                                            <option value="interviewing" <?= $app['status']=='interviewing'?'selected':'' ?>>Interviewing</option>
                                            <option value="offered" <?= $app['status']=='offered'?'selected':'' ?>>Offered</option>
                                            <option value="rejected" <?= $app['status']=='rejected'?'selected':'' ?>>Rejected</option>
                                            <option value="withdrawn" <?= $app['status']=='withdrawn'?'selected':'' ?>>Withdrawn</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-briefcase fa-4x"></i>
                <h4>No applications found</h4>
                <p>Try adjusting your filters or check back later.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

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
    min-width: 150px;
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

.action-buttons {
    display: flex;
    gap: 0.4rem;
    align-items: center;
    flex-wrap: wrap;
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
.status-select {
    width: 120px;
    padding: 0.3rem;
    border-radius: 8px;
    border: 1px solid #ced4da;
    font-size: 0.85rem;
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

/* Responsive */
@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form select,
    .filter-form .btn {
        width: 100%;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>