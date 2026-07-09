<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Admin Dashboard";
require_once '../../app/templates/header.php';

$db = new Database();

$stats = $db->fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM students) as total_students,
        (SELECT COUNT(*) FROM applications) as total_applications,
        (SELECT COUNT(*) FROM universities) as total_universities,
        (SELECT COUNT(*) FROM scholarships) as total_scholarships,
        (SELECT COUNT(*) FROM attachments WHERE verified_at IS NULL AND deleted_at IS NULL) as pending_documents,
        (SELECT COUNT(*) FROM job_applications) as total_job_applications,
        (SELECT COUNT(*) FROM student_scholarships) as total_scholarship_applications,
        (SELECT COUNT(*) FROM applications WHERE application_status_id = (SELECT application_status_id FROM application_statuses WHERE code = 'submitted')) as submitted_applications
");

$recent_users = $db->fetchAll("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN roles r ON u.default_role_id = r.role_id 
    ORDER BY u.created_at DESC 
    LIMIT 5
");

$recent_applications = $db->fetchAll("
    SELECT a.*, p.program_name, u.name as university_name, s.username as student_name,
           st.name as status_name
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    JOIN students st2 ON a.student_id = st2.student_id
    JOIN users s ON st2.user_id = s.user_id
    JOIN application_statuses st ON a.application_status_id = st.application_status_id
    ORDER BY a.created_at DESC
    LIMIT 5
");

$pending_docs = $db->fetchAll("
    SELECT a.*, u.username 
    FROM attachments a
    JOIN users u ON a.uploader_user_id = u.user_id
    WHERE a.verified_at IS NULL AND a.deleted_at IS NULL
    ORDER BY a.created_at DESC
    LIMIT 5
");
?>

<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
    <p class="text-muted">System overview and management at a glance</p>
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
            <?php if ($stats['submitted_applications'] > 0): ?>
                <small><?php echo $stats['submitted_applications']; ?> submitted</small>
            <?php endif; ?>
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
            <i class="fas fa-award"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_scholarships']; ?></h3>
            <p>Scholarships</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #1abc9c, #16a085);">
            <i class="fas fa-file-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['pending_documents']; ?></h3>
            <p>Pending Docs</p>
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

<!-- Recent Activity - Scrollable Tables -->
<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-user-clock"></i> Recent Users</h3>
        </div>
        <div class="scrollable-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge"><?php echo ucfirst($user['role_name']); ?></span></td>
                            <td><?php echo Helpers::formatDate($user['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <a href="users.php" class="btn btn-outline-primary btn-sm">View All Users</a>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-file-signature"></i> Recent Applications</h3>
        </div>
        <div class="scrollable-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_applications as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['program_name']); ?><br><small><?php echo htmlspecialchars($app['university_name']); ?></small></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($app['status_name']); ?>">
                                    <?php echo $app['status_name']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="application_view.php?id=<?php echo $app['application_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <a href="applications.php" class="btn btn-outline-primary btn-sm">View All Applications</a>
        </div>
    </div>
</div>

<!-- Quick Actions Grid -->
<div class="dashboard-card">
    <div class="card-header">
        <h3><i class="fas fa-rocket"></i> Quick Actions</h3>
    </div>
    <div class="quick-actions-grid">
        <a href="users.php?action=add" class="action-card">
            <div class="action-icon" style="background: #e74c3c;"><i class="fas fa-user-plus"></i></div>
            <div class="action-content">
                <h4>Add User</h4>
                <p>Create new user</p>
            </div>
        </a>
        <a href="universities.php" class="action-card">
            <div class="action-icon" style="background: #3498db;"><i class="fas fa-university"></i></div>
            <div class="action-content">
                <h4>Manage Universities</h4>
                <p>Add/Edit universities</p>
            </div>
        </a>
        <a href="programs.php?action=add" class="action-card">
            <div class="action-icon" style="background: #2ecc71;"><i class="fas fa-plus-circle"></i></div>
            <div class="action-content">
                <h4>Add Program</h4>
                <p>Create new program</p>
            </div>
        </a>
        <a href="scholarships.php" class="action-card">
            <div class="action-icon" style="background: #f39c12;"><i class="fas fa-award"></i></div>
            <div class="action-content">
                <h4>Manage Scholarships</h4>
                <p>Add scholarships</p>
            </div>
        </a>
        <a href="scholarship_applications.php" class="action-card">
            <div class="action-icon" style="background: #ffc107;"><i class="fas fa-award"></i></div>
            <div class="action-content">
                <h4>Scholarship Applications</h4>
                <p>Review and award</p>
            </div>
        </a>
        <a href="attachments_moderation.php" class="action-card">
            <div class="action-icon" style="background: #1abc9c;"><i class="fas fa-file-alt"></i></div>
            <div class="action-content">
                <h4>Moderate Files</h4>
                <p>Verify documents</p>
            </div>
        </a>
        <a href="applications.php" class="action-card">
            <div class="action-icon" style="background: #9b59b6;"><i class="fas fa-eye"></i></div>
            <div class="action-content">
                <h4>View Applications</h4>
                <p>All applications</p>
            </div>
        </a>
        <a href="visa_management.php" class="action-card">
            <div class="action-icon" style="background: #34495e;"><i class="fas fa-passport"></i></div>
            <div class="action-content">
                <h4>Manage Visas</h4>
                <p>Visa processing</p>
            </div>
        </a>
        <a href="report.php" class="action-card">
            <div class="action-icon" style="background: #8e44ad;"><i class="fas fa-chart-bar"></i></div>
            <div class="action-content">
                <h4>View Reports</h4>
                <p>Analytics & reports</p>
            </div>
        </a>
        <a href="jobs.php" class="action-card">
            <div class="action-icon" style="background: #e67e22;"><i class="fas fa-briefcase"></i></div>
            <div class="action-content">
                <h4>Manage Jobs</h4>
                <p>Part-time job listings</p>
            </div>
        </a>
        <a href="job_applications.php" class="action-card">
            <div class="action-icon" style="background: #17a2b8;"><i class="fas fa-briefcase"></i></div>
            <div class="action-content">
                <h4>Job Applications</h4>
                <p>Manage part-time job applications</p>
            </div>
        </a>
        <a href="exam_resources.php" class="action-card">
            <div class="action-icon" style="background: #27ae60;"><i class="fas fa-book-open"></i></div>
            <div class="action-content">
                <h4>Exam Resources</h4>
                <p>Manage prep materials</p>
            </div>
        </a>
        <a href="email_templates.php" class="action-card">
            <div class="action-icon" style="background: #8e44ad;"><i class="fas fa-envelope-open-text"></i></div>
            <div class="action-content">
                <h4>Email Templates</h4>
                <p>Customize notifications</p>
            </div>
        </a>
        <a href="milestone_definitions.php" class="action-card">
            <div class="action-icon" style="background: #f1c40f;"><i class="fas fa-trophy"></i></div>
            <div class="action-content">
                <h4>Milestones</h4>
                <p>Gamification settings</p>
            </div>
        </a>
        <a href="counselor_assignments.php" class="action-card">
            <div class="action-icon" style="background: #3498db;"><i class="fas fa-user-tie"></i></div>
            <div class="action-content">
                <h4>Counselor Assignments</h4>
                <p>Assign counselors</p>
            </div>
        </a>
    </div>
</div>

<?php if (!empty($pending_docs)): ?>
<div class="dashboard-card mt-4">
    <div class="card-header">
        <h3><i class="fas fa-clock"></i> Pending Documents for Moderation</h3>
    </div>
    <div class="scrollable-table">
        <table class="table">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Uploader</th>
                    <th>Uploaded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_docs as $doc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($doc['filename'], 0, 30)) . '...'; ?></td>
                        <td><?php echo htmlspecialchars($doc['username']); ?></td>
                        <td><?php echo Helpers::formatDate($doc['created_at']); ?></td>
                        <td>
                            <a href="attachments_moderation.php?action=view&id=<?php echo $doc['attachment_id']; ?>" 
                               class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <a href="attachments_moderation.php" class="btn btn-primary btn-sm">
            <i class="fas fa-file-check"></i> Go to Moderation Panel
        </a>
    </div>
</div>
<?php endif; ?>

<style>
/* Global card and table styles */
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
    justify-content: space-between;
    margin-bottom: 1rem;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
}
.card-footer {
    margin-top: 1rem;
    text-align: right;
}

/* Stats grid: exactly 4 columns on large screens */
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
.stat-content small {
    color: #95a5a6;
    font-size: 0.7rem;
}

/* Scrollable tables */
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

.role-badge {
    background: #e9ecef;
    color: #2c3e50;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 500;
}
.status-badge {
    display: inline-block;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}
.status-draft { background: #6c757d; }
.status-submitted { background: #007bff; }
.status-under_review { background: #17a2b8; }
.status-accepted { background: #28a745; }
.status-rejected { background: #dc3545; }

/* Quick actions grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
}
.action-card {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background: white;
    border-radius: 14px;
    border: 1px solid #e9ecef;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
}
.action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.05);
    border-color: #b0c4de;
}
.action-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    margin-right: 0.75rem;
    flex-shrink: 0;
}
.action-content h4 {
    margin: 0 0 0.1rem 0;
    color: #2c3e50;
    font-size: 0.9rem;
    font-weight: 600;
}
.action-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.7rem;
}

/* Dashboard grid for two columns */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.btn-sm {
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    border-radius: 30px;
}
.btn-outline-primary {
    border: 1px solid #3498db;
    color: #3498db;
    background: transparent;
}
.btn-outline-primary:hover {
    background: #3498db;
    color: white;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    .quick-actions-grid {
        grid-template-columns: 1fr 1fr;
    }
}
.mt-4 { margin-top: 2rem; }
</style>

<?php require_once '../../app/templates/footer.php'; ?>