<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Scholarship Management";
require_once '../../app/templates/header.php';

$db = new Database();
$auth = new Auth();
$current_user = $auth->getCurrentUser();

$success = '';
$error = '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle deletion
if ($action == 'delete' && $id) {
    $db->query("DELETE FROM scholarships WHERE scholarship_id = ?", [$id]);
    $success = 'Scholarship deleted successfully!';
    $action = '';
}

// Handle POST (add/edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_scholarship']) || isset($_POST['edit_scholarship'])) {
        $name = Helpers::sanitize($_POST['name']);
        $program_id = intval($_POST['program_id']);
        $amount = $_POST['amount'] ? floatval($_POST['amount']) : null;
        $currency = Helpers::sanitize($_POST['currency']);
        $deadline = $_POST['deadline'] ?: null;
        $description = Helpers::sanitize($_POST['description']);
        $required_documents = Helpers::sanitize($_POST['required_documents']);
        
        // Build criteria JSON
        $criteria = [];
        if (!empty($_POST['min_gpa'])) $criteria['min_gpa'] = floatval($_POST['min_gpa']);
        if (!empty($_POST['min_test_score'])) $criteria['min_test_score'] = floatval($_POST['min_test_score']);
        if (!empty($_POST['test_type'])) $criteria['test_type'] = $_POST['test_type'];
        if (!empty($_POST['financial_need'])) $criteria['financial_need'] = true;
        if (!empty($_POST['nationality'])) $criteria['nationality'] = explode(',', $_POST['nationality']);
        if (!empty($_POST['field_of_study'])) $criteria['field_of_study'] = $_POST['field_of_study'];
        if (!empty($_POST['gender'])) $criteria['gender'] = $_POST['gender'];
        if (!empty($_POST['other_criteria'])) $criteria['other'] = $_POST['other_criteria'];
        
        if (isset($_POST['add_scholarship'])) {
            $db->insert('scholarships', [
                'program_id' => $program_id,
                'name' => $name,
                'amount' => $amount,
                'currency' => $currency,
                'deadline' => $deadline,
                'description' => $description,
                'required_documents' => $required_documents,
                'criteria' => json_encode($criteria),
                'created_by' => $current_user['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $success = 'Scholarship added successfully!';
        } else {
            $scholarship_id = intval($_POST['scholarship_id']);
            $db->update('scholarships', [
                'program_id' => $program_id,
                'name' => $name,
                'amount' => $amount,
                'currency' => $currency,
                'deadline' => $deadline,
                'description' => $description,
                'required_documents' => $required_documents,
                'criteria' => json_encode($criteria),
                'updated_at' => date('Y-m-d H:i:s')
            ], "scholarship_id = $scholarship_id");
            $success = 'Scholarship updated successfully!';
        }
    }
}

// Filters
$search = $_GET['search'] ?? '';
$university_id = $_GET['university_id'] ?? 0;
$program_id = $_GET['program_id'] ?? 0;

// Build query for scholarships
$where = "1=1";
$params = [];
if ($search) {
    $where .= " AND (s.name LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($program_id) {
    $where .= " AND s.program_id = ?";
    $params[] = $program_id;
} elseif ($university_id) {
    $where .= " AND p.university_id = ?";
    $params[] = $university_id;
}

$scholarships = $db->fetchAll("
    SELECT s.*, p.program_name, p.tuition_fee as program_tuition,
           u.name as university_name, u.university_id,
           COUNT(ss.id) as total_applications,
           COUNT(CASE WHEN ss.status = 'awarded' THEN 1 END) as total_awarded
    FROM scholarships s
    JOIN programs p ON s.program_id = p.program_id
    JOIN universities u ON p.university_id = u.university_id
    LEFT JOIN student_scholarships ss ON s.scholarship_id = ss.scholarship_id
    WHERE $where
    GROUP BY s.scholarship_id
    ORDER BY s.deadline ASC, s.amount DESC
", $params);

// Compute quick stats
$total_active = 0;
$total_expired = 0;
$total_applications = 0;
foreach ($scholarships as $s) {
    if ($s['deadline'] && strtotime($s['deadline']) < time()) {
        $total_expired++;
    } else {
        $total_active++;
    }
    $total_applications += $s['total_applications'];
}

$universities = $db->fetchAll("SELECT * FROM universities WHERE deleted_at IS NULL ORDER BY name");
$programs = $db->fetchAll("
    SELECT p.*, u.name as university_name 
    FROM programs p 
    JOIN universities u ON p.university_id = u.university_id 
    WHERE p.deleted_at IS NULL 
    ORDER BY p.program_name
");

$edit_scholarship = null;
if ($action == 'edit' && $id) {
    $edit_scholarship = $db->fetchOne("SELECT * FROM scholarships WHERE scholarship_id = ?", [$id]);
}

$test_types = $db->fetchAll("SELECT * FROM test_types ORDER BY name");
?>

<div class="page-header">
    <h1><i class="fas fa-award"></i> Scholarship Management</h1>
    <div class="header-actions">
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Scholarship
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Quick Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-award"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo count($scholarships); ?></h3>
            <p>Total Scholarships</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_active; ?></h3>
            <p>Active</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_expired; ?></h3>
            <p>Expired</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_applications; ?></h3>
            <p>Applications</p>
        </div>
    </div>
</div>

<?php if ($action == 'add' || ($action == 'edit' && $edit_scholarship)): ?>
<div class="card">
    <div class="card-header">
        <h3><?php echo $action == 'add' ? 'Add New Scholarship' : 'Edit Scholarship'; ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" class="scholarship-form">
            <?php if ($action == 'edit'): ?>
                <input type="hidden" name="edit_scholarship" value="1">
                <input type="hidden" name="scholarship_id" value="<?php echo $edit_scholarship['scholarship_id']; ?>">
            <?php else: ?>
                <input type="hidden" name="add_scholarship" value="1">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Scholarship Name *</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo $edit_scholarship ? htmlspecialchars($edit_scholarship['name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="program_id">Program *</label>
                    <select id="program_id" name="program_id" required>
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['program_id']; ?>"
                                <?php echo ($edit_scholarship && $edit_scholarship['program_id'] == $program['program_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($program['program_name']); ?> - <?php echo htmlspecialchars($program['university_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" min="0" step="0.01"
                           placeholder="Leave empty for variable amount"
                           value="<?php echo $edit_scholarship ? $edit_scholarship['amount'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select id="currency" name="currency">
                        <option value="USD" <?php echo ($edit_scholarship && $edit_scholarship['currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                        <option value="EUR" <?php echo ($edit_scholarship && $edit_scholarship['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                        <option value="GBP" <?php echo ($edit_scholarship && $edit_scholarship['currency'] == 'GBP') ? 'selected' : ''; ?>>GBP</option>
                        <option value="CAD" <?php echo ($edit_scholarship && $edit_scholarship['currency'] == 'CAD') ? 'selected' : ''; ?>>CAD</option>
                        <option value="AUD" <?php echo ($edit_scholarship && $edit_scholarship['currency'] == 'AUD') ? 'selected' : ''; ?>>AUD</option>
                        <option value="INR" <?php echo ($edit_scholarship && $edit_scholarship['currency'] == 'INR') ? 'selected' : ''; ?>>INR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="deadline">Application Deadline</label>
                    <input type="date" id="deadline" name="deadline"
                           value="<?php echo $edit_scholarship ? $edit_scholarship['deadline'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"
                          placeholder="Describe the scholarship, benefits, and any important information"><?php echo $edit_scholarship ? htmlspecialchars($edit_scholarship['description']) : ''; ?></textarea>
            </div>
            
            <hr>
            <h4>Eligibility Criteria</h4>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="min_gpa">Minimum GPA</label>
                    <input type="number" id="min_gpa" name="min_gpa" min="0" max="4" step="0.01"
                           placeholder="e.g., 3.0"
                           value="<?php 
                           if ($edit_scholarship && $edit_scholarship['criteria']) {
                               $criteria = json_decode($edit_scholarship['criteria'], true);
                               echo $criteria['min_gpa'] ?? '';
                           } ?>">
                </div>
                <div class="form-group">
                    <label for="test_type">Test Type</label>
                    <select id="test_type" name="test_type">
                        <option value="">No specific test</option>
                        <?php foreach ($test_types as $test): ?>
                            <option value="<?php echo $test['code']; ?>"
                                <?php 
                                if ($edit_scholarship && $edit_scholarship['criteria']) {
                                    $criteria = json_decode($edit_scholarship['criteria'], true);
                                    echo ($criteria['test_type'] ?? '') == $test['code'] ? 'selected' : '';
                                } ?>>
                                <?php echo $test['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="min_test_score">Minimum Test Score</label>
                    <input type="number" id="min_test_score" name="min_test_score" min="0" step="0.1"
                           placeholder="e.g., 7.5 for IELTS"
                           value="<?php 
                           if ($edit_scholarship && $edit_scholarship['criteria']) {
                               $criteria = json_decode($edit_scholarship['criteria'], true);
                               echo $criteria['min_test_score'] ?? '';
                           } ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="field_of_study">Field of Study</label>
                    <input type="text" id="field_of_study" name="field_of_study"
                           placeholder="e.g., Computer Science, Engineering"
                           value="<?php 
                           if ($edit_scholarship && $edit_scholarship['criteria']) {
                               $criteria = json_decode($edit_scholarship['criteria'], true);
                               echo $criteria['field_of_study'] ?? '';
                           } ?>">
                </div>
                <div class="form-group">
                    <label for="nationality">Nationality (comma separated)</label>
                    <input type="text" id="nationality" name="nationality"
                           placeholder="e.g., IN,BD,US,GB"
                           value="<?php 
                           if ($edit_scholarship && $edit_scholarship['criteria']) {
                               $criteria = json_decode($edit_scholarship['criteria'], true);
                               $nat = $criteria['nationality'] ?? '';
                               if (is_array($nat)) {
                                   echo implode(',', $nat);
                               } else {
                                   echo htmlspecialchars($nat);
                               }
                           } ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Any</option>
                        <option value="male"
                            <?php 
                            if ($edit_scholarship && $edit_scholarship['criteria']) {
                                $criteria = json_decode($edit_scholarship['criteria'], true);
                                echo ($criteria['gender'] ?? '') == 'male' ? 'selected' : '';
                            } ?>>Male</option>
                        <option value="female"
                            <?php 
                            if ($edit_scholarship && $edit_scholarship['criteria']) {
                                $criteria = json_decode($edit_scholarship['criteria'], true);
                                echo ($criteria['gender'] ?? '') == 'female' ? 'selected' : '';
                            } ?>>Female</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="financial_need" value="1"
                        <?php 
                        if ($edit_scholarship && $edit_scholarship['criteria']) {
                            $criteria = json_decode($edit_scholarship['criteria'], true);
                            echo ($criteria['financial_need'] ?? false) ? 'checked' : '';
                        } ?>>
                    Financial Need Based
                </label>
            </div>
            
            <div class="form-group">
                <label for="other_criteria">Other Criteria</label>
                <textarea id="other_criteria" name="other_criteria" rows="2"
                          placeholder="Any other eligibility requirements"><?php 
                    if ($edit_scholarship && $edit_scholarship['criteria']) {
                        $criteria = json_decode($edit_scholarship['criteria'], true);
                        echo htmlspecialchars($criteria['other'] ?? '');
                    }
                ?></textarea>
            </div>
            
            <hr>
            <h4>Required Documents</h4>
            <div class="form-group">
                <textarea id="required_documents" name="required_documents" rows="5"
                          placeholder="Enter each required document on a new line. For example:
- Academic transcripts
- Proof of financial need
- Letter of recommendation"><?php echo $edit_scholarship ? htmlspecialchars($edit_scholarship['required_documents']) : ''; ?></textarea>
                <small>Each line will be treated as a separate required document</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add Scholarship' : 'Save Changes'; ?>
                </button>
                <a href="scholarships.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$action || $action == 'list'): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> Filter Scholarships</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by name or description..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="university_id">
                <option value="">All Universities</option>
                <?php foreach ($universities as $uni): ?>
                    <option value="<?php echo $uni['university_id']; ?>"
                        <?php echo $university_id == $uni['university_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($uni['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="program_id">
                <option value="">All Programs</option>
                <?php foreach ($programs as $prog): ?>
                    <option value="<?php echo $prog['program_id']; ?>"
                        <?php echo $program_id == $prog['program_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($prog['program_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="scholarships.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> All Scholarships (<?php echo count($scholarships); ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($scholarships)): ?>
            <div class="empty-state">
                <i class="fas fa-award fa-4x"></i>
                <h4>No scholarships found</h4>
                <p>Add your first scholarship to start offering financial aid to students.</p>
                <a href="?action=add" class="btn btn-primary">Add Scholarship</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Scholarship</th>
                            <th>Program & University</th>
                            <th>Amount</th>
                            <th>Deadline</th>
                            <th>Applications</th>
                            <th>Awarded</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scholarships as $schol): ?>
                            <?php 
                                $is_expired = ($schol['deadline'] && strtotime($schol['deadline']) < time());
                                $status_color = $is_expired ? '#e74c3c' : '#27ae60';
                                $status_text = $is_expired ? 'Expired' : 'Active';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($schol['name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars(substr($schol['description'] ?? 'No description', 0, 50)); ?>...</small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($schol['program_name']); ?><br>
                                    <small><?php echo htmlspecialchars($schol['university_name']); ?></small>
                                </td>
                                <td>
                                    <?php if ($schol['amount']): ?>
                                        <strong><?php echo $schol['currency'] . ' ' . number_format($schol['amount']); ?></strong>
                                    <?php else: ?>
                                        <em>Variable</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($schol['deadline']): 
                                        $days_left = floor((strtotime($schol['deadline']) - time()) / (60 * 60 * 24));
                                    ?>
                                        <?php echo Helpers::formatDate($schol['deadline']); ?><br>
                                        <small style="color: <?php echo $days_left <= 7 ? '#e74c3c' : '#7f8c8d'; ?>;">
                                            <?php echo $days_left > 0 ? $days_left . ' days left' : 'Expired'; ?>
                                        </small>
                                    <?php else: ?>
                                        <em>No deadline</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $schol['total_applications']; ?></td>
                                <td><?php echo $schol['total_awarded']; ?></td>
                                <td>
                                    <span class="status-badge" style="background: <?php echo $status_color; ?>;">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?action=edit&id=<?php echo $schol['scholarship_id']; ?>" 
                                           class="btn btn-icon btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $schol['scholarship_id']; ?>" 
                                           class="btn btn-icon btn-danger"
                                           onclick="return confirm('Delete this scholarship? All associated applications will be removed.')"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <button class="btn btn-icon btn-info" 
                                                onclick="viewScholarshipDetails(<?php echo $schol['scholarship_id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scholarship Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Scholarship Details</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            Loading...
        </div>
    </div>
</div>

<script>
function viewScholarshipDetails(id) {
    fetch(`ajax_get_scholarship.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            let html = `
                <h4>${data.name}</h4>
                <p><strong>Program:</strong> ${data.program_name} - ${data.university_name}</p>
                <p><strong>Amount:</strong> ${data.amount ? data.currency + ' ' + Number(data.amount).toLocaleString() : 'Variable'}</p>
                ${data.deadline ? `<p><strong>Deadline:</strong> ${data.deadline}</p>` : ''}
                ${data.description ? `<p><strong>Description:</strong> ${data.description}</p>` : ''}
            `;
            
            if (data.criteria) {
                try {
                    let criteria = JSON.parse(data.criteria);
                    html += `<h5>Eligibility Criteria:</h5><ul>`;
                    if (criteria.min_gpa) html += `<li>Minimum GPA: ${criteria.min_gpa}</li>`;
                    if (criteria.test_type) html += `<li>Test Type: ${criteria.test_type}</li>`;
                    if (criteria.min_test_score) html += `<li>Minimum Test Score: ${criteria.min_test_score}</li>`;
                    if (criteria.field_of_study) html += `<li>Field of Study: ${criteria.field_of_study}</li>`;
                    if (criteria.nationality) {
                        let nationalities = Array.isArray(criteria.nationality) ? criteria.nationality.join(', ') : criteria.nationality;
                        html += `<li>Nationality: ${nationalities}</li>`;
                    }
                    if (criteria.gender) html += `<li>Gender: ${criteria.gender}</li>`;
                    if (criteria.financial_need) html += `<li>Financial Need Based: Yes</li>`;
                    if (criteria.other) html += `<li>Other: ${criteria.other}</li>`;
                    html += `</ul>`;
                } catch(e) {
                    html += `<p><strong>Criteria:</strong> ${data.criteria}</p>`;
                }
            }
            
            if (data.required_documents) {
                html += `<h5>Required Documents:</h5><ul>`;
                let docs = data.required_documents.split('\n').filter(doc => doc.trim() !== '');
                docs.forEach(doc => {
                    html += `<li>${doc.trim()}</li>`;
                });
                html += `</ul>`;
            }
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailsModal').style.display = 'flex';
        })
        .catch(error => {
            document.getElementById('modalBody').innerHTML = 'Error loading scholarship details.';
        });
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>
<?php endif; ?>

<style>
/* Modern stats grid */
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

/* Card styling */
.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    margin-bottom: 2rem;
    overflow: hidden;
}
.card-header {
    padding: 1.5rem 1.5rem 0.5rem;
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

/* Filter form */
.filter-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.filter-form input, .filter-form select {
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    min-width: 200px;
    flex: 1;
}

/* Table */
.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.75rem;
}
.table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
}
.table td {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    vertical-align: middle;
}
.status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
}
.action-buttons {
    display: flex;
    gap: 0.4rem;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-icon.btn-primary { background: #3498db; }
.btn-icon.btn-primary:hover { background: #2980b9; }
.btn-icon.btn-danger { background: #e74c3c; }
.btn-icon.btn-danger:hover { background: #c0392b; }
.btn-icon.btn-info { background: #1abc9c; }
.btn-icon.btn-info:hover { background: #16a085; }

/* Form styling */
.scholarship-form {
    max-width: 800px;
    margin: 0 auto;
}
.scholarship-form h4 {
    margin: 1.5rem 0 1rem;
    color: #2c3e50;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.5rem;
}
.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.form-group {
    flex: 1;
    min-width: 200px;
}
.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #4a5568;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
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
    margin-top: 2rem;
    justify-content: flex-end;
}
hr {
    border: none;
    border-top: 1px solid #e2e8f0;
    margin: 2rem 0;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem;
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
    background: white;
    max-width: 600px;
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

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .filter-form { flex-direction: column; }
    .filter-form input, .filter-form select { width: 100%; }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>