<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Exam Preparation";
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

// Get filter parameters
$test_type_id = isset($_GET['test_type']) ? intval($_GET['test_type']) : 0;
$resource_type = isset($_GET['resource_type']) ? Helpers::sanitize($_GET['resource_type']) : '';
$difficulty = isset($_GET['difficulty']) ? Helpers::sanitize($_GET['difficulty']) : '';

// Build query
$sql = "SELECT er.*, tt.name as test_type_name, tt.code as test_type_code
        FROM exam_resources er
        JOIN test_types tt ON er.test_type_id = tt.test_type_id
        WHERE 1=1";
$params = [];

if ($test_type_id > 0) {
    $sql .= " AND er.test_type_id = ?";
    $params[] = $test_type_id;
}
if (!empty($resource_type)) {
    $sql .= " AND er.resource_type = ?";
    $params[] = $resource_type;
}
if (!empty($difficulty)) {
    $sql .= " AND er.difficulty = ?";
    $params[] = $difficulty;
}

$sql .= " ORDER BY tt.name, er.title";

$resources = $db->fetchAll($sql, $params);

// Get all test types for filter
$test_types = $db->fetchAll("SELECT * FROM test_types ORDER BY name");

// Get distinct resource types present
$resource_types = $db->fetchAll("SELECT DISTINCT resource_type FROM exam_resources ORDER BY resource_type");

// Get student's attempts (if we want to show progress)
$attempts = $db->fetchAll(
    "SELECT uea.*, er.title, er.resource_type FROM user_exam_attempts uea 
     JOIN exam_resources er ON uea.resource_id = er.resource_id
     WHERE uea.student_id = ? ORDER BY uea.attempted_at DESC",
    [$student_id]
);
?>

<div class="page-header">
    <h1><i class="fas fa-book-open"></i> Exam Preparation</h1>
    <p>Free resources to help you ace your standardized tests</p>
</div>

<!-- Filter Bar -->
<div class="filter-bar" style="background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
    <form method="GET" class="filters-form" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="test_type">Exam Type</label>
            <select name="test_type" id="test_type" class="form-control">
                <option value="">All Exams</option>
                <?php foreach ($test_types as $tt): ?>
                    <option value="<?php echo $tt['test_type_id']; ?>" <?php echo $test_type_id == $tt['test_type_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tt['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="resource_type">Resource Type</label>
            <select name="resource_type" id="resource_type" class="form-control">
                <option value="">All Types</option>
                <?php foreach ($resource_types as $rt): ?>
                    <option value="<?php echo $rt['resource_type']; ?>" <?php echo $resource_type == $rt['resource_type'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $rt['resource_type'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="difficulty">Difficulty</label>
            <select name="difficulty" id="difficulty" class="form-control">
                <option value="">All Levels</option>
                <option value="beginner" <?php echo $difficulty == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                <option value="intermediate" <?php echo $difficulty == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                <option value="advanced" <?php echo $difficulty == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 38px;">
            <i class="fas fa-filter"></i> Apply Filters
        </button>
        <a href="exam_resources.php" class="btn btn-secondary" style="height: 38px;">Clear</a>
    </form>
</div>

<!-- Resources Grid -->
<div class="resources-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
    <?php if (empty($resources)): ?>
        <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 3rem;">
            <i class="fas fa-search fa-4x" style="color: #ccc;"></i>
            <h3>No resources found</h3>
            <p>Try adjusting your filters or check back later.</p>
        </div>
    <?php else: ?>
        <?php foreach ($resources as $resource): 
            // Check if student has attempted this resource
            $attempted = false;
            $best_score = null;
            foreach ($attempts as $att) {
                if ($att['resource_id'] == $resource['resource_id']) {
                    $attempted = true;
                    $best_score = $att['score'];
                    break;
                }
            }
        ?>
            <div class="resource-card" style="border: 1px solid #e9ecef; border-radius: 8px; background: white; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;">
                <div style="padding: 1rem; border-bottom: 1px solid #f1f1f1; background: #f8f9fa;">
                    <span class="badge" style="background: #3498db; color: white;"><?php echo htmlspecialchars($resource['test_type_code']); ?></span>
                    <span class="badge" style="background: #6c757d; color: white; margin-left: 0.5rem;"><?php echo ucfirst(str_replace('_', ' ', $resource['resource_type'])); ?></span>
                    <?php if ($resource['difficulty']): ?>
                        <span class="badge" style="background: 
                            <?php 
                            if ($resource['difficulty'] == 'beginner') echo '#28a745';
                            elseif ($resource['difficulty'] == 'intermediate') echo '#ffc107';
                            else echo '#dc3545';
                            ?>; color: white; margin-left: 0.5rem;">
                            <?php echo ucfirst($resource['difficulty']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="padding: 1rem;">
                    <h4 style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($resource['title']); ?></h4>
                    <?php if ($resource['description']): ?>
                        <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;"><?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?>...</p>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <?php if (!$resource['is_free']): ?>
                                <span class="badge" style="background: #f39c12; color: white;">Premium</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($resource['attachment_id']): ?>
                                <a href="/studysphere/public/download_file.php?id=<?php echo $resource['attachment_id']; ?>" class="btn btn-primary btn-small">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            <?php elseif ($resource['file_url']): ?>
                                <a href="<?php echo htmlspecialchars($resource['file_url']); ?>" target="_blank" class="btn btn-primary btn-small">
                                    <i class="fas fa-external-link-alt"></i> Open
                                </a>
                            <?php endif; ?>
                            <?php if ($resource['resource_type'] == 'question_set'): ?>
                                <a href="exam_practice.php?resource_id=<?php echo $resource['resource_id']; ?>" class="btn btn-secondary btn-small">
                                    <i class="fas fa-pencil-alt"></i> Practice
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($attempted): ?>
                        <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #28a745;">
                            <i class="fas fa-check-circle"></i> Attempted · Best score: <?php echo $best_score; ?>%
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- My Activity Section (optional) -->
<?php if (!empty($attempts)): ?>
<div class="dashboard-card" style="margin-top: 2rem;">
    <h3><i class="fas fa-chart-line"></i> My Practice History</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Resource</th>
                <th>Type</th>
                <th>Score</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attempts as $att): ?>
                <tr>
                    <td><?php echo htmlspecialchars($att['title']); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $att['resource_type'])); ?></td>
                    <td><?php echo $att['score'] ? $att['score'] . '%' : 'N/A'; ?></td>
                    <td><?php echo Helpers::formatDate($att['attempted_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<style>
.resource-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>