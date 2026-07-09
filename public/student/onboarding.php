<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Complete Your Profile";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

$step = $_GET['step'] ?? 1;
$total_steps = 6;

$student = $db->fetchOne("SELECT * FROM students WHERE student_id = ?", [$student_id]);
if (!$student) {
    $db->insert('students', [
        'user_id' => $user['user_id'],
        'degree_level_id' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $student_id = $db->getConnection()->lastInsertId();
    $student = $db->fetchOne("SELECT * FROM students WHERE student_id = ?", [$student_id]);
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch($step) {
        case 1: 
            $first_name = Helpers::sanitize($_POST['first_name']);
            $last_name = Helpers::sanitize($_POST['last_name']);
            $gender = Helpers::sanitize($_POST['gender']);
            $dob = Helpers::sanitize($_POST['dob']);
            $nationality = Helpers::sanitize($_POST['nationality']);
            
            $db->update('user_profiles', [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'gender' => $gender,
                'dob' => $dob,
                'nationality' => $nationality,
                'updated_at' => date('Y-m-d H:i:s')
            ], "user_id = " . $user['user_id']);
            break;
            
        case 2: 
            $level = Helpers::sanitize($_POST['level']);
            $institution_name = Helpers::sanitize($_POST['institution_name']);
            $country_code = Helpers::sanitize($_POST['country_code']);
            $degree_name = Helpers::sanitize($_POST['degree_name']);
            $gpa = floatval($_POST['gpa']);
            $start_date = Helpers::sanitize($_POST['start_date']);
            $end_date = Helpers::sanitize($_POST['end_date']);
            
            $db->insert('education_records', [
                'student_id' => $student_id,
                'level' => $level,
                'institution_name' => $institution_name,
                'country_code' => $country_code,
                'degree_name' => $degree_name,
                'gpa' => $gpa,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 3: 
            $test_type_id = intval($_POST['test_type_id']);
            $test_date = Helpers::sanitize($_POST['test_date']);
            $overall_score = floatval($_POST['overall_score']);
            
            $db->insert('test_scores', [
                'student_id' => $student_id,
                'test_type_id' => $test_type_id,
                'test_date' => $test_date,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $score_id = $db->getConnection()->lastInsertId();
            
            $db->insert('test_score_sections', [
                'score_id' => $score_id,
                'section_name' => 'Overall',
                'score_value' => $overall_score,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 4: 
            $degree_level_id = intval($_POST['degree_level_id']);
            $country_from = Helpers::sanitize($_POST['country_from']);
            $budget_min = floatval($_POST['budget_min']);
            $budget_max = floatval($_POST['budget_max']);
            $subject_area_id = intval($_POST['subject_area_id']);
            $preferred_countries = $_POST['preferred_countries'] ?? [];
            
            $db->update('students', [
                'degree_level_id' => $degree_level_id,
                'country_from' => $country_from,
                'budget_min' => $budget_min,
                'budget_max' => $budget_max,
                'subject_area_id' => $subject_area_id,
                'updated_at' => date('Y-m-d H:i:s')
            ], "student_id = $student_id");
            
            $db->query("DELETE FROM student_preferred_countries WHERE student_id = ?", [$student_id]);
            
            foreach ($preferred_countries as $country_code) {
                $db->insert('student_preferred_countries', [
                    'student_id' => $student_id,
                    'country_code' => $country_code,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 5: 
            break;
            
        case 6: 
            $success = 'Profile completed! You can now start applying to universities.';
            break;
    }
    
    if ($step < $total_steps) {
        $step++;
    }
}

$countries = Helpers::getCountries();
$degree_levels = Helpers::getDegreeLevels();
$test_types = Helpers::getTestTypes();
$subjects = $db->fetchAll("SELECT * FROM subjects ORDER BY name");

$profile = $db->fetchOne("SELECT * FROM user_profiles WHERE user_id = ?", [$user['user_id']]);
?>

<div class="onboarding-container">
    <div class="onboarding-header">
        <h1>Complete Your Student Profile</h1>
        <p>Step <?php echo $step; ?> of <?php echo $total_steps; ?></p>
        
        <div class="progress-bar">
            <?php for($i = 1; $i <= $total_steps; $i++): ?>
                <div class="progress-step <?php echo $i <= $step ? 'active' : ''; ?>"></div>
            <?php endfor; ?>
        </div>
        
        <div class="step-titles">
            <span>Personal Info</span>
            <span>Education</span>
            <span>Test Scores</span>
            <span>Preferences</span>
            <span>Match</span>
            <span>Complete</span>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="onboarding-card">
        <form method="POST" action="?step=<?php echo $step; ?>">
            <?php switch($step): 
                case 1: ?>
                    <h3>Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required 
                                   value="<?php echo $profile ? $profile['first_name'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required 
                                   value="<?php echo $profile ? $profile['last_name'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select</option>
                                <option value="male" <?php echo ($profile && $profile['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($profile && $profile['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($profile && $profile['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" 
                                   value="<?php echo $profile ? $profile['dob'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nationality</label>
                        <select name="nationality">
                            <option value="">Select Country</option>
                            <?php foreach($countries as $country): ?>
                                <option value="<?php echo $country['iso2']; ?>"
                                    <?php echo ($profile && $profile['nationality'] == $country['iso2']) ? 'selected' : ''; ?>>
                                    <?php echo $country['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php break;
                    
                case 2: ?>
                    <h3>Educational Background</h3>
                    <div class="form-group">
                        <label>Level of Education *</label>
                        <select name="level" required>
                            <option value="">Select Level</option>
                            <option value="High School">High School</option>
                            <option value="UG">Undergraduate</option>
                            <option value="GRAD">Graduate</option>
                            <option value="PHD">PhD</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Institution Name *</label>
                        <input type="text" name="institution_name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Country</label>
                            <select name="country_code">
                                <option value="">Select Country</option>
                                <?php foreach($countries as $country): ?>
                                    <option value="<?php echo $country['iso2']; ?>">
                                        <?php echo $country['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Degree Name</label>
                            <input type="text" name="degree_name" placeholder="e.g., BSc Computer Science">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>GPA (out of 4.0)</label>
                            <input type="number" name="gpa" min="0" max="4" step="0.01" placeholder="e.g., 3.5">
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date">
                        </div>
                        <div class="form-group">
                            <label>End Date (or expected)</label>
                            <input type="date" name="end_date">
                        </div>
                    </div>
                    <?php break;
                    
                case 3: ?>
                    <h3>Test Scores</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Test Type *</label>
                            <select name="test_type_id" required>
                                <option value="">Select Test</option>
                                <?php foreach($test_types as $test): ?>
                                    <option value="<?php echo $test['test_type_id']; ?>">
                                        <?php echo $test['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Test Date</label>
                            <input type="date" name="test_date">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Overall Score</label>
                        <input type="number" name="overall_score" step="0.1" placeholder="e.g., 7.5">
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Score Report (Optional)</label>
                        <input type="file" name="score_report" accept=".pdf,.jpg,.png">
                        <small>You can also upload later in Documents section</small>
                    </div>
                    <?php break;
                    
                case 4: ?>
                    <h3>Study Preferences</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Desired Degree Level *</label>
                            <select name="degree_level_id" required>
                                <option value="">Select Level</option>
                                <?php foreach($degree_levels as $level): ?>
                                    <option value="<?php echo $level['degree_level_id']; ?>"
                                        <?php echo ($student && $student['degree_level_id'] == $level['degree_level_id']) ? 'selected' : ''; ?>>
                                        <?php echo $level['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Country of Origin</label>
                            <select name="country_from">
                                <option value="">Select Country</option>
                                <?php foreach($countries as $country): ?>
                                    <option value="<?php echo $country['iso2']; ?>"
                                        <?php echo ($student && $student['country_from'] == $country['iso2']) ? 'selected' : ''; ?>>
                                        <?php echo $country['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Budget Minimum ($)</label>
                            <input type="number" name="budget_min" min="0" step="1000"
                                   value="<?php echo $student ? $student['budget_min'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Budget Maximum ($)</label>
                            <input type="number" name="budget_max" min="0" step="1000"
                                   value="<?php echo $student ? $student['budget_max'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject Area of Interest</label>
                        <select name="subject_area_id">
                            <option value="">Select Subject</option>
                            <?php foreach($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_id']; ?>"
                                    <?php echo ($student && $student['subject_area_id'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                    <?php echo $subject['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Preferred Study Countries (Select multiple)</label>
                        <select name="preferred_countries[]" multiple size="5">
                            <?php foreach($countries as $country): ?>
                                <option value="<?php echo $country['iso2']; ?>">
                                    <?php echo $country['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Hold Ctrl (Cmd on Mac) to select multiple countries</small>
                    </div>
                    <?php break;
                    
                case 5: ?>
                    <h3>Get University Matches</h3>
                    <p>Based on your profile, we'll find the best universities for you.</p>
                    
                    <?php
                    require_once '../../app/includes/match_engine.php';
                    $matchEngine = new MatchEngine();
                    $recommendations = $matchEngine->getTopRecommendations($student_id, 5);
                    
                    if (!empty($recommendations)): ?>
                        <div class="recommendations">
                            <h4>Top Matches for You:</h4>
                            <?php foreach($recommendations as $rec): ?>
                                <div class="recommendation-card">
                                    <div class="match-score">
                                        <div class="score-circle" style="background: <?php echo getScoreColor($rec['match_score']); ?>">
                                            <?php echo $rec['match_score']; ?>%
                                        </div>
                                        <span><?php echo $rec['match_category']; ?></span>
                                    </div>
                                    <h5><?php echo htmlspecialchars($rec['program_name']); ?></h5>
                                    <p><?php echo htmlspecialchars($rec['university_name']); ?></p>
                                    <p><i class="fas fa-money-bill"></i> $<?php echo number_format($rec['tuition_fee']); ?></p>
                                    <p><i class="fas fa-calendar"></i> Deadline: <?php echo Helpers::formatDate($rec['application_deadline']); ?></p>
                                    <a href="applications.php?program_id=<?php echo $rec['program_id']; ?>" class="btn btn-small">Apply Now</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Complete more information to get better matches.</p>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="receive_updates" checked>
                            Receive updates about new matching programs
                        </label>
                    </div>
                    <?php break;
                    
                case 6: ?>
                    <div class="completion-step">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Profile Completed!</h3>
                        <p>Your student profile is now complete. You can:</p>
                        <div class="completion-actions">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Go to Dashboard
                            </a>
                            <a href="applications.php" class="btn btn-secondary">
                                <i class="fas fa-file-alt"></i> Start Applications
                            </a>
                            <a href="recommendations.php" class="btn btn-secondary">
                                <i class="fas fa-star"></i> View More Matches
                            </a>
                        </div>
                        
                        <div class="next-steps">
                            <h4>Next Steps:</h4>
                            <ul>
                                <li><i class="fas fa-check"></i> Upload your documents</li>
                                <li><i class="fas fa-check"></i> Browse recommended programs</li>
                                <li><i class="fas fa-check"></i> Start your applications</li>
                                <li><i class="fas fa-check"></i> Explore scholarship options</li>
                            </ul>
                        </div>
                    </div>
                    <?php break;
            endswitch; ?>
            
            <?php if ($step < 6): ?>
                <div class="form-navigation">
                    <?php if ($step > 1): ?>
                        <a href="?step=<?php echo $step - 1; ?>" class="btn btn-secondary">Previous</a>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo $step == 5 ? 'Complete Profile' : 'Next Step'; ?>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php
function getScoreColor($score) {
    if ($score >= 90) return '#4CAF50';
    if ($score >= 80) return '#8BC34A';
    if ($score >= 70) return '#FFC107';
    if ($score >= 60) return '#FF9800';
    return '#F44336';
}
?>

<style>
.onboarding-container {
    max-width: 800px;
    margin: 0 auto;
}

.onboarding-header {
    text-align: center;
    margin-bottom: 2rem;
}

.progress-bar {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin: 20px 0;
}

.progress-step {
    width: 40px;
    height: 8px;
    background: #ddd;
    border-radius: 4px;
    transition: background 0.3s;
}

.progress-step.active {
    background: #3498db;
}

.step-titles {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #666;
    margin-top: 10px;
}

.onboarding-card {
    background: white;
    border-radius: 8px;
    padding: 2rem;
    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
}

.form-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.recommendations {
    margin: 1rem 0;
}

.recommendation-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 4px solid #3498db;
    position: relative;
}

.match-score {
    position: absolute;
    top: 10px;
    right: 10px;
    text-align: center;
}

.score-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto 5px;
}

.completion-step {
    text-align: center;
    padding: 2rem;
}

.success-icon {
    font-size: 4rem;
    color: #4CAF50;
    margin-bottom: 1rem;
}

.completion-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin: 2rem 0;
    flex-wrap: wrap;
}

.next-steps {
    text-align: left;
    max-width: 400px;
    margin: 2rem auto;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.next-steps ul {
    list-style: none;
    padding: 0;
}

.next-steps li {
    padding: 5px 0;
}

.next-steps li i {
    color: #4CAF50;
    margin-right: 10px;
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>