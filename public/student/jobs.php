<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Part-Time Jobs";
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

// Get student's existing preferences
$preferences = $db->fetchOne(
    "SELECT * FROM part_time_preferences WHERE student_id = ?",
    [$student_id]
);

// Handle preference update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_preferences'])) {
    $job_sector = Helpers::sanitize($_POST['job_sector'] ?? '');
    $job_type = Helpers::sanitize($_POST['job_type'] ?? '');
    $worked_before = isset($_POST['worked_before']) ? 1 : 0;
    $experience_years = floatval($_POST['experience_years'] ?? 0);
    $expected_wage = !empty($_POST['expected_wage']) ? floatval($_POST['expected_wage']) : null;
    $expected_work_hours = !empty($_POST['expected_work_hours']) ? intval($_POST['expected_work_hours']) : null;
    $visa_required = isset($_POST['visa_required']) ? 1 : 0;
    $references_text = Helpers::sanitize($_POST['references_text'] ?? '');

    if ($preferences) {
        // Update existing
        $db->update('part_time_preferences', [
            'job_sector' => $job_sector,
            'job_type' => $job_type,
            'worked_before' => $worked_before,
            'experience_years' => $experience_years,
            'expected_wage' => $expected_wage,
            'expected_work_hours' => $expected_work_hours,
            'visa_required' => $visa_required,
            'references_text' => $references_text
        ], "preference_id = " . $preferences['preference_id']);
        $success = "Preferences updated successfully.";
    } else {
        // Insert new
        $db->insert('part_time_preferences', [
            'student_id' => $student_id,
            'job_sector' => $job_sector,
            'job_type' => $job_type,
            'worked_before' => $worked_before,
            'experience_years' => $experience_years,
            'expected_wage' => $expected_wage,
            'expected_work_hours' => $expected_work_hours,
            'visa_required' => $visa_required,
            'references_text' => $references_text,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $success = "Preferences saved successfully.";
    }
    // Refresh preferences
    $preferences = $db->fetchOne("SELECT * FROM part_time_preferences WHERE student_id = ?", [$student_id]);
}

// Handle job application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_job'])) {
    $job_id = intval($_POST['job_id']);
    
    // Check if already applied
    $existing = $db->fetchOne(
        "SELECT application_id FROM job_applications WHERE job_id = ? AND student_id = ?",
        [$job_id, $student_id]
    );
    
    if (!$existing) {
        $db->insert('job_applications', [
            'job_id' => $job_id,
            'student_id' => $student_id,
            'applied_at' => date('Y-m-d H:i:s'),
            'status' => 'applied'
        ]);
        $apply_success = "Application submitted!";
    } else {
        $apply_error = "You have already applied for this job.";
    }
}

// Get filters
$country_filter = $_GET['country'] ?? '';
$sector_filter = $_GET['sector'] ?? '';
$search = $_GET['search'] ?? '';

// Build jobs query
$sql = "SELECT j.*, c.name as country_name 
        FROM jobs j
        JOIN countries c ON j.country_code = c.iso2
        WHERE j.is_active = 1";
$params = [];

if (!empty($country_filter)) {
    $sql .= " AND j.country_code = ?";
    $params[] = $country_filter;
}
if (!empty($sector_filter)) {
    $sql .= " AND j.sector = ?";
    $params[] = $sector_filter;
}
if (!empty($search)) {
    $sql .= " AND (j.title LIKE ? OR j.company LIKE ? OR j.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
$sql .= " ORDER BY j.posted_date DESC, j.created_at DESC";

$jobs = $db->fetchAll($sql, $params);

// Get list of countries with jobs
$countries_with_jobs = $db->fetchAll("
    SELECT DISTINCT c.iso2, c.name 
    FROM jobs j
    JOIN countries c ON j.country_code = c.iso2
    WHERE j.is_active = 1
    ORDER BY c.name
");

// Get list of sectors present
$sectors = $db->fetchAll("
    SELECT DISTINCT sector FROM jobs WHERE is_active = 1 AND sector IS NOT NULL ORDER BY sector
");

// Get student's job applications
$my_applications = $db->fetchAll("
    SELECT ja.*, j.title, j.company, j.country_code, c.name as country_name, j.website
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    JOIN countries c ON j.country_code = c.iso2
    WHERE ja.student_id = ?
    ORDER BY ja.applied_at DESC
", [$student_id]);

// If student has preferences, we might want to highlight matching jobs (optional)
$matching_jobs = [];
if ($preferences && !empty($preferences['job_sector']) && !empty($preferences['job_type'])) {
    // We'll just filter client-side or show a "recommended" tag
}
?>

<div class="page-header">
    <h1><i class="fas fa-briefcase"></i> Part-Time Jobs</h1>
    <p>Find part-time work that fits your schedule and preferences</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- Preferences Card -->
    <div class="dashboard-card">
        <h3><i class="fas fa-sliders-h"></i> Your Preferences</h3>
        <form method="POST" class="preferences-form">
            <div class="form-group">
                <label for="job_sector">Preferred Sector</label>
                <select name="job_sector" id="job_sector" class="form-control">
                    <option value="">Any</option>
                    <option value="IT" <?php echo ($preferences['job_sector'] ?? '') == 'IT' ? 'selected' : ''; ?>>IT / Technology</option>
                    <option value="Hospitality" <?php echo ($preferences['job_sector'] ?? '') == 'Hospitality' ? 'selected' : ''; ?>>Hospitality</option>
                    <option value="Retail" <?php echo ($preferences['job_sector'] ?? '') == 'Retail' ? 'selected' : ''; ?>>Retail</option>
                    <option value="Education" <?php echo ($preferences['job_sector'] ?? '') == 'Education' ? 'selected' : ''; ?>>Education</option>
                    <option value="Healthcare" <?php echo ($preferences['job_sector'] ?? '') == 'Healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                    <option value="Other" <?php echo ($preferences['job_sector'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="job_type">Job Type</label>
                <select name="job_type" id="job_type" class="form-control">
                    <option value="">Any</option>
                    <option value="part-time" <?php echo ($preferences['job_type'] ?? '') == 'part-time' ? 'selected' : ''; ?>>Part-Time</option>
                    <option value="full-time" <?php echo ($preferences['job_type'] ?? '') == 'full-time' ? 'selected' : ''; ?>>Full-Time</option>
                    <option value="internship" <?php echo ($preferences['job_type'] ?? '') == 'internship' ? 'selected' : ''; ?>>Internship</option>
                    <option value="contract" <?php echo ($preferences['job_type'] ?? '') == 'contract' ? 'selected' : ''; ?>>Contract</option>
                </select>
            </div>
            <div class="form-group">
                <label for="experience_years">Years of Experience</label>
                <input type="number" step="0.1" name="experience_years" id="experience_years" class="form-control" value="<?php echo $preferences['experience_years'] ?? 0; ?>">
            </div>
            <div class="form-group">
                <label for="expected_wage">Expected Hourly Wage (<?php echo $preferences['currency'] ?? 'USD'; ?>)</label>
                <input type="number" step="0.01" name="expected_wage" id="expected_wage" class="form-control" value="<?php echo $preferences['expected_wage'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label for="expected_work_hours">Hours per Week</label>
                <input type="number" name="expected_work_hours" id="expected_work_hours" class="form-control" value="<?php echo $preferences['expected_work_hours'] ?? ''; ?>">
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="worked_before" value="1" <?php echo ($preferences['worked_before'] ?? 0) ? 'checked' : ''; ?>>
                    I have worked before
                </label>
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="visa_required" value="1" <?php echo ($preferences['visa_required'] ?? 0) ? 'checked' : ''; ?>>
                    I need visa sponsorship
                </label>
            </div>
            <div class="form-group">
                <label for="references_text">References (optional)</label>
                <textarea name="references_text" id="references_text" class="form-control" rows="3"><?php echo htmlspecialchars($preferences['references_text'] ?? ''); ?></textarea>
            </div>
            <button type="submit" name="update_preferences" class="btn btn-primary btn-block">
                <i class="fas fa-save"></i> Save Preferences
            </button>
        </form>
    </div>

    <!-- Job Listings Card -->
    <div class="dashboard-card" style="grid-column: span 2;">
        <h3><i class="fas fa-list"></i> Available Jobs</h3>
        
        <!-- Filters -->
        <div class="filters-bar" style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <form method="GET" class="filters-form" style="display: flex; gap: 0.5rem; flex-wrap: wrap; width: 100%;">
                <select name="country" class="form-control" style="width: auto;">
                    <option value="">All Countries</option>
                    <?php foreach ($countries_with_jobs as $c): ?>
                        <option value="<?php echo $c['iso2']; ?>" <?php echo $country_filter == $c['iso2'] ? 'selected' : ''; ?>>
                            <?php echo $c['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="sector" class="form-control" style="width: auto;">
                    <option value="">All Sectors</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?php echo $s['sector']; ?>" <?php echo $sector_filter == $s['sector'] ? 'selected' : ''; ?>>
                            <?php echo $s['sector']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="text" name="search" class="form-control" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="jobs.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
        
        <?php if (empty($jobs)): ?>
            <div class="empty-state">
                <i class="fas fa-briefcase fa-3x"></i>
                <h4>No jobs found</h4>
                <p>Try adjusting your filters or check back later.</p>
            </div>
        <?php else: ?>
            <div class="jobs-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
                <?php foreach ($jobs as $job): 
                    // Check if already applied
                    $applied = $db->fetchOne(
                        "SELECT application_id FROM job_applications WHERE job_id = ? AND student_id = ?",
                        [$job['job_id'], $student_id]
                    );
                ?>
                    <div class="job-card" style="border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem; background: white;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h4 style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($job['title']); ?></h4>
                            <?php if ($applied): ?>
                                <span class="badge" style="background: #28a745; color: white;">Applied</span>
                            <?php endif; ?>
                        </div>
                        
                        <p><strong><?php echo htmlspecialchars($job['company']); ?></strong> • <?php echo htmlspecialchars($job['country_name']); ?></p>
                        
                        <div style="margin: 0.5rem 0; font-size: 0.9em; color: #666;">
                            <?php if ($job['job_type']): ?>
                                <span><i class="fas fa-clock"></i> <?php echo ucfirst($job['job_type']); ?></span>
                            <?php endif; ?>
                            <?php if ($job['sector']): ?>
                                <span style="margin-left: 0.5rem;"><i class="fas fa-tag"></i> <?php echo $job['sector']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($job['description']): ?>
                            <p style="font-size: 0.9em; color: #555;"><?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 150))); ?>...</p>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                            <?php if (!$applied): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                    <button type="submit" name="apply_job" class="btn btn-primary btn-small">
                                        <i class="fas fa-paper-plane"></i> Apply
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($job['website']): ?>
                                <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank" class="btn btn-secondary btn-small">
                                    <i class="fas fa-external-link-alt"></i> Website
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($job['application_deadline']): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.8em; color: #dc3545;">
                                <i class="fas fa-hourglass-end"></i> Deadline: <?php echo Helpers::formatDate($job['application_deadline'], 'M d, Y'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- My Applications Section -->
<div class="dashboard-card" style="margin-top: 2rem;">
    <h3><i class="fas fa-history"></i> My Job Applications</h3>
    <?php if (empty($my_applications)): ?>
        <p class="text-muted">You haven't applied for any jobs yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Job Title</th>
                    <th>Company</th>
                    <th>Country</th>
                    <th>Applied On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_applications as $app): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['title']); ?></td>
                        <td><?php echo htmlspecialchars($app['company']); ?></td>
                        <td><?php echo $app['country_name']; ?></td>
                        <td><?php echo Helpers::formatDate($app['applied_at']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($app['status']); ?>">
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($app['website']): ?>
                                <a href="<?php echo htmlspecialchars($app['website']); ?>" target="_blank" class="btn btn-small">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.job-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.job-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.preferences-form .checkbox {
    margin: 0.5rem 0;
}
.preferences-form .checkbox label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
}
</style>

<script>
// Optional: add frontend filtering if needed
document.addEventListener('DOMContentLoaded', function() {
    // If you want to implement AJAX for filtering, but we already use server-side.
});
</script>

<?php require_once '../../app/templates/footer.php'; ?>