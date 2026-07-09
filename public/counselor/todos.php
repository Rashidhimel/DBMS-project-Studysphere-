<?php
require_once '../../app/includes/init.php';
requireCounselor();

$page_title = "Student Tasks";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

// Get all assigned students for dropdown
$students = $auth->getAssignedStudents($user['user_id']);

// Handle add/edit/delete/completion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_todo'])) {
        $student_id = intval($_POST['student_id']);
        $title = Helpers::sanitize($_POST['title']);
        $description = Helpers::sanitize($_POST['description'] ?? '');
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $priority = Helpers::sanitize($_POST['priority']);

        // Verify student belongs to this counselor
        $assignment = $db->fetchOne("
            SELECT * FROM counselor_assignments 
            WHERE student_id = ? AND counselor_id = ? AND is_active = 1
        ", [$student_id, $user['user_id']]);

        if ($assignment) {
            $db->insert('student_todos', [
                'student_id' => $student_id,
                'title' => $title,
                'description' => $description,
                'due_date' => $due_date,
                'priority' => $priority,
                'created_by' => $user['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $success = "Task added.";
        } else {
            $error = "Student not assigned to you.";
        }
    } elseif (isset($_POST['update_todo'])) {
        $todo_id = intval($_POST['todo_id']);
        $title = Helpers::sanitize($_POST['title']);
        $description = Helpers::sanitize($_POST['description'] ?? '');
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $priority = Helpers::sanitize($_POST['priority']);

        // Verify todo belongs to a student assigned to this counselor
        $todo = $db->fetchOne("
            SELECT st.*, ca.counselor_id 
            FROM student_todos st
            JOIN counselor_assignments ca ON st.student_id = ca.student_id
            WHERE st.todo_id = ? AND ca.counselor_id = ?
        ", [$todo_id, $user['user_id']]);

        if ($todo) {
            $db->update('student_todos', [
                'title' => $title,
                'description' => $description,
                'due_date' => $due_date,
                'priority' => $priority
            ], "todo_id = $todo_id");
            $success = "Task updated.";
        } else {
            $error = "Task not found or not authorized.";
        }
    } elseif (isset($_POST['delete_todo'])) {
        $todo_id = intval($_POST['todo_id']);
        $todo = $db->fetchOne("
            SELECT st.*, ca.counselor_id 
            FROM student_todos st
            JOIN counselor_assignments ca ON st.student_id = ca.student_id
            WHERE st.todo_id = ? AND ca.counselor_id = ?
        ", [$todo_id, $user['user_id']]);
        if ($todo) {
            $db->query("DELETE FROM student_todos WHERE todo_id = ?", [$todo_id]);
            $success = "Task deleted.";
        } else {
            $error = "Task not found.";
        }
    }
}

// Handle mark complete (via GET link)
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $todo_id = intval($_GET['complete']);
    $todo = $db->fetchOne("
        SELECT st.*, ca.counselor_id 
        FROM student_todos st
        JOIN counselor_assignments ca ON st.student_id = ca.student_id
        WHERE st.todo_id = ? AND ca.counselor_id = ?
    ", [$todo_id, $user['user_id']]);
    if ($todo) {
        $db->update('student_todos', [
            'completed' => 1,
            'completed_at' => date('Y-m-d H:i:s')
        ], "todo_id = $todo_id");
        $success = "Task marked as complete.";
    } else {
        $error = "Task not found.";
    }
}

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? ''; // 'pending' or 'completed'
$priority_filter = $_GET['priority'] ?? '';

// Get all todos for assigned students with filters
$sql = "
    SELECT st.*, 
           s.student_id, u.username, up.first_name, up.last_name
    FROM student_todos st
    JOIN students s ON st.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    JOIN counselor_assignments ca ON s.student_id = ca.student_id AND ca.is_active = 1
    WHERE ca.counselor_id = ?
";
$params = [$user['user_id']];

if ($status_filter == 'pending') {
    $sql .= " AND st.completed = 0";
} elseif ($status_filter == 'completed') {
    $sql .= " AND st.completed = 1";
}
if ($priority_filter) {
    $sql .= " AND st.priority = ?";
    $params[] = $priority_filter;
}
if ($search) {
    $sql .= " AND (st.title LIKE ? OR st.description LIKE ? OR u.username LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

$sql .= " GROUP BY st.todo_id ORDER BY st.completed ASC, st.due_date ASC, st.created_at DESC";
$todos = $db->fetchAll($sql, $params);

// Compute stats
$total_tasks = count($todos);
$pending = count(array_filter($todos, fn($t) => !$t['completed']));
$completed = count(array_filter($todos, fn($t) => $t['completed']));
$overdue = count(array_filter($todos, fn($t) => !$t['completed'] && $t['due_date'] && strtotime($t['due_date']) < time()));

// For editing, get single todo
$edit_todo = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $todo_id = intval($_GET['edit']);
    $edit_todo = $db->fetchOne("
        SELECT st.*, ca.counselor_id 
        FROM student_todos st
        JOIN counselor_assignments ca ON st.student_id = ca.student_id
        WHERE st.todo_id = ? AND ca.counselor_id = ?
    ", [$todo_id, $user['user_id']]);
    if (!$edit_todo) {
        $error = "Task not found.";
    }
}

// Pre-select student for new task
$pre_select_student = isset($_GET['assign']) ? intval($_GET['assign']) : 0;
?>

<div class="page-header">
    <h1><i class="fas fa-tasks"></i> Student Tasks</h1>
    <p class="text-muted">Manage tasks for your assigned students</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-error alert-dismissible fade show" role="alert">
        <?= $error ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-tasks"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total_tasks ?></h3>
            <p>Total Tasks</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?= $pending ?></h3>
            <p>Pending</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $completed ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $overdue ?></h3>
            <p>Overdue</p>
        </div>
    </div>
</div>

<!-- Add/Edit Form Card -->
<div class="card form-card">
    <div class="card-header">
        <h3><?= $edit_todo ? '<i class="fas fa-edit"></i> Edit Task' : '<i class="fas fa-plus"></i> Assign New Task' ?></h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php if ($edit_todo): ?>
                <input type="hidden" name="todo_id" value="<?= $edit_todo['todo_id'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="student_id">Student</label>
                <select name="student_id" id="student_id" class="form-control" required <?= $edit_todo ? 'disabled' : '' ?>>
                    <option value="">Select Student</option>
                    <?php foreach ($students as $s): 
                        $student_name = $s['first_name'] ? $s['first_name'] . ' ' . $s['last_name'] : $s['username'];
                    ?>
                        <option value="<?= $s['student_id'] ?>" 
                            <?php 
                            if ($edit_todo && $edit_todo['student_id'] == $s['student_id']) echo 'selected';
                            elseif (!$edit_todo && $pre_select_student == $s['student_id']) echo 'selected';
                            ?>>
                            <?= htmlspecialchars($student_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($edit_todo): ?>
                    <input type="hidden" name="student_id" value="<?= $edit_todo['student_id'] ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="title">Task Title</label>
                <input type="text" name="title" id="title" class="form-control" required value="<?= $edit_todo ? htmlspecialchars($edit_todo['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label for="description">Description (optional)</label>
                <textarea name="description" id="description" class="form-control" rows="2"><?= $edit_todo ? htmlspecialchars($edit_todo['description']) : '' ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="due_date">Due Date (optional)</label>
                    <input type="date" name="due_date" id="due_date" class="form-control" value="<?= $edit_todo ? $edit_todo['due_date'] : '' ?>">
                </div>
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select name="priority" id="priority" class="form-control" required>
                        <option value="low" <?= ($edit_todo && $edit_todo['priority'] == 'low') ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($edit_todo && $edit_todo['priority'] == 'medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($edit_todo && $edit_todo['priority'] == 'high') ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="<?= $edit_todo ? 'update_todo' : 'add_todo' ?>" class="btn btn-primary">
                    <?= $edit_todo ? '<i class="fas fa-save"></i> Update Task' : '<i class="fas fa-plus"></i> Assign Task' ?>
                </button>
                <?php if ($edit_todo): ?>
                    <a href="todos.php" class="btn btn-outline-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Filter Bar -->
<div class="card filter-card">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> Filter Tasks</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by task, student..." value="<?= htmlspecialchars($search) ?>" class="form-control">
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
            <select name="priority" class="form-control">
                <option value="">All Priorities</option>
                <option value="low" <?= $priority_filter == 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $priority_filter == 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $priority_filter == 'high' ? 'selected' : '' ?>>High</option>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="todos.php" class="btn btn-outline-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Tasks List Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Tasks (<?= count($todos) ?>)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Task</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todos)): ?>
                        <tr><td colspan="6" class="text-center py-4">No tasks found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($todos as $t): 
                            $student_name = $t['first_name'] ? $t['first_name'] . ' ' . $t['last_name'] : $t['username'];
                            $priority_colors = [
                                'low' => '#28a745',
                                'medium' => '#ffc107',
                                'high' => '#dc3545'
                            ];
                            $is_overdue = !$t['completed'] && $t['due_date'] && strtotime($t['due_date']) < time();
                        ?>
                            <tr class="<?= $is_overdue ? 'table-danger' : '' ?>">
                                <td><strong><?= htmlspecialchars($student_name) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($t['title']) ?></strong>
                                    <?php if ($t['description']): ?>
                                        <br><small><?= nl2br(htmlspecialchars(substr($t['description'], 0, 50))) ?>…</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($t['due_date']): ?>
                                        <?= Helpers::formatDate($t['due_date']) ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge badge-danger">Overdue</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        No deadline
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: <?= $priority_colors[$t['priority']] ?>; color: white;">
                                        <?= ucfirst($t['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($t['completed']): ?>
                                        <span class="badge badge-secondary">Completed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (!$t['completed']): ?>
                                            <a href="?complete=<?= $t['todo_id'] ?>" class="btn-icon btn-success" title="Complete" onclick="return confirm('Mark as complete?')"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        <a href="?edit=<?= $t['todo_id'] ?>" class="btn-icon btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="todo_id" value="<?= $t['todo_id'] ?>">
                                            <button type="submit" name="delete_todo" class="btn-icon btn-danger" title="Delete" onclick="return confirm('Delete this task?')"><i class="fas fa-trash"></i></button>
                                        </form>
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

<style>
/* Reuse modern styles from other pages */
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

.form-card {
    margin-bottom: 2rem;
}
.form-row {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.form-group {
    flex: 1;
    min-width: 200px;
    margin-bottom: 1rem;
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
    margin-top: 1rem;
}
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    border-radius: 30px;
    padding: 0.6rem 1.5rem;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52,152,219,0.3);
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
.table-danger {
    background: #fef9fa;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-warning {
    background: #ffc107;
    color: #333;
}
.badge-secondary {
    background: #6c757d;
    color: white;
}
.badge-danger {
    background: #dc3545;
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
.btn-icon.btn-success { background: #28a745; }
.btn-icon.btn-primary { background: #3498db; }
.btn-icon.btn-danger { background: #dc3545; }
.btn-icon:hover {
    opacity: 0.8;
    transform: translateY(-2px);
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
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>