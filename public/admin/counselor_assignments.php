<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Counselor Assignments";
require_once '../../app/templates/header.php';

$db = new Database();

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_counselor'])) {
    $student_id = intval($_POST['student_id']);
    $counselor_id = intval($_POST['counselor_id']);

    // End any existing active assignment
    $db->update('counselor_assignments',
        ['is_active' => 0, 'ended_at' => date('Y-m-d H:i:s')],
        "student_id = $student_id AND is_active = 1"
    );

    // Create new assignment
    $data = [
        'student_id' => $student_id,
        'counselor_id' => $counselor_id,
        'assigned_at' => date('Y-m-d H:i:s'),
        'assigned_by' => $_SESSION['user_id'],
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];

    if ($db->insert('counselor_assignments', $data)) {
        $success = "Counselor assigned successfully.";
    } else {
        $error = "Failed to assign counselor.";
    }
}

// Search/filter
$search = $_GET['search'] ?? '';
$counselor_filter = $_GET['counselor_id'] ?? 0;
$status_filter = $_GET['status'] ?? ''; // 'assigned' or 'unassigned'

// Build query
$sql = "
    SELECT s.student_id, u.user_id, u.username, u.email,
           up.first_name, up.last_name,
           ca.counselor_id as assigned_counselor_id,
           cu.username as counselor_username,
           cup.first_name as counselor_first_name,
           cup.last_name as counselor_last_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    LEFT JOIN counselor_assignments ca ON s.student_id = ca.student_id AND ca.is_active = 1
    LEFT JOIN users cu ON ca.counselor_id = cu.user_id
    LEFT JOIN user_profiles cup ON cu.user_id = cup.user_id
    WHERE u.deleted_at IS NULL
";
$params = [];

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}
if ($counselor_filter) {
    $sql .= " AND ca.counselor_id = ?";
    $params[] = $counselor_filter;
}
if ($status_filter == 'assigned') {
    $sql .= " AND ca.counselor_id IS NOT NULL";
} elseif ($status_filter == 'unassigned') {
    $sql .= " AND ca.counselor_id IS NULL";
}

$sql .= " ORDER BY up.first_name, up.last_name, u.username";
$students = $db->fetchAll($sql, $params);

// Get all counselors for filter and dropdown
$counselors = $db->fetchAll("
    SELECT u.user_id, u.username, up.first_name, up.last_name
    FROM users u
    JOIN user_roles ur ON u.user_id = ur.user_id
    JOIN roles r ON ur.role_id = r.role_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE r.name = 'counselor' AND u.deleted_at IS NULL
    ORDER BY up.first_name, up.last_name, u.username
");

// Compute stats
$total_students = count($students);
$assigned = count(array_filter($students, fn($s) => $s['assigned_counselor_id']));
$unassigned = $total_students - $assigned;
$total_counselors = count($counselors);
?>

<div class="page-header">
    <h1><i class="fas fa-user-tie"></i> Counselor Assignments</h1>
    <p class="text-muted">Manage which counselor advises each student</p>
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
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total_students ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $assigned ?></h3>
            <p>Assigned</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $unassigned ?></h3>
            <p>Unassigned</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total_counselors ?></h3>
            <p>Counselors</p>
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
            <input type="text" name="search" placeholder="Search by name, username, email..." 
                   value="<?= htmlspecialchars($search) ?>" class="form-control">
            <select name="counselor_id" class="form-control">
                <option value="">All Counselors</option>
                <?php foreach ($counselors as $c): 
                    $cname = $c['first_name'] ? $c['first_name'] . ' ' . $c['last_name'] : $c['username'];
                ?>
                    <option value="<?= $c['user_id'] ?>" <?= $counselor_filter == $c['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cname) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control">
                <option value="">All Students</option>
                <option value="assigned" <?= $status_filter == 'assigned' ? 'selected' : '' ?>>Assigned</option>
                <option value="unassigned" <?= $status_filter == 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="counselor_assignments.php" class="btn btn-outline-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Students Table Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Students (<?= count($students) ?>)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Current Counselor</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): 
                        $student_name = $s['first_name'] ? $s['first_name'] . ' ' . $s['last_name'] : $s['username'];
                        $counselor_name = '';
                        if ($s['assigned_counselor_id']) {
                            $counselor_name = $s['counselor_first_name'] 
                                ? $s['counselor_first_name'] . ' ' . $s['counselor_last_name']
                                : $s['counselor_username'];
                        }
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($student_name) ?></strong></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td>
                                <?php if ($s['assigned_counselor_id']): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($counselor_name) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-icon btn-primary" 
                                        onclick="showAssignModal(<?= $s['student_id'] ?>, '<?= htmlspecialchars(addslashes($student_name)) ?>', <?= $s['assigned_counselor_id'] ?: 'null' ?>)"
                                        title="Assign / Change">
                                    <i class="fas fa-user-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <i class="fas fa-users fa-4x"></i>
                <h4>No students found</h4>
                <p>Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Modal (modernised) -->
<div id="assignModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Assign Counselor</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="student_id" id="student_id">
            <div class="modal-body">
                <p>Student: <strong id="student_name"></strong></p>
                <div class="form-group">
                    <label for="counselor_id">Select Counselor</label>
                    <select name="counselor_id" id="counselor_id" class="form-control" required>
                        <option value="">-- Choose --</option>
                        <?php foreach ($counselors as $c): 
                            $counselor_name = $c['first_name'] ? $c['first_name'] . ' ' . $c['last_name'] : $c['username'];
                        ?>
                            <option value="<?= $c['user_id'] ?>"><?= htmlspecialchars($counselor_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" name="assign_counselor" class="btn btn-primary">Assign</button>
            </div>
        </form>
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

.badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
}
.badge-info {
    background: #17a2b8;
    color: white;
}
.badge-secondary {
    background: #6c757d;
    color: white;
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
    background: #3498db;
    cursor: pointer;
    transition: all 0.2s;
}
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

/* Modal */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background-color: #fff;
    max-width: 400px;
    width: 90%;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: modalSlide 0.3s ease;
}
@keyframes modalSlide {
    from { transform: translateY(-40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 {
    margin: 0;
    color: #2d3748;
    font-size: 1.4rem;
}
.close {
    background: none;
    border: none;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    color: #a0aec0;
}
.close:hover {
    color: #718096;
}
.modal-body {
    padding: 2rem;
}
.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.form-group {
    margin-bottom: 1.2rem;
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
.btn-outline-secondary {
    border: 1px solid #ced4da;
    background: transparent;
    border-radius: 30px;
    padding: 0.6rem 1.5rem;
    color: #6c757d;
}
.btn-outline-secondary:hover {
    background: #e2e6ea;
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
}
</style>

<script>
function showAssignModal(studentId, studentName, currentCounselorId) {
    document.getElementById('student_id').value = studentId;
    document.getElementById('student_name').innerText = studentName;
    const select = document.getElementById('counselor_id');
    if (currentCounselorId) {
        select.value = currentCounselorId;
    } else {
        select.value = '';
    }
    document.getElementById('assignModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('assignModal').style.display = 'none';
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('assignModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>