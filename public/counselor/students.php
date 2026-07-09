<?php
require_once '../../app/includes/init.php';
requireCounselor();

$page_title = "My Students";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

$students = $auth->getAssignedStudents($user['user_id']);

// Search/filter for list view
$search = $_GET['search'] ?? '';

// View single student details
$view_student_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$student_details = null;
$student_applications = [];
$student_essays = [];
$student_todos = [];

if ($view_student_id) {
    // Verify this student belongs to this counselor
    $assignment = $db->fetchOne("
        SELECT * FROM counselor_assignments 
        WHERE student_id = ? AND counselor_id = ? AND is_active = 1
    ", [$view_student_id, $user['user_id']]);

    if ($assignment) {
        $student_details = $db->fetchOne("
            SELECT s.*, u.username, u.email, u.is_verified,
                   up.first_name, up.last_name, up.dob, up.gender, up.nationality,
                   up.profile_photo_url, up.bio
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE s.student_id = ?
        ", [$view_student_id]);

        $student_applications = $db->fetchAll("
            SELECT a.*, p.program_name, u2.name as university_name, 
                   st.name as status_name
            FROM applications a
            JOIN programs p ON a.program_id = p.program_id
            JOIN universities u2 ON a.university_id = u2.university_id
            JOIN application_statuses st ON a.application_status_id = st.application_status_id
            WHERE a.student_id = ? AND a.deleted_at IS NULL
            ORDER BY a.applied_at DESC
        ", [$view_student_id]);

        $student_essays = $db->fetchAll("
            SELECT * FROM essays WHERE student_id = ? ORDER BY created_at DESC
        ", [$view_student_id]);

        $student_todos = $db->fetchAll("
            SELECT * FROM student_todos WHERE student_id = ? ORDER BY due_date ASC
        ", [$view_student_id]);
    } else {
        $error = "Student not found or not assigned to you.";
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> My Students</h1>
    <p class="text-muted">View and manage your assigned students</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($view_student_id && $student_details): ?>
    <!-- Student Detail View -->
    <div class="page-header">
        <a href="students.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <!-- Stats Cards for this student -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= count($student_applications) ?></h3>
                <p>Applications</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
                <i class="fas fa-pen"></i>
            </div>
            <div class="stat-content">
                <h3><?= count($student_essays) ?></h3>
                <p>Essays</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= count(array_filter($student_todos, fn($t) => !$t['completed'])) ?></h3>
                <p>Pending Tasks</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= count(array_filter($student_todos, fn($t) => $t['completed'])) ?></h3>
                <p>Completed Tasks</p>
            </div>
        </div>
    </div>

    <!-- Student Profile Card -->
    <div class="card student-profile">
        <div class="card-body">
            <div class="profile-header">
                <div class="avatar">
                    <?php if ($student_details['profile_photo_url']): ?>
                        <img src="<?= htmlspecialchars($student_details['profile_photo_url']) ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?= strtoupper(substr($student_details['first_name'] ?? $student_details['username'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="info">
                    <h2><?= htmlspecialchars($student_details['first_name'] . ' ' . $student_details['last_name'] ?: $student_details['username']) ?></h2>
                    <p><?= $student_details['email'] ?></p>
                    <div class="actions">
                        <a href="messages.php?to=<?= $student_details['user_id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-envelope"></i> Message</a>
                        <a href="todos.php?assign=<?= $view_student_id ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-tasks"></i> Assign Task</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Applications Section -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Applications</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Program</th>
                            <th>University</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($student_applications)): ?>
                            <tr><td colspan="5" class="text-center py-4">No applications yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($student_applications as $app): ?>
                                <tr>
                                    <td><?= htmlspecialchars($app['program_name']) ?></td>
                                    <td><?= htmlspecialchars($app['university_name']) ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?= 
                                            ['Draft'=>'#6c757d','Submitted'=>'#f39c12','Under Review'=>'#17a2b8','Accepted'=>'#28a745','Rejected'=>'#dc3545'][$app['status_name']] ?? '#6c757d' ?>">
                                            <?= $app['status_name'] ?>
                                        </span>
                                    </td>
                                    <td><?= Helpers::formatDate($app['applied_at']) ?></td>
                                    <td>
                                        <a href="/studysphere/public/admin/application_view.php?id=<?= $app['application_id'] ?>" class="btn-icon btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Essays Section -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-pen"></i> Essays</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($student_essays)): ?>
                            <tr><td colspan="4" class="text-center py-4">No essays submitted.</td></tr>
                        <?php else: ?>
                            <?php foreach ($student_essays as $essay): ?>
                                <tr>
                                    <td><?= htmlspecialchars($essay['title']) ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?= 
                                            $essay['status'] == 'submitted' ? '#f39c12' : ($essay['status'] == 'reviewed' ? '#28a745' : '#6c757d') ?>">
                                            <?= ucfirst($essay['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $essay['submitted_at'] ? Helpers::formatDate($essay['submitted_at']) : 'Not submitted' ?></td>
                                    <td>
                                        <a href="essays.php?review=<?= $essay['essay_id'] ?>" class="btn-icon btn-primary" title="Review">
                                            <i class="fas fa-search"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- To-Do Tasks Section -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> To-Do Tasks</h3>
        </div>
        <div class="card-body">
            <?php if (empty($student_todos)): ?>
                <p class="text-muted">No tasks assigned.</p>
            <?php else: ?>
                <div class="todo-list">
                    <?php foreach ($student_todos as $todo): ?>
                        <div class="todo-item">
                            <div class="todo-content">
                                <strong><?= htmlspecialchars($todo['title']) ?></strong>
                                <?php if ($todo['description']): ?>
                                    <br><small><?= nl2br(htmlspecialchars($todo['description'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="todo-meta">
                                <?php if ($todo['due_date']): ?>
                                    <span class="badge <?= strtotime($todo['due_date']) < time() && !$todo['completed'] ? 'badge-danger' : 'badge-secondary' ?>">
                                        <i class="far fa-calendar-alt"></i> <?= Helpers::formatDate($todo['due_date']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($todo['completed']): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- Student List View -->
    <?php
    // Filter students by search if any
    if ($search) {
        $students = array_filter($students, function($s) use ($search) {
            $name = $s['first_name'] ? $s['first_name'] . ' ' . $s['last_name'] : $s['username'];
            return stripos($name, $search) !== false || stripos($s['email'], $search) !== false;
        });
    }
    ?>
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?= count($students) ?></h3>
                <p>Assigned Students</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= array_sum(array_column($students, 'application_count')) ?></h3>
                <p>Total Applications</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
                <i class="fas fa-pen"></i>
            </div>
            <div class="stat-content">
                <h3><?= count($students) ?></h3>
                <p>Active Students</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= count(array_filter($students, fn($s) => $s['application_count'] > 0)) ?></h3>
                <p>With Applications</p>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filter Students</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>" class="form-control">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="students.php" class="btn btn-outline-secondary">Clear</a>
            </form>
        </div>
    </div>

    <!-- Students Table Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Assigned Students (<?= count($students) ?>)</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Applications</th>
                            <th>Assigned On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="5" class="text-center py-4">No students assigned to you yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): 
                                $name = $s['first_name'] ? $s['first_name'] . ' ' . $s['last_name'] : $s['username'];
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($name) ?></strong></td>
                                    <td><?= $s['email'] ?></td>
                                    <td><span class="badge badge-info"><?= $s['application_count'] ?></span></td>
                                    <td><?= Helpers::formatDate($s['assigned_at']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?view=<?= $s['student_id'] ?>" class="btn-icon btn-info" title="View Profile"><i class="fas fa-user"></i></a>
                                            <a href="messages.php?to=<?= $s['user_id'] ?>" class="btn-icon btn-primary" title="Message"><i class="fas fa-envelope"></i></a>
                                            <a href="todos.php?assign=<?= $s['student_id'] ?>" class="btn-icon btn-secondary" title="Assign Task"><i class="fas fa-tasks"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
/* Reuse modern styles from other admin/counselor pages */
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
    min-width: 250px;
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
.badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-info {
    background: #17a2b8;
    color: white;
}
.badge-success {
    background: #28a745;
    color: white;
}
.badge-warning {
    background: #ffc107;
    color: #333;
}
.badge-danger {
    background: #dc3545;
    color: white;
}
.badge-secondary {
    background: #6c757d;
    color: white;
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
.btn-icon.btn-secondary { background: #6c757d; }
.btn-icon:hover {
    opacity: 0.8;
    transform: translateY(-2px);
}

/* Student Profile */
.student-profile .profile-header {
    display: flex;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}
.student-profile .avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    background: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
}
.student-profile .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.student-profile .avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 500;
}
.student-profile .info h2 {
    margin: 0 0 0.25rem 0;
}
.student-profile .info p {
    margin: 0 0 1rem 0;
    color: #6c757d;
}
.student-profile .actions {
    display: flex;
    gap: 0.75rem;
}
.btn-sm {
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
}

.todo-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.todo-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #eef2f6;
}
.todo-content {
    flex: 1;
}
.todo-meta {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form .form-control,
    .filter-form .btn {
        width: 100%;
    }
    .student-profile .profile-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>