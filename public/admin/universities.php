<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "University Management";
require_once '../../app/templates/header.php';

$db = new Database();
$auth = new Auth();
$current_user = $auth->getCurrentUser();

$success = '';
$error = '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle delete
if ($action == 'delete' && $id) {
    $db->update('universities', [
        'deleted_at' => date('Y-m-d H:i:s'),
        'updated_by' => $current_user['user_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ], "university_id = $id");
    $success = 'University deleted successfully!';
    $action = '';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_university'])) {
        $name = Helpers::sanitize($_POST['name']);
        $country_code = Helpers::sanitize($_POST['country_code']);
        $city = Helpers::sanitize($_POST['city']);
        $website = Helpers::sanitize($_POST['website']);
        $qs_ranking = $_POST['qs_ranking'] ? intval($_POST['qs_ranking']) : null;
        $approx_tuition = $_POST['approx_tuition'] ? floatval($_POST['approx_tuition']) : null;
        $student_count = $_POST['student_count'] ? intval($_POST['student_count']) : null;
        $description = Helpers::sanitize($_POST['description']);
        $required_documents = Helpers::sanitize($_POST['required_documents'] ?? '');
        
        $db->insert('universities', [
            'name' => $name,
            'country_code' => $country_code,
            'city' => $city,
            'website' => $website,
            'qs_ranking' => $qs_ranking,
            'approx_tuition' => $approx_tuition,
            'student_count' => $student_count,
            'description' => $description,
            'required_documents' => $required_documents,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $current_user['user_id']
        ]);
        $success = 'University added successfully!';
    }
    
    if (isset($_POST['edit_university'])) {
        $university_id = intval($_POST['university_id']);
        $name = Helpers::sanitize($_POST['name']);
        $country_code = Helpers::sanitize($_POST['country_code']);
        $city = Helpers::sanitize($_POST['city']);
        $website = Helpers::sanitize($_POST['website']);
        $qs_ranking = $_POST['qs_ranking'] ? intval($_POST['qs_ranking']) : null;
        $approx_tuition = $_POST['approx_tuition'] ? floatval($_POST['approx_tuition']) : null;
        $student_count = $_POST['student_count'] ? intval($_POST['student_count']) : null;
        $description = Helpers::sanitize($_POST['description']);
        $required_documents = Helpers::sanitize($_POST['required_documents'] ?? '');
        
        $db->update('universities', [
            'name' => $name,
            'country_code' => $country_code,
            'city' => $city,
            'website' => $website,
            'qs_ranking' => $qs_ranking,
            'approx_tuition' => $approx_tuition,
            'student_count' => $student_count,
            'description' => $description,
            'required_documents' => $required_documents,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $current_user['user_id']
        ], "university_id = $university_id");
        $success = 'University updated successfully!';
    }
}

// Search/filter
$search = $_GET['search'] ?? '';
$country = $_GET['country'] ?? '';

$where = "u.deleted_at IS NULL";
$params = [];

if ($search) {
    $where .= " AND (u.name LIKE ? OR u.city LIKE ? OR u.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
if ($country) {
    $where .= " AND u.country_code = ?";
    $params[] = $country;
}

// Get universities with counts
$universities = $db->fetchAll("
    SELECT u.*, c.name as country_name,
           (SELECT COUNT(*) FROM programs p WHERE p.university_id = u.university_id AND p.deleted_at IS NULL) as program_count,
           (SELECT COUNT(*) FROM applications a WHERE a.university_id = u.university_id) as application_count
    FROM universities u
    LEFT JOIN countries c ON u.country_code = c.iso2
    WHERE $where
    ORDER BY u.name
", $params);

$countries = Helpers::getCountries();

$edit_university = null;
if ($action == 'edit' && $id) {
    $edit_university = $db->fetchOne("SELECT * FROM universities WHERE university_id = ? AND deleted_at IS NULL", [$id]);
}

// Stats for dashboard
$total_universities = count($universities);
$total_programs = array_sum(array_column($universities, 'program_count'));
$total_applications = array_sum(array_column($universities, 'application_count'));
?>

<div class="page-header">
    <h1><i class="fas fa-university"></i> University Management</h1>
    <div class="header-actions">
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New University
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

<!-- Stats Cards -->
<div class="stats-grid modern">
    <div class="stat-item">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-university"></i>
        </div>
        <div class="stat-content">
            <h4><?php echo $total_universities; ?></h4>
            <p>Universities</p>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-content">
            <h4><?php echo $total_programs; ?></h4>
            <p>Programs</p>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
            <h4><?php echo $total_applications; ?></h4>
            <p>Applications</p>
        </div>
    </div>
</div>

<?php if ($action == 'add' || ($action == 'edit' && $edit_university)): ?>
<!-- Add/Edit Form -->
<div class="card modern-form">
    <div class="card-header">
        <h3><?php echo $action == 'add' ? 'Add New University' : 'Edit University'; ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?php echo $action == 'edit' ? 'edit_university' : 'add_university'; ?>">
            <?php if ($action == 'edit'): ?>
                <input type="hidden" name="university_id" value="<?php echo $edit_university['university_id']; ?>">
            <?php endif; ?>
            
            <h4>Basic Information</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="name">University Name *</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo $edit_university ? htmlspecialchars($edit_university['name']) : ''; ?>"
                           placeholder="e.g., University of Example">
                </div>
                <div class="form-group">
                    <label for="country_code">Country *</label>
                    <select id="country_code" name="country_code" required>
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country['iso2']; ?>"
                                <?php echo ($edit_university && $edit_university['country_code'] == $country['iso2']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city"
                           value="<?php echo $edit_university ? htmlspecialchars($edit_university['city']) : ''; ?>"
                           placeholder="e.g., New York">
                </div>
                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="url" id="website" name="website" placeholder="https://example.edu"
                           value="<?php echo $edit_university ? htmlspecialchars($edit_university['website']) : ''; ?>">
                </div>
            </div>
            
            <h4>Details</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="qs_ranking">QS World Ranking</label>
                    <input type="number" id="qs_ranking" name="qs_ranking" min="1" placeholder="e.g., 50"
                           value="<?php echo $edit_university ? $edit_university['qs_ranking'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="approx_tuition">Approximate Tuition (USD)</label>
                    <input type="number" id="approx_tuition" name="approx_tuition" min="0" step="0.01" placeholder="e.g., 30000"
                           value="<?php echo $edit_university ? $edit_university['approx_tuition'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="student_count">Total Students</label>
                    <input type="number" id="student_count" name="student_count" min="0" placeholder="e.g., 20000"
                           value="<?php echo $edit_university ? $edit_university['student_count'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Brief description including facilities, dormitories, scholarships, etc."><?php echo $edit_university ? htmlspecialchars($edit_university['description']) : ''; ?></textarea>
            </div>
            
            <h4>Documents</h4>
            <div class="form-group">
                <label for="required_documents">Required Documents for Application</label>
                <textarea id="required_documents" name="required_documents" rows="6" placeholder="Enter each required document on a new line. For example:
- Application form
- Academic transcripts
- Proof of English proficiency
- Letter of recommendation
- Statement of purpose
- Passport copy"><?php echo $edit_university ? htmlspecialchars($edit_university['required_documents'] ?? '') : ''; ?></textarea>
                <small>One document per line. Use "- " at the start if you like, but not required.</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add University' : 'Save Changes'; ?>
                </button>
                <a href="universities.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$action || $action == 'list'): ?>
<!-- University List -->
<div class="card modern-card">
    <div class="card-header">
        <h3>All Universities (<?php echo count($universities); ?>)</h3>
        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search universities..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="country">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?php echo $c['iso2']; ?>"
                            <?php echo $country == $c['iso2'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="universities.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($universities)): ?>
            <div class="empty-state">
                <i class="fas fa-university fa-4x"></i>
                <h4>No universities found</h4>
                <p>Add your first university to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table modern-table">
                    <thead>
                        <tr>
                            <th>University</th>
                            <th>Location</th>
                            <th>Ranking</th>
                            <th>Tuition</th>
                            <th>Programs</th>
                            <th>Applications</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($universities as $univ): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($univ['name']); ?></strong><br>
                                <small>
                                    <?php if($univ['website']): ?>
                                        <a href="<?php echo htmlspecialchars($univ['website']); ?>" target="_blank">
                                            <?php echo parse_url($univ['website'], PHP_URL_HOST); ?>
                                        </a>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span class="country-flag"><?php echo strtolower($univ['country_code']); ?></span>
                                <?php echo htmlspecialchars($univ['city'] ?: ''); ?><br>
                                <small><?php echo htmlspecialchars($univ['country_name']); ?></small>
                            </td>
                            <td>
                                <?php echo $univ['qs_ranking'] ? '#' . $univ['qs_ranking'] : 'N/A'; ?>
                            </td>
                            <td>
                                <?php echo $univ['approx_tuition'] ? '$' . number_format($univ['approx_tuition']) : 'N/A'; ?>
                            </td>
                            <td><?php echo $univ['program_count']; ?></td>
                            <td><?php echo $univ['application_count']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=edit&id=<?php echo $univ['university_id']; ?>" 
                                       class="btn btn-small btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $univ['university_id']; ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Delete this university? All programs and related data will be affected.')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="programs.php?university_id=<?php echo $univ['university_id']; ?>" 
                                       class="btn btn-small" title="View Programs">
                                        <i class="fas fa-eye"></i>
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
</div>
<?php endif; ?>

<style>
/* Modern Stats Grid */
.stats-grid.modern {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-item {
    background: white;
    border-radius: 16px;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}
.stat-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
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
.stat-content h4 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Modern Cards */
.card.modern-form, .card.modern-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    margin-bottom: 2rem;
    overflow: hidden;
}
.card-header {
    padding: 1.5rem 2rem;
    background: white;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.card-header h3 {
    margin: 0;
    font-size: 1.3rem;
    color: #2d3748;
}
.card-body {
    padding: 2rem;
}

/* Form Styling */
.modern-form h4 {
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
    transition: all 0.2s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    justify-content: flex-end;
}

/* Buttons */
.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(52,152,219,0.3);
}
.btn-secondary {
    background: #f1f5f9;
    color: #2c3e50;
    border: 1px solid #e2e8f0;
}
.btn-secondary:hover {
    background: #e2e8f0;
}
.btn-small {
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
}
.btn-danger {
    background: #fee;
    color: #e74c3c;
    border: 1px solid #fcc;
}
.btn-danger:hover {
    background: #fdd;
}

/* Filter Form */
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
}

/* Table */
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.75rem;
}
.modern-table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
}
.modern-table td {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    vertical-align: middle;
}
.action-buttons {
    display: flex;
    gap: 0.5rem;
}
.country-flag {
    display: inline-block;
    width: 20px;
    height: 15px;
    background: #f0f0f0;
    border-radius: 3px;
    margin-right: 0.3rem;
    background-size: cover;
    background-position: center;
}
/* You can add flag sprite or emoji; for now just text */

/* Empty State */
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
</style>

<?php require_once '../../app/templates/footer.php'; ?>