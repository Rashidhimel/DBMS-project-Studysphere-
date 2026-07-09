<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Reports & Analytics";
require_once '../../app/templates/header.php';

$db = new Database();

// Fetch all statistics including job and scholarship applications
$stats = $db->fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) as total_users,
        (SELECT COUNT(*) FROM students) as total_students,
        (SELECT COUNT(*) FROM applications WHERE deleted_at IS NULL) as total_applications,
        (SELECT COUNT(*) FROM universities WHERE deleted_at IS NULL) as total_universities,
        (SELECT COUNT(*) FROM programs WHERE deleted_at IS NULL) as total_programs,
        (SELECT COUNT(*) FROM attachments WHERE deleted_at IS NULL) as total_documents,
        (SELECT COUNT(*) FROM job_applications) as total_job_applications,
        (SELECT COUNT(*) FROM student_scholarships) as total_scholarship_applications
");

$status_breakdown = $db->fetchAll("
    SELECT s.name as status_name, COUNT(a.application_id) as count
    FROM application_statuses s
    LEFT JOIN applications a ON s.application_status_id = a.application_status_id AND a.deleted_at IS NULL
    GROUP BY s.name
    ORDER BY s.name
");

$recent_activity = $db->fetchAll("
    SELECT 
        'Application' as type,
        p.program_name,
        u.username,
        a.applied_at as date
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN students st ON a.student_id = st.student_id
    JOIN users u ON st.user_id = u.user_id
    WHERE a.deleted_at IS NULL
    ORDER BY a.applied_at DESC
    LIMIT 10
");

$top_universities = $db->fetchAll("
    SELECT un.name as university_name, COUNT(a.application_id) as application_count
    FROM universities un
    LEFT JOIN applications a ON un.university_id = a.university_id AND a.deleted_at IS NULL
    GROUP BY un.university_id
    ORDER BY application_count DESC
    LIMIT 5
");
?>

<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
    <p class="text-muted">System‑wide statistics and performance metrics</p>
</div>

<!-- Stats Grid - 8 cards in 2 rows of 4 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_users']; ?></h3>
            <p>Total Users</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_students']; ?></h3>
            <p>Students</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_applications']; ?></h3>
            <p>Applications</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
            <i class="fas fa-university"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_universities']; ?></h3>
            <p>Universities</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_programs']; ?></h3>
            <p>Programs</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #1abc9c, #16a085);">
            <i class="fas fa-file-upload"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_documents']; ?></h3>
            <p>Documents</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e67e22, #d35400);">
            <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_job_applications']; ?></h3>
            <p>Job Applications</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #8e44ad, #6c3483);">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_scholarship_applications']; ?></h3>
            <p>Scholarship Applications</p>
        </div>
    </div>
</div>

<!-- Main Reports Grid -->
<div class="dashboard-grid">
    <!-- Left Column -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Application Status Breakdown</h3>
        </div>
        <div class="scrollable-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_breakdown as $status): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($status['status_name']); ?></td>
                            <td><strong><?php echo $status['count']; ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-trophy"></i> Top Universities by Applications</h3>
        </div>
        <div class="scrollable-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>University</th>
                        <th>Applications</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_universities as $uni): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($uni['university_name']); ?></td>
                            <td><strong><?php echo $uni['application_count']; ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="dashboard-card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent Activity</h3>
    </div>
    <div class="scrollable-table">
        <table class="table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Details</th>
                    <th>User</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_activity as $activity): ?>
                    <tr>
                        <td><span class="badge activity-badge"><?php echo $activity['type']; ?></span></td>
                        <td><?php echo htmlspecialchars($activity['program_name']); ?></td>
                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                        <td><?php echo Helpers::formatDate($activity['date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Export Section -->
<div class="dashboard-card">
    <div class="card-header">
        <h3><i class="fas fa-download"></i> Export Reports</h3>
    </div>
    <div class="export-options">
        <button class="btn btn-outline-primary"><i class="fas fa-file-excel"></i> Excel</button>
        <button class="btn btn-outline-secondary"><i class="fas fa-file-pdf"></i> PDF</button>
        <button class="btn btn-outline-secondary"><i class="fas fa-print"></i> Print</button>
    </div>
</div>

<style>
/* Reuse styles from admin dashboard for consistency */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.2rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 18px;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.stat-content h3 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.8rem;
    font-weight: 500;
    letter-spacing: 0.3px;
}
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}
.dashboard-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
}
.scrollable-table {
    max-height: 280px;
    overflow-y: auto;
    border-radius: 12px;
    border: 1px solid #edf2f7;
}
.scrollable-table table {
    width: 100%;
    border-collapse: collapse;
}
.scrollable-table thead {
    position: sticky;
    top: 0;
    background: #f8fafc;
    z-index: 2;
}
.scrollable-table th {
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
}
.scrollable-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    background: white;
}
.scrollable-table tr:last-child td {
    border-bottom: none;
}
.badge.activity-badge {
    background: #e9ecef;
    color: #2c3e50;
    padding: 0.25rem 0.75rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.export-options {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.btn-outline-primary, .btn-outline-secondary {
    padding: 0.5rem 1.2rem;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 500;
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-outline-primary {
    border-color: #3498db;
    color: #3498db;
}
.btn-outline-primary:hover {
    background: #3498db;
    color: white;
}
.btn-outline-secondary {
    border-color: #95a5a6;
    color: #7f8c8d;
}
.btn-outline-secondary:hover {
    background: #95a5a6;
    color: white;
}
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .dashboard-grid { grid-template-columns: 1fr; }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>