<?php
require_once '../../app/includes/init.php';
requireCounselor();

$page_title = "Counselor Dashboard";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

$students = $auth->getAssignedStudents($user['user_id']);
$upcoming_sessions = $auth->getCounselorSessions($user['user_id']);

// Counts
$total_students = count($students);
$pending_essays = $db->fetchOne("
    SELECT COUNT(*) as count FROM essays e
    JOIN students s ON e.student_id = s.student_id
    JOIN counselor_assignments ca ON s.student_id = ca.student_id
    WHERE ca.counselor_id = ? AND e.status = 'submitted'
", [$user['user_id']])['count'];

$pending_tasks = $db->fetchOne("
    SELECT COUNT(*) as count FROM student_todos st
    JOIN students s ON st.student_id = s.student_id
    JOIN counselor_assignments ca ON s.student_id = ca.student_id
    WHERE ca.counselor_id = ? AND st.completed = 0
", [$user['user_id']])['count'];

$unread_messages = $db->fetchOne("
    SELECT COUNT(*) as count FROM messages m
    WHERE m.receiver_id = ? AND m.is_read = 0
", [$user['user_id']])['count'];
?>

<div class="page-header">
    <h1><i class="fas fa-chalkboard-teacher"></i> Counselor Dashboard</h1>
    <p class="text-muted">Welcome back, <?php echo htmlspecialchars($user['first_name'] ?: $user['username']); ?></p>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_students; ?></h3>
            <p>Assigned Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo count($upcoming_sessions); ?></h3>
            <p>Upcoming Sessions</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
            <i class="fas fa-pen"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $pending_essays; ?></h3>
            <p>Pending Essay Reviews</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-tasks"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $pending_tasks; ?></h3>
            <p>Pending Tasks</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $unread_messages; ?></h3>
            <p>Unread Messages</p>
        </div>
    </div>
</div>

<!-- First row: Students & Sessions -->
<div class="dashboard-grid">
    <!-- My Students card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> My Students</h3>
            <?php if (!empty($students) && count($students) > 5): ?>
                <a href="students.php" class="btn btn-outline-primary btn-sm">View All</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <p class="empty-message">No students assigned yet.</p>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Applications</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($students, 0, 5) as $s): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($s['first_name'] ? $s['first_name'] . ' ' . $s['last_name'] : $s['username']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo $s['application_count']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="students.php?view=<?php echo $s['student_id']; ?>" class="btn-icon-sm btn-info" title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="messages.php?to=<?php echo $s['user_id']; ?>" class="btn-icon-sm btn-primary" title="Send Message">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($students) && count($students) > 5): ?>
            <div class="card-footer">
                <a href="students.php" class="btn btn-outline-secondary btn-sm">View All <?php echo count($students); ?> Students</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Sessions card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h3>
            <a href="sessions.php" class="btn btn-outline-primary btn-sm">Manage</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($upcoming_sessions)): ?>
                <p class="empty-message">No upcoming sessions scheduled.</p>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($upcoming_sessions, 0, 5) as $sess): 
                                $student_name = $sess['first_name'] ? $sess['first_name'] . ' ' . $sess['last_name'] : $sess['student_username'];
                            ?>
                                <tr>
                                    <td><?php echo Helpers::formatDate($sess['scheduled_at'], 'M j, Y g:i A'); ?></td>
                                    <td><?php echo htmlspecialchars($student_name); ?></td>
                                    <td><?php echo ucfirst($sess['session_type']); ?></td>
                                    <td>
                                        <?php if ($sess['is_booked']): ?>
                                            <span class="badge badge-success">Booked</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="sessions.php?edit=<?php echo $sess['session_id']; ?>" class="btn-icon-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($upcoming_sessions) && count($upcoming_sessions) > 5): ?>
            <div class="card-footer">
                <a href="sessions.php" class="btn btn-outline-secondary btn-sm">View All Sessions</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Second row: Pending Essays & Recent Tasks -->
<div class="dashboard-grid">
    <!-- Pending Essay Reviews -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-pen"></i> Pending Essay Reviews</h3>
            <a href="essays.php" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
            <?php
            $essays = $db->fetchAll("
                SELECT e.essay_id, e.title, e.submitted_at, 
                       s.student_id, u.username, up.first_name, up.last_name
                FROM essays e
                JOIN students s ON e.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                JOIN counselor_assignments ca ON s.student_id = ca.student_id
                WHERE ca.counselor_id = ? AND e.status = 'submitted'
                ORDER BY e.submitted_at ASC
                LIMIT 5
            ", [$user['user_id']]);
            ?>
            <?php if (empty($essays)): ?>
                <p class="empty-message">No pending essay reviews.</p>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Essay Title</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($essays as $e): 
                                $student_name = $e['first_name'] ? $e['first_name'] . ' ' . $e['last_name'] : $e['username'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student_name); ?></td>
                                    <td><?php echo htmlspecialchars($e['title']); ?></td>
                                    <td><?php echo Helpers::formatDate($e['submitted_at']); ?></td>
                                    <td>
                                        <a href="essays.php?review=<?php echo $e['essay_id']; ?>" class="btn-icon-sm btn-info" title="Review">
                                            <i class="fas fa-pencil-alt"></i>
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

    <!-- Recent Tasks -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> Recent Tasks</h3>
            <a href="todos.php" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
            <?php
            $tasks = $db->fetchAll("
                SELECT st.*, s.student_id, u.username, up.first_name, up.last_name
                FROM student_todos st
                JOIN students s ON st.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                JOIN counselor_assignments ca ON s.student_id = ca.student_id
                WHERE ca.counselor_id = ? AND st.completed = 0
                ORDER BY st.due_date ASC
                LIMIT 5
            ", [$user['user_id']]);
            ?>
            <?php if (empty($tasks)): ?>
                <p class="empty-message">No pending tasks.</p>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Student</th>
                                <th>Due Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): 
                                $student_name = $t['first_name'] ? $t['first_name'] . ' ' . $t['last_name'] : $t['username'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td><?php echo htmlspecialchars($student_name); ?></td>
                                    <td><?php echo $t['due_date'] ? Helpers::formatDate($t['due_date']) : 'No deadline'; ?></td>
                                    <td>
                                        <a href="todos.php?complete=<?php echo $t['todo_id']; ?>" class="btn-icon-sm btn-success" title="Mark Complete">
                                            <i class="fas fa-check"></i>
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
</div>

<style>
/* Reuse styles from other admin pages */
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

/* Dashboard grid (two columns) */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Card styling */
.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-body.p-0 {
    padding: 0;
    flex: 1;
}
.card-footer {
    padding: 0.75rem 1.5rem;
    border-top: 1px solid #eef2f6;
    text-align: right;
}

/* Scrollable tables */
.scrollable-table {
    max-height: 250px;
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
    padding: 0.6rem 0.75rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
    z-index: 2;
    font-size: 0.8rem;
}
.table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
    font-size: 0.9rem;
}
.table tr:hover td {
    background: #f8fafc;
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
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
.btn-icon-sm.btn-info { background: #3498db; }
.btn-icon-sm.btn-info:hover { background: #2980b9; }
.btn-icon-sm.btn-primary { background: #3498db; }
.btn-icon-sm.btn-primary:hover { background: #2980b9; }
.btn-icon-sm.btn-success { background: #27ae60; }
.btn-icon-sm.btn-success:hover { background: #219653; }

/* Badges */
.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

/* Empty message */
.empty-message {
    padding: 2rem;
    text-align: center;
    color: #7f8c8d;
    margin: 0;
}

/* Buttons */
.btn-outline-primary, .btn-outline-secondary, .btn-outline-primary.btn-sm {
    border-radius: 30px;
    padding: 0.3rem 1rem;
    font-size: 0.8rem;
    border: 1px solid;
    background: transparent;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-outline-primary { border-color: #3498db; color: #3498db; }
.btn-outline-primary:hover { background: #3498db; color: white; }
.btn-outline-secondary { border-color: #cbd5e0; color: #4a5568; }
.btn-outline-secondary:hover { background: #e2e8f0; }

/* Responsive */
@media (max-width: 900px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 600px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>