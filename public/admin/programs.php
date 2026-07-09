<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Program Management";
require_once '../../app/templates/header.php';

$db = new Database();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

switch($action) {
    case 'add':
    case 'edit':
        handleProgramForm($action, $id);
        break;
    case 'delete':
        deleteProgram($id);
        break;
    default:
        showProgramList();
}

function showProgramList() {
    global $db;
    
    $search = $_GET['search'] ?? '';
    $university_id = $_GET['university_id'] ?? 0;
    $degree_id = $_GET['degree_id'] ?? 0;
    
    $where = "p.deleted_at IS NULL";
    $params = [];
    
    if ($search) {
        $where .= " AND (p.program_name LIKE ? OR u.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($university_id) {
        $where .= " AND p.university_id = ?";
        $params[] = $university_id;
    }
    if ($degree_id) {
        $where .= " AND p.degree_level_id = ?";
        $params[] = $degree_id;
    }
    
    $programs = $db->fetchAll("
        SELECT p.*, u.name as university_name, dl.name as degree_level,
               COUNT(a.application_id) as application_count,
               af.amount as fee_amount, af.currency as fee_currency
        FROM programs p
        JOIN universities u ON p.university_id = u.university_id
        JOIN degree_levels dl ON p.degree_level_id = dl.degree_level_id
        LEFT JOIN applications a ON p.program_id = a.program_id
        LEFT JOIN application_fees af ON p.program_id = af.program_id
        WHERE $where
        GROUP BY p.program_id
        ORDER BY p.program_name
    ", $params);
    
    $universities = $db->fetchAll("SELECT * FROM universities WHERE deleted_at IS NULL ORDER BY name");
    $degree_levels = $db->fetchAll("SELECT * FROM degree_levels ORDER BY name");
    ?>
    <div class="page-header">
        <h1><i class="fas fa-university"></i> Program Management</h1>
        <div class="header-actions">
            <a href="programs.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Add Program</a>
            <a href="universities.php?action=add" class="btn btn-secondary"><i class="fas fa-building"></i> Add University</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Filter Programs</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search program or university..." value="<?= htmlspecialchars($search) ?>">
                <select name="university_id">
                    <option value="">All Universities</option>
                    <?php foreach($universities as $u): ?>
                        <option value="<?= $u['university_id'] ?>" <?= $university_id == $u['university_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="degree_id">
                    <option value="">All Degrees</option>
                    <?php foreach($degree_levels as $d): ?>
                        <option value="<?= $d['degree_level_id'] ?>" <?= $degree_id == $d['degree_level_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="programs.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Programs (<?= count($programs) ?>)</h3>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Program / Department</th>
                        <th>University</th>
                        <th>Degree</th>
                        <th>Applications</th>
                        <th>Deadline</th>
                        <th>Tuition</th>
                        <th>Application Fee</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($programs as $p): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($p['program_name']) ?></strong><br>
                            <small><?= htmlspecialchars($p['department']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($p['university_name']) ?></td>
                        <td><?= htmlspecialchars($p['degree_level']) ?></td>
                        <td><?= $p['application_count'] ?></td>
                        <td><?= $p['application_deadline'] ? date('M d, Y', strtotime($p['application_deadline'])) : 'N/A' ?></td>
                        <td><?= $p['tuition_fee'] ? '$'.number_format($p['tuition_fee'],2) : 'N/A' ?></td>
                        <td>
                            <?php if ($p['fee_amount']): ?>
                                <span class="fee-badge"><?= $p['fee_currency'] ?> <?= number_format($p['fee_amount'],2) ?></span>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="programs.php?action=edit&id=<?= $p['program_id'] ?>" class="btn btn-small btn-primary"><i class="fas fa-edit"></i></a>
                            <a href="programs.php?action=delete&id=<?= $p['program_id'] ?>" 
                               class="btn btn-small btn-danger" 
                               onclick="return confirm('Delete this program? All related data will be removed.')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function handleProgramForm($action, $id) {
    global $db;
    
    $program = null;
    $fee = null;
    if ($id) {
        $program = $db->fetchOne("SELECT * FROM programs WHERE program_id = ?", [$id]);
        $fee = $db->fetchOne("SELECT * FROM application_fees WHERE program_id = ?", [$id]);
    }
    $universities = $db->fetchAll("SELECT * FROM universities WHERE deleted_at IS NULL ORDER BY name");
    $degree_levels = $db->fetchAll("SELECT * FROM degree_levels ORDER BY name");
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = [
            'program_name' => $_POST['program_name'],
            'university_id' => $_POST['university_id'],
            'degree_level_id' => $_POST['degree_level_id'],
            'department' => $_POST['department'],
            'duration_months' => $_POST['duration_months'] ?: null,
            'tuition_fee' => $_POST['tuition_fee'] ?: null,
            'currency' => $_POST['currency'] ?: 'USD',
            'intake_months' => $_POST['intake_months'],
            'application_deadline' => $_POST['application_deadline'] ?: null,
            'rolling_admission' => $_POST['rolling_admission'] ?? 0,
            'decision_release_weeks' => $_POST['decision_release_weeks'] ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            if ($action == 'add') {
                $data['created_at'] = date('Y-m-d H:i:s');
                $db->insert('programs', $data);
                $id = $db->getConnection()->lastInsertId();
                $message = "Program added successfully!";
            } else {
                $db->update('programs', $data, "program_id = $id");
                $message = "Program updated successfully!";
            }
            
            // Handle required documents
            if (!empty($_POST['required_documents'])) {
                // Clear existing document requirements
                $db->query("DELETE prd FROM program_requirement_docs prd 
                          JOIN program_requirements pr ON prd.req_id = pr.req_id 
                          WHERE pr.program_id = ? AND pr.requirement_type = 'document'", [$id]);
                $db->query("DELETE FROM program_requirements WHERE program_id = ? AND requirement_type = 'document'", [$id]);
                
                // Save new document requirements
                saveProgramRequirements($id, $_POST['required_documents']);
            }
            
            // Handle application fee
            $fee_amount = $_POST['fee_amount'] ? floatval($_POST['fee_amount']) : null;
            $fee_currency = $_POST['fee_currency'] ?: 'USD';
            $waiver_criteria = $_POST['waiver_criteria'] ? json_encode([$_POST['waiver_criteria']]) : null; // Simple JSON array
            
            if ($fee_amount !== null) {
                // Delete existing fee if any
                $db->query("DELETE FROM application_fees WHERE program_id = ?", [$id]);
                // Insert new fee
                $db->insert('application_fees', [
                    'program_id' => $id,
                    'amount' => $fee_amount,
                    'currency' => $fee_currency,
                    'waiver_criteria' => $waiver_criteria,
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                // No fee, delete any existing
                $db->query("DELETE FROM application_fees WHERE program_id = ?", [$id]);
            }
            
            echo "<script>alert('$message'); location.href='programs.php?action=edit&id=$id';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
        $program = $db->fetchOne("SELECT * FROM programs WHERE program_id = ?", [$id]);
        $fee = $db->fetchOne("SELECT * FROM application_fees WHERE program_id = ?", [$id]);
    }
    ?>
    <div class="page-header">
        <h1><i class="fas fa-<?= $action == 'add' ? 'plus' : 'edit' ?>"></i> <?= $action == 'add' ? 'Add' : 'Edit' ?> Program</h1>
        <a href="programs.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><?= $action == 'add' ? 'New Program' : 'Edit Program' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" class="program-form">
                <h4>Basic Information</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Program Name *</label>
                        <input type="text" name="program_name" value="<?= htmlspecialchars($program['program_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>University *</label>
                        <select name="university_id" required>
                            <option value="">Select University</option>
                            <?php foreach($universities as $u): ?>
                                <option value="<?= $u['university_id'] ?>" <?= ($program['university_id'] ?? '') == $u['university_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Degree Level *</label>
                        <select name="degree_level_id" required>
                            <option value="">Select Degree</option>
                            <?php foreach($degree_levels as $d): ?>
                                <option value="<?= $d['degree_level_id'] ?>" <?= ($program['degree_level_id'] ?? '') == $d['degree_level_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" value="<?= htmlspecialchars($program['department'] ?? '') ?>" placeholder="e.g., Computer Science">
                    </div>
                </div>
                
                <h4>Tuition & Duration</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Duration (months)</label>
                        <input type="number" name="duration_months" value="<?= $program['duration_months'] ?? '' ?>" placeholder="e.g., 24">
                    </div>
                    <div class="form-group">
                        <label>Tuition Fee</label>
                        <input type="number" step="0.01" name="tuition_fee" value="<?= $program['tuition_fee'] ?? '' ?>" placeholder="30000">
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <input type="text" name="currency" value="<?= htmlspecialchars($program['currency'] ?? 'USD') ?>" maxlength="3" placeholder="USD">
                    </div>
                </div>
                
                <h4>Application Details</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Intake Months</label>
                        <input type="text" name="intake_months" value="<?= htmlspecialchars($program['intake_months'] ?? '') ?>" placeholder="Fall, Spring, Summer">
                    </div>
                    <div class="form-group">
                        <label>Application Deadline</label>
                        <input type="date" name="application_deadline" value="<?= $program['application_deadline'] ?? '' ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="rolling_admission" value="1" <?= ($program['rolling_admission'] ?? 0) ? 'checked' : '' ?>>
                            Rolling Admission
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Decision Release (weeks after deadline)</label>
                        <input type="number" name="decision_release_weeks" value="<?= $program['decision_release_weeks'] ?? '' ?>" placeholder="e.g., 4">
                    </div>
                </div>
                
                <h4>Application Fee</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fee Amount</label>
                        <input type="number" step="0.01" name="fee_amount" value="<?= $fee['amount'] ?? '' ?>" placeholder="e.g., 75.00">
                    </div>
                    <div class="form-group">
                        <label>Fee Currency</label>
                        <input type="text" name="fee_currency" value="<?= htmlspecialchars($fee['currency'] ?? 'USD') ?>" maxlength="3" placeholder="USD">
                    </div>
                </div>
                <div class="form-group">
                    <label>Waiver Criteria (optional)</label>
                    <textarea name="waiver_criteria" rows="2" placeholder="Describe fee waiver eligibility, e.g., need-based, academic merit..."><?php 
                        $criteria = $fee['waiver_criteria'] ?? null;
                        if ($criteria) {
                            $decoded = json_decode($criteria, true);
                            if (is_array($decoded)) echo implode(', ', $decoded);
                        }
                    ?></textarea>
                    <small>Enter criteria as plain text. Will be stored as JSON.</small>
                </div>
                
                <h4>Program-Specific Required Documents</h4>
                <div class="form-group">
                    <textarea name="required_documents" rows="6" placeholder="Enter one document requirement per line, prefixed with '-'. Example:
- Portfolio (for arts programs)
- GRE/GMAT scores
- Research proposal
- CV/Resume"><?php 
                    if ($id) {
                        $docs = getProgramDocumentRequirements($id);
                        echo htmlspecialchars($docs);
                    }
                ?></textarea>
                    <small>These are in addition to university requirements. Use "- " at the start of each line. You can add descriptions in parentheses.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Program</button>
                    <a href="programs.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function getProgramDocumentRequirements($program_id) {
    global $db;
    
    $documents = $db->fetchAll("
        SELECT prd.document_type, prd.description 
        FROM program_requirement_docs prd
        JOIN program_requirements pr ON prd.req_id = pr.req_id
        WHERE pr.program_id = ? AND pr.requirement_type = 'document'
        ORDER BY prd.document_type
    ", [$program_id]);
    
    $result = [];
    foreach ($documents as $doc) {
        $line = "- " . $doc['document_type'];
        if (!empty($doc['description'])) {
            $line .= " (" . $doc['description'] . ")";
        }
        $result[] = $line;
    }
    
    return implode("\n", $result);
}

function saveProgramRequirements($program_id, $documents_text) {
    global $db;
    
    $lines = explode("\n", $documents_text);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '- ') !== 0) {
            continue;
        }
        
        $line = substr($line, 2); // Remove "- " prefix
        $description = '';
        
        // Check if there's a description in parentheses
        if (preg_match('/^(.*?)\s*\((.*)\)$/', $line, $matches)) {
            $document_type = trim($matches[1]);
            $description = trim($matches[2]);
        } else {
            $document_type = trim($line);
        }
        
        if (!empty($document_type)) {
            // Create requirement entry
            $db->insert('program_requirements', [
                'program_id' => $program_id,
                'requirement_type' => 'document',
                'minimum_value' => null,
                'is_mandatory' => 1,
                'description' => '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $req_id = $db->getConnection()->lastInsertId();
            
            // Create document requirement
            $db->insert('program_requirement_docs', [
                'req_id' => $req_id,
                'document_type' => $document_type,
                'description' => $description,
                'is_mandatory' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}

function deleteProgram($id) {
    global $db;
    
    // First, delete related requirements
    $reqs = $db->fetchAll("SELECT req_id FROM program_requirements WHERE program_id = ?", [(int)$id]);
    foreach ($reqs as $req) {
        $db->query("DELETE FROM program_requirement_docs WHERE req_id = ?", [$req['req_id']]);
        $db->query("DELETE FROM program_requirement_tests WHERE req_id = ?", [$req['req_id']]);
    }
    $db->query("DELETE FROM program_requirements WHERE program_id = ?", [$id]);
    
    // Delete program tags
    $db->query("DELETE FROM program_tags WHERE program_id = ?", [$id]);
    
    // Delete program subjects
    $db->query("DELETE FROM program_subjects WHERE program_id = ?", [$id]);
    
    // Delete application fees
    $db->query("DELETE FROM application_fees WHERE program_id = ?", [$id]);
    
    // Delete scholarships
    $db->query("DELETE FROM scholarships WHERE program_id = ?", [$id]);
    
    // Soft delete the program
    $db->update('programs', ['deleted_at' => date('Y-m-d H:i:s')], "program_id = $id");
    
    echo "<script>alert('Program deleted successfully!'); location.href='programs.php';</script>";
}
?>

<style>
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

.filter-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.filter-form input, .filter-form select {
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
    min-width: 200px;
    flex: 1;
}

.card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
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
}
.card-body {
    padding: 1.5rem;
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

.program-form {
    max-width: 800px;
    margin: 0 auto;
}
.program-form h4 {
    margin: 2rem 0 1rem;
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
    transition: border 0.2s;
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
.fee-badge {
    background: #d4edda;
    color: #155724;
    padding: 0.2rem 0.6rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
}
.text-muted {
    color: #95a5a6;
    font-size: 0.9rem;
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>