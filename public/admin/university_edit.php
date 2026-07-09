<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Edit University";
require_once '../../app/templates/header.php';

$db = new Database();
$auth = new Auth();
$current_user = $auth->getCurrentUser();

$university_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


$university = $db->fetchOne(
    "SELECT * FROM universities WHERE university_id = ? AND deleted_at IS NULL",
    [$university_id]
);

if (!$university) {
    echo "<div class='alert alert-error'>University not found.</div>";
    echo "<a href='universities.php' class='btn btn-secondary'>Back to Universities</a>";
    require_once '../../app/templates/footer.php';
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = Helpers::sanitize($_POST['name']);
    $country_code = Helpers::sanitize($_POST['country_code']);
    $city = Helpers::sanitize($_POST['city']);
    $website = Helpers::sanitize($_POST['website']);
    $qs_ranking = $_POST['qs_ranking'] ? intval($_POST['qs_ranking']) : null;
    $approx_tuition = $_POST['approx_tuition'] ? floatval($_POST['approx_tuition']) : null;
    $student_count = $_POST['student_count'] ? intval($_POST['student_count']) : null;
    $description = Helpers::sanitize($_POST['description']);
    
    $db->update('universities', [
        'name' => $name,
        'country_code' => $country_code,
        'city' => $city,
        'website' => $website,
        'qs_ranking' => $qs_ranking,
        'approx_tuition' => $approx_tuition,
        'student_count' => $student_count,
        'description' => $description,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $current_user['user_id']
    ], "university_id = $university_id");
    
    $success = 'University updated successfully!';
    
    $university = $db->fetchOne(
        "SELECT * FROM universities WHERE university_id = ? AND deleted_at IS NULL",
        [$university_id]
    );
}

$countries = Helpers::getCountries();
?>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> Edit University</h1>
    <a href="universities.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Universities
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="name">University Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($university['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="country_code">Country *</label>
                <select id="country_code" name="country_code" required>
                    <option value="">Select Country</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo $country['iso2']; ?>" 
                            <?php echo ($university['country_code'] == $country['iso2']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($country['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($university['city']); ?>">
            </div>
            <div class="form-group">
                <label for="website">Website</label>
                <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($university['website']); ?>" placeholder="https://example.edu">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="qs_ranking">QS World Ranking</label>
                <input type="number" id="qs_ranking" name="qs_ranking" 
                       value="<?php echo $university['qs_ranking']; ?>" min="1" placeholder="e.g., 50">
            </div>
            <div class="form-group">
                <label for="approx_tuition">Approximate Tuition (USD)</label>
                <input type="number" id="approx_tuition" name="approx_tuition" 
                       value="<?php echo $university['approx_tuition']; ?>" min="0" step="0.01" placeholder="e.g., 30000">
            </div>
            <div class="form-group">
                <label for="student_count">Total Students</label>
                <input type="number" id="student_count" name="student_count" 
                       value="<?php echo $university['student_count']; ?>" min="0" placeholder="e.g., 20000">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($university['description']); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
        </button>
        <a href="universities.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once '../../app/templates/footer.php'; ?>