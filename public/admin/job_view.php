<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Job Details";
require_once '../../app/templates/header.php';

$db = new Database();

$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$job = $db->fetchOne("
    SELECT j.*, c.name as country_name
    FROM jobs j
    JOIN countries c ON j.country_code = c.iso2
    WHERE j.job_id = ?
", [$job_id]);

if (!$job) {
    echo "<div class='alert alert-error'>Job not found.</div>";
    echo "<a href='job_applications.php' class='btn btn-secondary'>Back</a>";
    require_once '../../app/templates/footer.php';
    exit();
}

$applications_count = $db->fetchOne("SELECT COUNT(*) as count FROM job_applications WHERE job_id = ?", [$job_id])['count'];
?>

<div class="page-header">
    <h1><i class="fas fa-briefcase"></i> Job Details</h1>
    <a href="job_applications.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3><?= htmlspecialchars($job['title']) ?></h3>
            </div>
            <div class="card-body">
                <table class="info-table">
                    <tr><td><strong>Company:</strong></td><td><?= htmlspecialchars($job['company']) ?></td></tr>
                    <tr><td><strong>Country:</strong></td><td><?= $job['country_name'] ?></td></tr>
                    <tr><td><strong>Job Type:</strong></td><td><?= ucfirst($job['job_type']) ?></td></tr>
                    <tr><td><strong>Sector:</strong></td><td><?= $job['sector'] ?: '—' ?></td></tr>
                    <tr><td><strong>Posted:</strong></td><td><?= Helpers::formatDate($job['posted_date']) ?></td></tr>
                    <tr><td><strong>Deadline:</strong></td><td><?= $job['application_deadline'] ? Helpers::formatDate($job['application_deadline']) : 'N/A' ?></td></tr>
                    <tr><td><strong>Status:</strong></td><td>
                        <?php if ($job['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><td><strong>Applications:</strong></td><td><?= $applications_count ?></td></tr>
                </table>
                <?php if ($job['description']): ?>
                    <hr>
                    <h4>Description</h4>
                    <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
                <?php endif; ?>
                <?php if ($job['website']): ?>
                    <hr>
                    <p><a href="<?= htmlspecialchars($job['website']) ?>" target="_blank" class="btn btn-outline-primary">Visit Website</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <a href="jobs.php?edit=<?= $job['job_id'] ?>" class="btn btn-primary btn-block">Edit Job</a>
                <a href="../student/jobs.php" target="_blank" class="btn btn-outline-secondary btn-block">View on Student Side</a>
            </div>
        </div>
    </div>
</div>

<style>
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
.badge-success {
    background: #28a745;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
}
.badge-secondary {
    background: #6c757d;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
}
.btn-block {
    display: block;
    width: 100%;
    margin-bottom: 0.5rem;
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>