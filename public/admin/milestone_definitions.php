<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Manage Milestones";
require_once '../../app/templates/header.php';

$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_milestone']) || isset($_POST['edit_milestone'])) {
        $milestone_id = isset($_POST['milestone_id']) ? intval($_POST['milestone_id']) : 0;
        $code = Helpers::sanitize($_POST['code']);
        $name = Helpers::sanitize($_POST['name']);
        $description = Helpers::sanitize($_POST['description']);
        $badge_icon = Helpers::sanitize($_POST['badge_icon'] ?? '');
        $points = intval($_POST['points']);

        $data = [
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'badge_icon' => $badge_icon,
            'points' => $points
        ];

        if ($milestone_id > 0 && isset($_POST['edit_milestone'])) {
            $db->update('milestone_definitions', $data, "milestone_id = $milestone_id");
            $success = "Milestone updated.";
        } else {
            $db->insert('milestone_definitions', $data);
            $success = "Milestone added.";
        }
    } elseif (isset($_POST['delete_milestone'])) {
        $milestone_id = intval($_POST['milestone_id']);
        $db->query("DELETE FROM milestone_definitions WHERE milestone_id = $milestone_id");
        $success = "Milestone deleted.";
    }
}

// Get filter/search
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM milestone_definitions";
$params = [];
if (!empty($search)) {
    $sql .= " WHERE code LIKE ? OR name LIKE ? OR description LIKE ?";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
$sql .= " ORDER BY points DESC";

$milestones = $db->fetchAll($sql, $params);

// Compute stats
$total = count($milestones);
$total_points = array_sum(array_column($milestones, 'points'));
$avg_points = $total ? round($total_points / $total, 1) : 0;
$with_icons = count(array_filter($milestones, fn($m) => !empty($m['badge_icon'])));
?>

<div class="page-header">
    <h1><i class="fas fa-trophy"></i> Manage Milestones</h1>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Add Milestone
        </button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total; ?></h3>
            <p>Total Milestones</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_points; ?></h3>
            <p>Total Points</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $avg_points; ?></h3>
            <p>Avg. Points</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
            <i class="fas fa-icons"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $with_icons; ?></h3>
            <p>With Icons</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #1abc9c, #16a085);">
            <i class="fas fa-code"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo count(array_unique(array_column($milestones, 'code'))); ?></h3>
            <p>Unique Codes</p>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> Filter Milestones</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by code, name, or description..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="milestone_definitions.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Milestones Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> All Milestones (<?php echo $total; ?>)</h3>
    </div>
    <div class="card-body p-0">
        <div class="scrollable-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Badge</th>
                        <th>Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($milestones as $m): ?>
                        <tr>
                            <td><?php echo $m['milestone_id']; ?></td>
                            <td><code><?php echo htmlspecialchars($m['code']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($m['description']); ?></td>
                            <td><?php if (!empty($m['badge_icon'])): ?>
                                <span class="badge-icon"><i class="fas <?php echo htmlspecialchars($m['badge_icon']); ?>"></i></span>
                            <?php endif; ?></td>
                            <td><span class="badge badge-points"><?php echo $m['points']; ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon-sm btn-primary" onclick="editMilestone(<?php echo htmlspecialchars(json_encode($m)); ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this milestone?');">
                                        <input type="hidden" name="milestone_id" value="<?php echo $m['milestone_id']; ?>">
                                        <button type="submit" name="delete_milestone" class="btn-icon-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="milestoneModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Milestone</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="milestone_id" id="milestone_id" value="">
            <div class="form-group">
                <label for="code">Code *</label>
                <input type="text" name="code" id="code" class="form-control" required placeholder="e.g., profile_completed">
            </div>
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="Profile Completed">
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="badge_icon">Badge Icon <small>(FontAwesome class, e.g., fa-user-check)</small></label>
                <input type="text" name="badge_icon" id="badge_icon" class="form-control" placeholder="fa-user-check">
            </div>
            <div class="form-group">
                <label for="points">Points</label>
                <input type="number" name="points" id="points" class="form-control" value="10">
            </div>
            <div class="form-actions">
                <button type="submit" name="add_milestone" id="submitBtn" class="btn btn-primary">Save Milestone</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Reuse styles from other admin pages */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}
.page-header h1 {
    margin: 0;
}
.header-actions {
    display: flex;
    gap: 0.5rem;
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

/* Cards */
.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    margin-bottom: 2rem;
    overflow: hidden;
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
.card-body {
    padding: 1.5rem;
}
.card-body.p-0 {
    padding: 0;
}

/* Filter form */
.filter-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.filter-form input {
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    font-size: 0.9rem;
    min-width: 250px;
}
.filter-form .btn {
    border-radius: 30px;
    padding: 0.6rem 1.5rem;
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
.badge-points {
    background: #f1c40f;
    color: #2c3e50;
    padding: 0.2rem 0.6rem;
    border-radius: 30px;
    font-weight: 600;
}
.badge-icon {
    font-size: 1.2rem;
    color: #3498db;
}
code {
    background: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}
.btn-icon-sm {
    width: 30px;
    height: 30px;
    border-radius: 8px;
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
.btn-icon-sm.btn-danger { background: #e74c3c; }
.btn-icon-sm.btn-danger:hover { background: #c0392b; }

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
    background: white;
    max-width: 500px;
    width: 90%;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: modalSlide 0.3s ease;
    overflow: hidden;
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
}
.close {
    background: none;
    border: none;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    color: #a0aec0;
    transition: color 0.2s;
}
.close:hover {
    color: #718096;
}
.form-group {
    margin-bottom: 1.2rem;
    padding: 0 2rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.3rem;
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
.form-actions {
    padding: 0 2rem 2rem;
    display: flex;
    gap: 1rem;
}
.btn {
    border-radius: 30px;
    padding: 0.5rem 1.5rem;
    border: none;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: #3498db;
    color: white;
}
.btn-primary:hover {
    background: #2980b9;
}
.btn-secondary {
    background: #e2e8f0;
    color: #2c3e50;
}
.btn-secondary:hover {
    background: #cbd5e0;
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
}
</style>

<script>
function showAddModal() {
    document.getElementById('modalTitle').innerText = 'Add Milestone';
    document.getElementById('milestone_id').value = '';
    document.getElementById('code').value = '';
    document.getElementById('name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('badge_icon').value = '';
    document.getElementById('points').value = '10';
    document.getElementById('submitBtn').name = 'add_milestone';
    document.getElementById('milestoneModal').style.display = 'flex';
}

function editMilestone(m) {
    document.getElementById('modalTitle').innerText = 'Edit Milestone';
    document.getElementById('milestone_id').value = m.milestone_id;
    document.getElementById('code').value = m.code;
    document.getElementById('name').value = m.name;
    document.getElementById('description').value = m.description || '';
    document.getElementById('badge_icon').value = m.badge_icon || '';
    document.getElementById('points').value = m.points;
    document.getElementById('submitBtn').name = 'edit_milestone';
    document.getElementById('milestoneModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('milestoneModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('milestoneModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>