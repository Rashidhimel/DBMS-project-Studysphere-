<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "User Management";
require_once '../../app/templates/header.php';

$auth = new Auth();
$current_user = $auth->getCurrentUser();
$db = new Database();

$success = '';
$error = '';

// Handle hard delete using the new function
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id_to_delete = intval($_GET['delete']);
    
    if ($user_id_to_delete == $current_user['user_id']) {
        $error = 'You cannot delete your own account!';
    } else {
        $result = $db->hardDeleteUser($user_id_to_delete);
        
        if ($result) {
            $success = 'User account has been permanently deleted from the database.';
            // Refresh user list after deletion
            $users = $db->fetchAll("
                SELECT u.*, r.name as role_name, 
                       COUNT(DISTINCT a.application_id) as application_count,
                       (SELECT COUNT(*) FROM students s WHERE s.user_id = u.user_id) as is_student
                FROM users u
                JOIN roles r ON u.default_role_id = r.role_id
                LEFT JOIN students st ON u.user_id = st.user_id
                LEFT JOIN applications a ON st.student_id = a.student_id
                WHERE u.deleted_at IS NULL
                GROUP BY u.user_id
                ORDER BY u.created_at DESC
            ");
        } else {
            $error = 'Failed to delete user. Please check error logs.';
        }
    }
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle']) && isset($_GET['status'])) {
    $user_id = intval($_GET['toggle']);
    $status = ($_GET['status'] == 'enable') ? 0 : 1;
    
    if ($user_id == $current_user['user_id']) {
        $error = 'You cannot disable your own account!';
    } else {
        $result = $db->update('users', 
            ['is_disabled' => $status],
            "user_id = $user_id"
        );
        
        if ($result) {
            $action = $status ? 'disabled' : 'enabled';
            $success = "User account has been $action.";
        }
    }
}

if (isset($_GET['verify']) && is_numeric($_GET['verify'])) {
    $user_id = intval($_GET['verify']);
    
    $result = $db->update('users', 
        ['is_verified' => 1],
        "user_id = $user_id"
    );
    
    if ($result) {
        $success = "User account has been verified.";
    }
}

// Fetch users
$users = $db->fetchAll("
    SELECT u.*, r.name as role_name, 
           COUNT(DISTINCT a.application_id) as application_count,
           (SELECT COUNT(*) FROM students s WHERE s.user_id = u.user_id) as is_student
    FROM users u
    JOIN roles r ON u.default_role_id = r.role_id
    LEFT JOIN students st ON u.user_id = st.user_id
    LEFT JOIN applications a ON st.student_id = a.student_id
    WHERE u.deleted_at IS NULL
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");

// Compute quick stats
$total_users = count($users);
$active_users = count(array_filter($users, fn($u) => !$u['is_disabled']));
$verified_users = count(array_filter($users, fn($u) => $u['is_verified'] && !$u['is_disabled']));
$students_count = count(array_filter($users, fn($u) => $u['is_student']));
$admins_count = count(array_filter($users, fn($u) => in_array($u['role_name'], ['admin','super_admin'])));
$counselors_count = count(array_filter($users, fn($u) => $u['role_name'] == 'counselor'));

$roles = $db->fetchAll("SELECT * FROM roles ORDER BY name");
?>

<div class="page-header">
    <h1><i class="fas fa-users-cog"></i> User Management</h1>
    <p class="text-muted">Manage all user accounts in the system</p>
    <a href="/studysphere/public/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_users; ?></h3>
            <p>Total Users</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $active_users; ?></h3>
            <p>Active</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $students_count; ?></h3>
            <p>Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $admins_count; ?></h3>
            <p>Admins</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
            <i class="fas fa-user-friends"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counselors_count; ?></h3>
            <p>Counselors</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #1abc9c, #16a085);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $verified_users; ?></h3>
            <p>Verified</p>
        </div>
    </div>
</div>

<!-- Users Table Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> All Users (<?php echo $total_users; ?>)</h3>
    </div>
    <div class="card-body p-0">
        <div class="scrollable-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Applications</th>
                        <th>Joined</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <?php if ($user['user_id'] == $current_user['user_id']): ?>
                                    <span class="badge-you">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role_name']; ?>">
                                    <?php echo ucfirst($user['role_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['is_disabled']): ?>
                                    <span class="status-badge status-disabled">Disabled</span>
                                <?php elseif ($user['is_verified']): ?>
                                    <span class="status-badge status-verified">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-unverified">Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['is_student'] ? $user['application_count'] : '—'; ?></td>
                            <td><?php echo Helpers::formatDate($user['created_at']); ?></td>
                            <td>
                                <?php echo $user['last_login_at'] ? Helpers::formatDate($user['last_login_at'], 'M d, Y') : 'Never'; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="user_edit.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn-icon-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($user['user_id'] != $current_user['user_id']): ?>
                                        <?php if ($user['is_disabled']): ?>
                                            <a href="?toggle=<?php echo $user['user_id']; ?>&status=enable" 
                                               class="btn-icon-sm btn-success"
                                               onclick="return confirm('Enable this account?')"
                                               title="Enable">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle=<?php echo $user['user_id']; ?>&status=disable" 
                                               class="btn-icon-sm btn-warning"
                                               onclick="return confirm('Disable this account?')"
                                               title="Disable">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!$user['is_verified']): ?>
                                            <a href="?verify=<?php echo $user['user_id']; ?>" 
                                               class="btn-icon-sm btn-info"
                                               onclick="return confirm('Verify this account?')"
                                               title="Verify">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?delete=<?php echo $user['user_id']; ?>" 
                                           class="btn-icon-sm btn-danger"
                                           onclick="return confirm('⚠️ WARNING: This will PERMANENTLY DELETE this user account and ALL associated data (applications, documents, files, etc.) from the database. This action CANNOT BE UNDONE and the email can be used for new registration. Are you ABSOLUTELY SURE?')"
                                           title="Permanently Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Page header */
.page-header {
    margin-bottom: 2rem;
}
.page-header h1 {
    margin-bottom: 0.25rem;
}

/* Stats grid (6 cards) */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
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

/* Card */
.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    overflow: hidden;
    margin-bottom: 2rem;
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
.card-body.p-0 {
    padding: 0;
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
    padding: 0.75rem 0.5rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
    z-index: 2;
}
.table td {
    padding: 0.5rem 0.5rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}
.table tr:hover td {
    background: #f8fafc;
}

/* Badges */
.badge-you {
    background: #3498db;
    color: white;
    padding: 0.1rem 0.4rem;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 500;
    margin-left: 0.3rem;
    display: inline-block;
}
.role-badge {
    display: inline-block;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
}
.role-student { background: #d4edda; color: #155724; }
.role-admin { background: #f8d7da; color: #721c24; }
.role-counselor { background: #fff3cd; color: #856404; }
.role-super_admin { background: #d1ecf1; color: #0c5460; }

.status-badge {
    display: inline-block;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
}
.status-disabled { background: #e74c3c; color: white; }
.status-verified { background: #27ae60; color: white; }
.status-unverified { background: #f39c12; color: white; }

/* Action buttons - compact icon only */
.action-buttons {
    display: flex;
    gap: 0.2rem;
    flex-wrap: wrap;
}
.btn-icon-sm {
    width: 28px;
    height: 28px;
    border-radius: 6px;
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
.btn-icon-sm.btn-primary { background: #3498db; }
.btn-icon-sm.btn-primary:hover { background: #2980b9; }
.btn-icon-sm.btn-success { background: #27ae60; }
.btn-icon-sm.btn-success:hover { background: #219653; }
.btn-icon-sm.btn-warning { background: #f39c12; }
.btn-icon-sm.btn-warning:hover { background: #e67e22; }
.btn-icon-sm.btn-info { background: #3498db; }
.btn-icon-sm.btn-info:hover { background: #2980b9; }
.btn-icon-sm.btn-danger { background: #e74c3c; }
.btn-icon-sm.btn-danger:hover { background: #c0392b; }

/* Alerts */
.alert {
    border-radius: 12px;
    border: none;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.5rem;
}
.alert-success { background: #d4edda; color: #155724; }
.alert-error { background: #f8d7da; color: #721c24; }
.alert .close {
    line-height: 1;
    color: inherit;
    opacity: 0.7;
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
    .scrollable-table {
        max-height: 400px;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>