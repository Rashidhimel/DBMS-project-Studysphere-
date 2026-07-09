<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Test Scores";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$error = '';

$test_types = Helpers::getTestTypes();

// Updated query to calculate overall score from sections
$test_scores = $db->fetchAll(
    "SELECT ts.*, tt.name as test_name,
            (SELECT GROUP_CONCAT(CONCAT(tss.section_name, ': ', tss.score_value, '/', tss.max_score) SEPARATOR ', ') 
             FROM test_score_sections tss 
             WHERE tss.score_id = ts.score_id) as sections_summary,
            (SELECT ROUND(AVG(tss.score_value), 1) 
             FROM test_score_sections tss 
             WHERE tss.score_id = ts.score_id) as calculated_overall
     FROM test_scores ts 
     JOIN test_types tt ON ts.test_type_id = tt.test_type_id 
     WHERE ts.student_id = ? 
     ORDER BY ts.test_date DESC",
    [$student_id]
);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_test'])) {
        $test_type_id = intval($_POST['test_type_id']);
        $test_date = Helpers::sanitize($_POST['test_date']);
        $valid_until = Helpers::sanitize($_POST['valid_until']);
        $overall_score = Helpers::sanitize($_POST['overall_score']);
        
        // Insert test score (without overall_score since column doesn't exist)
        $db->insert('test_scores', [
            'student_id' => $student_id,
            'test_type_id' => $test_type_id,
            'test_date' => $test_date,
            'valid_until' => $valid_until,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $score_id = $db->getConnection()->lastInsertId();
        
        // Add overall score as a section if provided
        if (!empty($overall_score)) {
            $db->insert('test_score_sections', [
                'score_id' => $score_id,
                'section_name' => 'Overall',
                'score_value' => $overall_score,
                'max_score' => isset($_POST['max_score_overall']) ? $_POST['max_score_overall'] : null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Add other sections
        if (isset($_POST['section_name']) && is_array($_POST['section_name'])) {
            for ($i = 0; $i < count($_POST['section_name']); $i++) {
                if (!empty($_POST['section_name'][$i])) {
                    $db->insert('test_score_sections', [
                        'score_id' => $score_id,
                        'section_name' => Helpers::sanitize($_POST['section_name'][$i]),
                        'score_value' => Helpers::sanitize($_POST['section_score'][$i]),
                        'max_score' => Helpers::sanitize($_POST['max_score'][$i]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
        
        Helpers::redirect('tests.php', 'Test score added successfully!');
    }
    
    if (isset($_FILES['score_report']) && $_FILES['score_report']['error'] == 0) {
        $score_id = intval($_POST['score_id']);
        
        $score = $db->fetchOne(
            "SELECT score_id FROM test_scores WHERE score_id = ? AND student_id = ?",
            [$score_id, $student_id]
        );
        
        if ($score) {
            $result = $upload_helper->uploadFile($_FILES['score_report'], $user['user_id'], 'test_score');
            
            if (isset($result['success']) && $result['success']) {
                $upload_helper->linkToTestScore($result['attachment_id'], $score_id);
                Helpers::redirect('tests.php', 'Score report uploaded successfully!');
            } else {
                $error = $result['error'] ?? 'Upload failed';
            }
        }
    }
    
    if (isset($_POST['delete_score'])) {
        $score_id = intval($_POST['score_id']);
        
        // Delete from related tables first
        $db->query("DELETE FROM test_score_sections WHERE score_id = ?", [$score_id]);
        $db->query("DELETE FROM test_score_documents WHERE score_id = ?", [$score_id]);
        
        // Delete the test score
        $db->query("DELETE FROM test_scores WHERE score_id = ? AND student_id = ?", [$score_id, $student_id]);
        
        Helpers::redirect('tests.php', 'Test score deleted successfully!');
    }
}

$flash = Helpers::getFlash();
?>

<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> Test Scores</h1>
    <p>Manage your standardized test scores and upload score reports</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Add New Test Score Card -->
<div class="modern-card add-test-card">
    <div class="card-header">
        <div class="card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
            <i class="fas fa-plus-circle"></i>
        </div>
        <h3>Add New Test Score</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="testForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="test_type_id">Test Type *</label>
                    <select id="test_type_id" name="test_type_id" required>
                        <option value="">Select Test Type</option>
                        <?php foreach ($test_types as $type): ?>
                            <option value="<?php echo $type['test_type_id']; ?>">
                                <?php echo $type['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="test_date">Test Date *</label>
                    <input type="date" id="test_date" name="test_date" required>
                </div>
                <div class="form-group">
                    <label for="valid_until">Valid Until</label>
                    <input type="date" id="valid_until" name="valid_until">
                    <small>Score validity date (if applicable)</small>
                </div>
            </div>
            
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-chart-line"></i>
                    <h4>Overall Score (Optional)</h4>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="overall_score">Overall Score</label>
                        <input type="number" step="0.1" id="overall_score" name="overall_score" placeholder="e.g., 7.5">
                    </div>
                    <div class="form-group">
                        <label for="max_score_overall">Max Score</label>
                        <input type="number" step="0.1" id="max_score_overall" name="max_score_overall" placeholder="e.g., 9.0">
                    </div>
                </div>
                <small class="hint">For tests with a single overall score (like IELTS, TOEFL)</small>
            </div>
            
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-layer-group"></i>
                    <h4>Section Scores (Optional)</h4>
                </div>
                <div id="sectionsContainer">
                    <div class="section-row">
                        <div class="form-group">
                            <input type="text" name="section_name[]" placeholder="Section (e.g., Reading)">
                        </div>
                        <div class="form-group">
                            <input type="number" step="0.1" name="section_score[]" placeholder="Score">
                        </div>
                        <div class="form-group">
                            <input type="number" step="0.1" name="max_score[]" placeholder="Max Score">
                        </div>
                        <button type="button" class="btn-icon btn-danger" onclick="this.closest('.section-row').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addSection()">
                    <i class="fas fa-plus"></i> Add Section
                </button>
            </div>
            
            <button type="submit" name="add_test" class="btn btn-primary btn-block">Add Test Score</button>
        </form>
    </div>
</div>

<!-- Test Scores List -->
<?php if (empty($test_scores)): ?>
    <div class="empty-state">
        <i class="fas fa-file-alt fa-4x"></i>
        <h4>No test scores recorded yet</h4>
        <p>Add your first test score using the form above.</p>
    </div>
<?php else: ?>
    <div class="scores-grid">
        <?php foreach ($test_scores as $score): ?>
            <div class="score-card">
                <div class="score-header">
                    <div class="test-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="test-info">
                        <h4><?php echo $score['test_name']; ?></h4>
                        <span class="test-date"><?php echo Helpers::formatDate($score['test_date'], 'M d, Y'); ?></span>
                    </div>
                    <div class="score-badge">
                        <?php 
                        if ($score['calculated_overall']) {
                            echo '<span class="overall-score">' . $score['calculated_overall'] . '</span>';
                        } else {
                            echo '<span class="overall-score">N/A</span>';
                        }
                        ?>
                    </div>
                </div>
                <div class="score-body">
                    <?php if ($score['sections_summary']): ?>
                        <div class="sections">
                            <?php 
                            $sections = $db->fetchAll(
                                "SELECT * FROM test_score_sections WHERE score_id = ? ORDER BY section_name",
                                [$score['score_id']]
                            );
                            foreach ($sections as $section): ?>
                                <div class="section-item">
                                    <span class="section-name"><?php echo $section['section_name']; ?>:</span>
                                    <span class="section-score"><?php echo $section['score_value']; ?>
                                        <?php if ($section['max_score']): ?>
                                            /<?php echo $section['max_score']; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($score['valid_until']): ?>
                        <div class="validity">
                            <i class="far fa-calendar-alt"></i>
                            Valid until <?php echo Helpers::formatDate($score['valid_until']); ?>
                            <?php if (strtotime($score['valid_until']) < time()): ?>
                                <span class="badge badge-warning">Expired</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Uploaded reports -->
                    <?php 
                    $reports = $db->fetchAll(
                        "SELECT a.* FROM attachments a
                         JOIN test_score_documents tsd ON a.attachment_id = tsd.attachment_id
                         WHERE tsd.score_id = ? AND a.deleted_at IS NULL",
                        [$score['score_id']]
                    );
                    ?>
                    <?php if (!empty($reports)): ?>
                        <div class="reports">
                            <strong><i class="fas fa-paperclip"></i> Reports:</strong>
                            <div class="report-list">
                                <?php foreach ($reports as $doc): ?>
                                    <div class="report-item">
                                        <i class="fas fa-file-pdf" style="color: #e74c3c;"></i>
                                        <span><?php echo htmlspecialchars($doc['filename']); ?></span>
                                        <div class="report-actions">
                                            <a href="<?php echo $doc['file_url']; ?>" target="_blank" class="btn-icon" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo $doc['file_url']; ?>" download class="btn-icon" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="score-footer">
                    <button class="btn-icon" onclick="toggleUploadForm(<?php echo $score['score_id']; ?>)" title="Upload Report">
                        <i class="fas fa-upload"></i>
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this test score?');">
                        <input type="hidden" name="score_id" value="<?php echo $score['score_id']; ?>">
                        <button type="submit" name="delete_score" class="btn-icon btn-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Hidden upload form -->
                <div id="upload-form-<?php echo $score['score_id']; ?>" class="upload-form" style="display:none;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="score_id" value="<?php echo $score['score_id']; ?>">
                        <div class="form-group">
                            <label>Select Score Report</label>
                            <input type="file" name="score_report" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            <small>Accepted: PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                        </div>
                        <div class="upload-actions">
                            <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleUploadForm(<?php echo $score['score_id']; ?>)">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function addSection() {
    const container = document.getElementById('sectionsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'section-row';
    newRow.innerHTML = `
        <div class="form-group">
            <input type="text" name="section_name[]" placeholder="Section (e.g., Writing)">
        </div>
        <div class="form-group">
            <input type="number" step="0.1" name="section_score[]" placeholder="Score">
        </div>
        <div class="form-group">
            <input type="number" step="0.1" name="max_score[]" placeholder="Max Score">
        </div>
        <button type="button" class="btn-icon btn-danger" onclick="this.closest('.section-row').remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(newRow);
}

function toggleUploadForm(scoreId) {
    const form = document.getElementById('upload-form-' + scoreId);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Form validation
document.getElementById('testForm').addEventListener('submit', function(e) {
    const testType = document.getElementById('test_type_id').value;
    const testDate = document.getElementById('test_date').value;
    const overallScore = document.getElementById('overall_score').value;
    
    if (!testType || !testDate) {
        alert('Please fill in all required fields (Test Type and Test Date).');
        e.preventDefault();
        return;
    }
    
    const hasOverallScore = overallScore.trim() !== '';
    const hasSections = document.querySelectorAll('.section-row input[name^="section_name"]').length > 0;
    
    if (!hasOverallScore && !hasSections) {
        alert('Please provide either an Overall Score or at least one section score.');
        e.preventDefault();
    }
});
</script>

<style>
/* Modern Add Card */
.modern-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.05);
    margin-bottom: 2.5rem;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.modern-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}
.modern-card .card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
}
.card-icon {
    width: 50px;
    height: 50px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.modern-card .card-header h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
    color: #2c3e50;
}
.modern-card .card-body {
    padding: 2rem;
}

/* Form styling */
.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.form-group {
    margin-bottom: 0.5rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.9rem;
}
.form-group input,
.form-group select {
    width: 100%;
    padding: 0.7rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}
small {
    display: block;
    margin-top: 0.3rem;
    color: #7f8c8d;
    font-size: 0.75rem;
}

/* Section cards */
.section-card {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.section-header i {
    font-size: 1.2rem;
    color: #3498db;
}
.section-header h4 {
    margin: 0;
    font-size: 1.1rem;
    color: #2c3e50;
}
.section-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 1rem;
}
.section-row .form-group {
    flex: 1;
    margin-bottom: 0;
}
.section-row .btn-icon {
    flex-shrink: 0;
}
.hint {
    color: #95a5a6;
    font-style: italic;
}

/* Buttons */
.btn {
    padding: 0.6rem 1.2rem;
    border-radius: 40px;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(52,152,219,0.3);
}
.btn-secondary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
}
.btn-secondary:hover {
    background: #e9ecef;
}
.btn-danger {
    background: #e74c3c;
    color: white;
}
.btn-danger:hover {
    background: #c0392b;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    color: #495057;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}
.btn-icon:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}
.btn-icon.btn-danger {
    color: #e74c3c;
}
.btn-icon.btn-danger:hover {
    background: #fee;
}
.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
}
.btn-block {
    width: 100%;
    justify-content: center;
}

/* Scores grid */
.scores-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}
.score-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.score-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.1);
}
.score-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 1.2rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.test-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.test-info {
    flex: 1;
}
.test-info h4 {
    margin: 0 0 0.2rem;
    font-size: 1.1rem;
}
.test-date {
    font-size: 0.8rem;
    opacity: 0.8;
}
.score-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.3rem 1rem;
    border-radius: 30px;
}
.overall-score {
    font-size: 1.3rem;
    font-weight: 700;
}
.score-body {
    padding: 1.5rem;
}
.sections {
    margin-bottom: 1rem;
}
.section-item {
    display: flex;
    justify-content: space-between;
    padding: 0.4rem 0;
    border-bottom: 1px solid #f0f0f0;
}
.section-name {
    font-weight: 500;
    color: #2c3e50;
}
.section-score {
    font-weight: 600;
    color: #3498db;
}
.validity {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 1rem;
    font-size: 0.85rem;
    color: #7f8c8d;
}
.badge {
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.badge-warning {
    background: #f39c12;
    color: white;
}
.reports {
    margin-top: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 0.8rem;
}
.reports strong {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #2c3e50;
}
.report-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.report-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: white;
    border-radius: 8px;
    padding: 0.3rem 0.8rem;
    font-size: 0.85rem;
}
.report-item i {
    font-size: 1rem;
}
.report-actions {
    margin-left: auto;
    display: flex;
    gap: 0.2rem;
}
.report-actions .btn-icon {
    width: 28px;
    height: 28px;
    font-size: 0.8rem;
}
.score-footer {
    padding: 0.8rem 1.5rem 1.5rem;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    border-top: 1px solid #f0f0f0;
}
.upload-form {
    padding: 1rem 1.5rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e0e0e0;
}
.upload-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}
.empty-state h4 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}
.empty-state p {
    color: #7f8c8d;
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    .section-row {
        flex-wrap: wrap;
    }
    .scores-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>