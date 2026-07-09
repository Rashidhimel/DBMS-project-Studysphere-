<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "To-Do List";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_task'])) {
        $title = Helpers::sanitize($_POST['title']);
        $description = Helpers::sanitize($_POST['description']);
        $due_date = $_POST['due_date'];
        $priority = $_POST['priority'];
        $category = isset($_POST['category']) ? $_POST['category'] : '';
        $linked_application = $_POST['linked_application'] ?: null;
        
        $db->insert('student_todos', [
    'student_id' => $student_id,
    'title' => $title,
    'description' => $description,
    'due_date' => $due_date ?: null,
    'priority' => $priority,
    'category' => $category,
    'completed' => 0,
    'created_at' => date('Y-m-d H:i:s'),
    'created_by' => $user['user_id']
]);
        
        $success = "Task added successfully!";
    }
    
    if (isset($_POST['complete_task'])) {
        $todo_id = intval($_POST['todo_id']);
        
        $db->update('student_todos', [
            'completed' => 1,
            'completed_at' => date('Y-m-d H:i:s')
        ], "todo_id = $todo_id AND student_id = $student_id");
        
        $success = "Task marked as complete!";
    }
    
    if (isset($_POST['delete_task'])) {
        $todo_id = intval($_POST['todo_id']);
        
        $db->query("DELETE FROM student_todos WHERE todo_id = ? AND student_id = ?", 
                   [$todo_id, $student_id]);
        
        $success = "Task deleted successfully!";
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $todo_id = intval($_GET['id']);
    
    switch ($_GET['action']) {
        case 'complete':
            $db->update('student_todos', [
                'completed' => 1,
                'completed_at' => date('Y-m-d H:i:s')
            ], "todo_id = $todo_id AND student_id = $student_id");
            $success = "Task completed!";
            break;
            
        case 'delete':
            $db->query("DELETE FROM student_todos WHERE todo_id = ? AND student_id = ?", 
                       [$todo_id, $student_id]);
            $success = "Task deleted!";
            break;
    }
}

$tasks = $db->fetchAll("
    SELECT * FROM student_todos 
    WHERE student_id = ? 
    ORDER BY 
        CASE priority 
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
            ELSE 4
        END,
        due_date IS NULL,
        due_date,
        created_at DESC
", [$student_id]);

$applications = $db->fetchAll("
    SELECT a.application_id, p.program_name, u.name as university_name
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    WHERE a.student_id = ? AND a.deleted_at IS NULL
    ORDER BY a.applied_at DESC
", [$student_id]);

$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN due_date < CURDATE() AND completed = 0 THEN 1 ELSE 0 END) as overdue_tasks,
        SUM(CASE WHEN due_date = CURDATE() AND completed = 0 THEN 1 ELSE 0 END) as due_today
    FROM student_todos 
    WHERE student_id = ?
", [$student_id]);
?>

<div class="page-header">
    <h1><i class="fas fa-tasks"></i> To-Do List</h1>
    <p>Stay organized with your application tasks and deadlines</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #4CAF50;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['completed_tasks'] ?? 0; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #2196F3;">
            <i class="fas fa-list-ul"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_tasks'] ?? 0; ?></h3>
            <p>Total Tasks</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #FF9800;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['due_today'] ?? 0; ?></h3>
            <p>Due Today</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #F44336;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['overdue_tasks'] ?? 0; ?></h3>
            <p>Overdue</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>Add New Task</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="title">Task Title *</label>
                <input type="text" id="title" name="title" class="form-control" required 
                       placeholder="E.g., Submit application for Harvard">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" 
                          rows="3" placeholder="Add details about this task..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">General</option>
                    <option value="application">Application</option>
                    <option value="document">Document</option>
                    <option value="test">Test Preparation</option>
                    <option value="recommendation">Recommendation</option>
                    <option value="scholarship">Scholarship</option>
                    <option value="visa">Visa</option>
                    <option value="housing">Housing</option>
                    <option value="travel">Travel</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="linked_application">Link to Application (Optional)</label>
                <select id="linked_application" name="linked_application" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($applications as $app): ?>
                        <option value="<?php echo $app['application_id']; ?>">
                            <?php echo $app['program_name']; ?> - <?php echo $app['university_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="add_task" class="btn btn-primary btn-block">
                <i class="fas fa-plus"></i> Add Task
            </button>
        </form>
    </div>
    
    <div class="dashboard-card">
        <h3>Quick Actions</h3>
        <div class="quick-tasks">
            <button class="btn btn-secondary btn-block mb-1" onclick="addQuickTask('Request recommendation letter')">
                <i class="fas fa-envelope"></i> Request Recommendation
            </button>
            <button class="btn btn-secondary btn-block mb-1" onclick="addQuickTask('Upload transcripts')">
                <i class="fas fa-upload"></i> Upload Documents
            </button>
            <button class="btn btn-secondary btn-block mb-1" onclick="addQuickTask('Prepare for interview')">
                <i class="fas fa-user-tie"></i> Interview Prep
            </button>
            <button class="btn btn-secondary btn-block mb-1" onclick="addQuickTask('Check application status')">
                <i class="fas fa-sync-alt"></i> Check Status
            </button>
            <button class="btn btn-secondary btn-block" onclick="addQuickTask('Research housing options')">
                <i class="fas fa-home"></i> Housing Research
            </button>
        </div>
        
        <h3 class="mt-3">Upcoming Deadlines</h3>
        <?php 
        $upcoming_tasks = array_filter($tasks, function($task) {
            return !$task['completed'] && $task['due_date'] && 
                   strtotime($task['due_date']) >= strtotime('today');
        });
        
        usort($upcoming_tasks, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']);
        });
        
        $upcoming_tasks = array_slice($upcoming_tasks, 0, 5);
        ?>
        
        <?php if (empty($upcoming_tasks)): ?>
            <p class="text-muted">No upcoming deadlines</p>
        <?php else: ?>
            <ul class="deadline-list">
                <?php foreach ($upcoming_tasks as $task): ?>
                    <li>
                        <div class="task-priority priority-<?php echo $task['priority']; ?>"></div>
                        <div>
                            <strong><?php echo htmlspecialchars($task['title']); ?></strong><br>
                            <small><?php echo Helpers::formatDate($task['due_date'], 'M d, Y'); ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>My Tasks</h3>
        <div class="view-toggle">
            <button class="btn btn-small active" data-view="all">All</button>
            <button class="btn btn-small" data-view="pending">Pending</button>
            <button class="btn btn-small" data-view="completed">Completed</button>
        </div>
    </div>
    
    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <i class="fas fa-tasks fa-3x"></i>
            <h4>No tasks yet</h4>
            <p>Add your first task to get started!</p>
        </div>
    <?php else: ?>
        <div class="tasks-container">
            <?php foreach ($tasks as $task): ?>
                <div class="task-item <?php echo $task['completed'] ? 'completed' : ''; ?>" 
                     data-priority="<?php echo $task['priority']; ?>"
                     data-category="<?php echo $task['category'] ?? 'general'; ?>">
                    <div class="task-checkbox">
                        <input type="checkbox" id="task-<?php echo $task['todo_id']; ?>" 
                               <?php echo $task['completed'] ? 'checked' : ''; ?>
                               onchange="toggleTask(<?php echo $task['todo_id']; ?>, this.checked)">
                    </div>
                    
                    <div class="task-content">
                        <div class="task-header">
                            <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                            <div class="task-meta">
                                <?php if ($task['due_date']): ?>
                                    <span class="task-due <?php echo isOverdue($task['due_date'], $task['completed']) ? 'overdue' : ''; ?>">
                                        <i class="far fa-calendar"></i>
                                        <?php echo Helpers::formatDate($task['due_date'], 'M d, Y'); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="task-priority-badge priority-<?php echo $task['priority']; ?>">
                                    <?php echo ucfirst($task['priority']); ?>
                                </span>
                                <?php if ($task['category']): ?>
                                    <span class="task-category"><?php echo ucfirst($task['category']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($task['description']): ?>
                            <div class="task-description">
                                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="task-footer">
                            <small class="text-muted">
                                Created: <?php echo Helpers::formatDate($task['created_at'], 'M d, Y'); ?>
                                <?php if ($task['completed_at']): ?>
                                    • Completed: <?php echo Helpers::formatDate($task['completed_at'], 'M d, Y'); ?>
                                <?php endif; ?>
                            </small>
                            <div class="task-actions">
                                <?php if (!$task['completed']): ?>
                                    <a href="todos.php?action=complete&id=<?php echo $task['todo_id']; ?>" 
                                       class="btn btn-small btn-success" title="Mark Complete">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="todos.php?action=delete&id=<?php echo $task['todo_id']; ?>" 
                                   class="btn btn-small btn-danger" 
                                   title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this task?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-toggle .btn');
    const taskItems = document.querySelectorAll('.task-item');
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            viewButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const view = this.dataset.view;
            
            taskItems.forEach(item => {
                switch(view) {
                    case 'pending':
                        item.style.display = item.classList.contains('completed') ? 'none' : '';
                        break;
                    case 'completed':
                        item.style.display = item.classList.contains('completed') ? '' : 'none';
                        break;
                    default:
                        item.style.display = '';
                }
            });
        });
    });
    
    const priorityFilter = document.getElementById('priorityFilter');
    if (priorityFilter) {
        priorityFilter.addEventListener('change', function() {
            const priority = this.value;
            
            taskItems.forEach(item => {
                if (!priority || item.dataset.priority === priority) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});

function addQuickTask(title) {
    document.getElementById('title').value = title;
    document.getElementById('title').focus();
}

function toggleTask(taskId, isComplete) {
    if (isComplete) {
        window.location.href = `todos.php?action=complete&id=${taskId}`;
    }
}

function isOverdue(dueDate, completed) {
    if (completed) return false;
    const today = new Date();
    const due = new Date(dueDate);
    return due < today;
}
</script>

<style>
.quick-tasks .btn {
    text-align: left;
    padding: 0.5rem;
}

.deadline-list {
    list-style: none;
    padding: 0;
}

.deadline-list li {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}

.deadline-list li:last-child {
    border-bottom: none;
}

.task-priority {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 10px;
}

.priority-high { background: #F44336; }
.priority-medium { background: #FF9800; }
.priority-low { background: #4CAF50; }

.tasks-container {
    max-height: 500px;
    overflow-y: auto;
}

.task-item {
    display: flex;
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 0.75rem;
    background: white;
    transition: all 0.2s;
}

.task-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.task-item.completed {
    opacity: 0.7;
    background: #f8f9fa;
}

.task-item.completed .task-content h4 {
    text-decoration: line-through;
    color: #666;
}

.task-checkbox {
    margin-right: 1rem;
    padding-top: 0.25rem;
}

.task-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.task-content {
    flex: 1;
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.task-header h4 {
    margin: 0;
    flex: 1;
    font-size: 1rem;
}

.task-meta {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.task-due {
    font-size: 0.875rem;
    color: #666;
}

.task-due.overdue {
    color: #F44336;
    font-weight: bold;
}

.task-priority-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.priority-high { background: #f8d7da; color: #721c24; }
.priority-medium { background: #fff3cd; color: #856404; }
.priority-low { background: #d4edda; color: #155724; }

.task-category {
    padding: 2px 8px;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 12px;
    font-size: 0.75rem;
}

.task-description {
    margin: 0.5rem 0;
    color: #666;
    font-size: 0.875rem;
    line-height: 1.5;
}

.task-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #f1f1f1;
}

.task-actions {
    display: flex;
    gap: 0.25rem;
}

.task-actions .btn-small {
    padding: 4px 8px;
    font-size: 0.75rem;
}

.view-toggle {
    display: flex;
    gap: 0.25rem;
}

.view-toggle .btn {
    padding: 4px 12px;
    font-size: 0.875rem;
}

.view-toggle .btn.active {
    background: #3498db;
    color: white;
}
</style>

<?php
function isOverdue($dueDate, $completed) {
    if ($completed) return false;
    if (!$dueDate) return false;
    return strtotime($dueDate) < strtotime('today');
}
require_once '../../app/templates/footer.php';
?>