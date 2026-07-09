<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Manage Exam Resources";
require_once '../../app/templates/header.php';

$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_resource']) || isset($_POST['edit_resource'])) {
        $resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
        $test_type_id = intval($_POST['test_type_id']);
        $title = Helpers::sanitize($_POST['title']);
        $description = Helpers::sanitize($_POST['description']);
        $resource_type = Helpers::sanitize($_POST['resource_type']);
        $file_url = Helpers::sanitize($_POST['file_url'] ?? '');
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $difficulty = Helpers::sanitize($_POST['difficulty'] ?? 'intermediate');

        // Handle file upload if attachment
        $attachment_id = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
            require_once APP_PATH . '/includes/upload_helper.php';
            $uploader = new UploadHelper();
            $result = $uploader->uploadFile($_FILES['attachment'], $_SESSION['user_id'], 'exam_resources');
            if (isset($result['success']) && $result['success']) {
                $attachment_id = $result['attachment_id'];
            } else {
                $error = "File upload failed: " . ($result['error'] ?? 'Unknown error');
            }
        }

        $data = [
            'test_type_id' => $test_type_id,
            'title' => $title,
            'description' => $description,
            'resource_type' => $resource_type,
            'is_free' => $is_free,
            'difficulty' => $difficulty,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($attachment_id) {
            $data['attachment_id'] = $attachment_id;
            $data['file_url'] = null;
        } elseif (!empty($file_url)) {
            $data['file_url'] = $file_url;
            $data['attachment_id'] = null;
        }

        if ($resource_id > 0 && isset($_POST['edit_resource'])) {
            $db->update('exam_resources', $data, "resource_id = $resource_id");
            $success = "Resource updated successfully.";
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'];
            $db->insert('exam_resources', $data);
            $success = "Resource added successfully.";
        }
    } elseif (isset($_POST['delete_resource'])) {
        $resource_id = intval($_POST['resource_id']);
        $db->query("DELETE FROM exam_resources WHERE resource_id = ?", [$resource_id]);
        $success = "Resource deleted.";
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$test_type_filter = isset($_GET['test_type']) ? intval($_GET['test_type']) : 0;
$resource_type_filter = $_GET['resource_type'] ?? '';
$difficulty_filter = $_GET['difficulty'] ?? '';

// Build query with filters
$sql = "SELECT er.*, tt.name as test_type_name
        FROM exam_resources er
        JOIN test_types tt ON er.test_type_id = tt.test_type_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (er.title LIKE ? OR er.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}
if ($test_type_filter) {
    $sql .= " AND er.test_type_id = ?";
    $params[] = $test_type_filter;
}
if (!empty($resource_type_filter)) {
    $sql .= " AND er.resource_type = ?";
    $params[] = $resource_type_filter;
}
if (!empty($difficulty_filter)) {
    $sql .= " AND er.difficulty = ?";
    $params[] = $difficulty_filter;
}
$sql .= " ORDER BY tt.name, er.title";

$resources = $db->fetchAll($sql, $params);

// Compute stats
$total = count($resources);
$free_count = count(array_filter($resources, fn($r) => $r['is_free'] == 1));
$premium_count = $total - $free_count;
$question_set_count = count(array_filter($resources, fn($r) => $r['resource_type'] == 'question_set'));
$guide_count = count(array_filter($resources, fn($r) => $r['resource_type'] == 'guide'));

$test_types = Helpers::getTestTypes();
$resource_types = $db->fetchAll("SELECT DISTINCT resource_type FROM exam_resources ORDER BY resource_type");
$difficulties = ['beginner', 'intermediate', 'advanced'];
?>

<div class="page-header">
    <h1><i class="fas fa-book-open"></i> Manage Exam Resources</h1>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Add Resource
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
<?php if (isset($error)): ?>
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
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total; ?></h3>
            <p>Total Resources</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
            <i class="fas fa-lock-open"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $free_count; ?></h3>
            <p>Free</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-crown"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $premium_count; ?></h3>
            <p>Premium</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
            <i class="fas fa-question-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $question_set_count; ?></h3>
            <p>Question Sets</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #1abc9c, #16a085);">
            <i class="fas fa-bookmark"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $guide_count; ?></h3>
            <p>Guides</p>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> Filter Resources</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by title or description..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="test_type">
                <option value="">All Exam Types</option>
                <?php foreach ($test_types as $tt): ?>
                    <option value="<?php echo $tt['test_type_id']; ?>" <?php echo $test_type_filter == $tt['test_type_id'] ? 'selected' : ''; ?>>
                        <?php echo $tt['name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="resource_type">
                <option value="">All Resource Types</option>
                <?php foreach ($resource_types as $rt): ?>
                    <option value="<?php echo $rt['resource_type']; ?>" <?php echo $resource_type_filter == $rt['resource_type'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $rt['resource_type'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="difficulty">
                <option value="">All Difficulties</option>
                <?php foreach ($difficulties as $d): ?>
                    <option value="<?php echo $d; ?>" <?php echo $difficulty_filter == $d ? 'selected' : ''; ?>>
                        <?php echo ucfirst($d); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="exam_resources.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Resources Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> All Resources (<?php echo $total; ?>)</h3>
    </div>
    <div class="card-body p-0">
        <div class="scrollable-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Test</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Difficulty</th>
                        <th>Free</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resources as $res): ?>
                        <tr>
                            <td><?php echo $res['resource_id']; ?></td>
                            <td><?php echo $res['test_type_name']; ?></td>
                            <td><strong><?php echo htmlspecialchars($res['title']); ?></strong></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $res['resource_type'])); ?></td>
                            <td><?php echo ucfirst($res['difficulty']); ?></td>
                            <td><?php echo $res['is_free'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon-sm btn-primary" onclick="editResource(<?php echo htmlspecialchars(json_encode($res)); ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this resource?');">
                                        <input type="hidden" name="resource_id" value="<?php echo $res['resource_id']; ?>">
                                        <button type="submit" name="delete_resource" class="btn-icon-sm btn-danger" title="Delete">
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
<div id="resourceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Resource</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="resourceForm" enctype="multipart/form-data">
            <input type="hidden" name="resource_id" id="resource_id" value="">
            <div class="form-group">
                <label for="test_type_id">Exam Type *</label>
                <select name="test_type_id" id="test_type_id" class="form-control" required>
                    <option value="">Select Exam</option>
                    <?php foreach ($test_types as $tt): ?>
                        <option value="<?php echo $tt['test_type_id']; ?>"><?php echo $tt['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" name="title" id="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label for="resource_type">Resource Type *</label>
                <select name="resource_type" id="resource_type" class="form-control" required>
                    <option value="question_set">Question Set</option>
                    <option value="test_paper">Test Paper</option>
                    <option value="guide">Guide</option>
                    <option value="video">Video</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="difficulty">Difficulty</label>
                <select name="difficulty" id="difficulty" class="form-control">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate" selected>Intermediate</option>
                    <option value="advanced">Advanced</option>
                </select>
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="is_free" id="is_free" value="1" checked> Free
                </label>
            </div>
            <div class="form-group">
                <label>Upload File (or provide URL)</label>
                <input type="file" name="attachment" id="attachment" class="form-control">
                <small>Max 20MB</small>
            </div>
            <div class="form-group">
                <label for="file_url">OR External URL</label>
                <input type="url" name="file_url" id="file_url" class="form-control" placeholder="https://...">
            </div>
            <div class="form-actions">
                <button type="submit" name="add_resource" id="submitBtn" class="btn btn-primary">Save Resource</button>
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
.filter-form input,
.filter-form select {
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    font-size: 0.9rem;
    min-width: 150px;
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
.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

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
    max-width: 600px;
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
.modal-body {
    padding: 2rem;
}
.form-group {
    margin-bottom: 1.2rem;
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
.form-group.checkbox {
    display: flex;
    align-items: center;
}
.form-group.checkbox input {
    width: auto;
    margin-right: 0.5rem;
}
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
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
    .filter-form input,
    .filter-form select {
        width: 100%;
    }
}
</style>

<script>
function showAddModal() {
    document.getElementById('modalTitle').innerText = 'Add Resource';
    document.getElementById('resource_id').value = '';
    document.getElementById('test_type_id').value = '';
    document.getElementById('title').value = '';
    document.getElementById('description').value = '';
    document.getElementById('resource_type').value = 'question_set';
    document.getElementById('difficulty').value = 'intermediate';
    document.getElementById('is_free').checked = true;
    document.getElementById('attachment').value = '';
    document.getElementById('file_url').value = '';
    document.getElementById('submitBtn').name = 'add_resource';
    document.getElementById('resourceModal').style.display = 'flex';
}

function editResource(res) {
    document.getElementById('modalTitle').innerText = 'Edit Resource';
    document.getElementById('resource_id').value = res.resource_id;
    document.getElementById('test_type_id').value = res.test_type_id;
    document.getElementById('title').value = res.title;
    document.getElementById('description').value = res.description || '';
    document.getElementById('resource_type').value = res.resource_type;
    document.getElementById('difficulty').value = res.difficulty;
    document.getElementById('is_free').checked = res.is_free == 1;
    document.getElementById('file_url').value = res.file_url || '';
    document.getElementById('submitBtn').name = 'edit_resource';
    document.getElementById('resourceModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('resourceModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('resourceModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>