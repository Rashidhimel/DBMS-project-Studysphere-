<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Program Recommendations";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();


if (file_exists('../../app/includes/match_engine.php')) {
    require_once '../../app/includes/match_engine.php';
    $match_engine = new MatchEngine();
    $recommendations = $match_engine->getTopRecommendations($student_id, 10);
} else {
    $recommendations = [];
}
?>

<div class="page-header">
    <h1><i class="fas fa-star"></i> Program Recommendations</h1>
    <p>Personalized program suggestions based on your profile</p>
</div>

<?php if (empty($recommendations)): ?>
<div class="card">
    <div class="alert alert-info">
        <h3>Complete Your Profile</h3>
        <p>To get personalized program recommendations, please complete your profile information:</p>
        <ul>
            <li><a href="profile.php">Add your personal details</a></li>
            <li><a href="education.php">Add your education history</a></li>
            <li><a href="tests.php">Add your test scores</a></li>
            <li><a href="profile.php">Set your budget preferences</a></li>
        </ul>
        <p>Once your profile is complete, we'll show you programs that match your profile.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <h3>Top Program Recommendations for You</h3>
    
    <table class="table">
        <thead>
            <tr>
                <th>Program</th>
                <th>University</th>
                <th>Match Score</th>
                <th>Tuition</th>
                <th>Deadline</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recommendations as $rec): ?>
            <tr>
                <td>
                    <strong><?php echo $rec['program_name']; ?></strong><br>
                    <small><?php echo $rec['degree_level']; ?></small>
                </td>
                <td><?php echo $rec['university_name']; ?></td>
                <td>
                    <span class="status-badge" style="
                        background: <?php 
                            if ($rec['match_score'] >= 90) echo '#27ae60';
                            elseif ($rec['match_score'] >= 80) echo '#2ecc71';
                            elseif ($rec['match_score'] >= 70) echo '#3498db';
                            elseif ($rec['match_score'] >= 60) echo '#f39c12';
                            else echo '#e74c3c';
                        ?>;
                        color: white;
                    ">
                        <?php echo $rec['match_score']; ?>%
                    </span><br>
                    <small><?php echo $rec['match_category']; ?></small>
                </td>
                <td>
                    <?php if ($rec['tuition_fee']): ?>
                        $<?php echo number_format($rec['tuition_fee']); ?> <?php echo $rec['currency']; ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rec['application_deadline']): ?>
                        <?php echo Helpers::formatDate($rec['application_deadline']); ?>
                    <?php else: ?>
                        Rolling
                    <?php endif; ?>
                </td>
                <td>
                    <a href="applications.php?program_id=<?php echo $rec['program_id']; ?>" 
                       class="btn btn-small btn-primary">Apply</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>How Recommendations Work</h3>
    <p>Our matching algorithm considers multiple factors from your profile:</p>
    <ul>
        <li>Your budget preferences vs program tuition</li>
        <li>Your test scores vs program requirements</li>
        <li>Your GPA vs program requirements</li>
        <li>Your preferred countries</li>
        <li>Your degree level preference</li>
        <li>Your subject interests</li>
    </ul>
    <p><strong>Tip:</strong> Complete all sections of your profile for the most accurate recommendations.</p>
</div>
<?php endif; ?>

<?php require_once '../../app/templates/footer.php'; ?>