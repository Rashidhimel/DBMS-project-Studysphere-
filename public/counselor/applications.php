<?php
require_once '../../app/includes/init.php';
requireCounselor();

$page_title = "Student Applications";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

// Get assigned student IDs
$students = $auth->getAssignedStudents($user['user_id']);
$student_ids = array_column($students, 'student_id');
if (empty($student_ids)) {
    $student_ids = [0]; // dummy to avoid empty IN clause
}

$placeholders = implode(',', array_fill(0, count($student_ids), '?'));

// Filter by status
$status_filter = isset($_GET['status']) ? intval($_GET['status']) : 0;
$search = isset($_GET['search']) ? Helpers::sanitize($_GET['search']) : '';

$sql = "
    SELECT a.*, p.program_name, u.name as university_name, s.name as status_name,
           st2.username as student_username, up.first_name, up.last_name
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    JOIN application_statuses s ON a.application_status_id = s.application_status_id
    JOIN students st ON a.student_id = st.student_id
    JOIN users st2 ON st.user_id = st2.user_id
    LEFT JOIN user_profiles up ON st2.user_id = up.user_id
    WHERE a.student_id IN ($placeholders) AND a.deleted_at IS NULL
";
$params = $student_ids;

if ($status_filter > 0) {
    $sql .= " AND a.application_status_id = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $sql .= " AND (p.program_name LIKE ? OR u.name LIKE ? OR st2.username LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
$sql .= " ORDER BY a.applied_at DESC";

$applications = $db->fetchAll($sql, $params);

// Get all statuses for filter dropdown
$statuses = $db->fetchAll("SELECT * FROM application_statuses ORDER BY name");
?>

<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> Student Applications</h1>
    <p>View and manage applications of your assigned students</p>
</div>

<!-- Filter Bar -->
<div class="filter-bar" style="background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
    <form method="GET" class="filters-form" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="flex: 1;">
            <label for="search">Search</label>
            <input type="text" name="search" id="search" class="form-control" placeholder="Program, university, student..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="form-group" style="flex: 1;">
            <label for="status">Status</label>
            <select name="status" id="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s['application_status_id'] ?>" <?= $status_filter == $s['application_status_id'] ? 'selected' : '' ?>>
                        <?= $s['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="applications.php" class="btn btn-secondary">Clear</a>
    </form>
</div>

<div class="dashboard-card">
    <h3>Applications (<?= count($applications) ?>)</h3>
    <?php if (empty($applications)): ?>
        <p>No applications found.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>University</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): 
                    $student_name = $app['first_name'] ? $app['first_name'] . ' ' . $app['last_name'] : $app['student_username'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($student_name) ?></td>
                        <td><?= htmlspecialchars($app['program_name']) ?></td>
                        <td><?= htmlspecialchars($app['university_name']) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($app['status_name']) ?>">
                                <?= $app['status_name'] ?>
                            </span>
                        </td>
                        <td><?= Helpers::formatDate($app['applied_at']) ?></td>
                        <td>
                            <a href="/studysphere/public/admin/application_view.php?id=<?= $app['application_id'] ?>" 
                               class="btn btn-small btn-primary" target="_blank">
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once '../../app/templates/footer.php'; ?>