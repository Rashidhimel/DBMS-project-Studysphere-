<?php
require_once '../../app/includes/init.php';
requireStudent();

$student_id = $_GET['student_id'] ?? 0;
$program_id = $_GET['program_id'] ?? 0;

if (!$student_id || !$program_id) {
    die("Invalid request");
}

require_once '../../app/includes/match_engine.php';
$matchEngine = new MatchEngine();

$breakdown = $matchEngine->getMatchBreakdown($student_id, $program_id);
$program = $matchEngine->getProgramDetails($program_id); 

if (!$breakdown || !$program) {
    die("Could not generate match breakdown");
}


$total_score = 0;
foreach ($breakdown as $criterion => $data) {
    $total_score += $data['score'] * $data['weight'];
}
$total_score = min(100, max(0, round($total_score)));
?>

<div class="match-breakdown">
    <h4>Match Analysis: <?php echo htmlspecialchars($program['program_name']); ?></h4>
    <p><?php echo htmlspecialchars($program['university_name']); ?></p>
    
    <div class="total-score">
        <div class="score-circle-large" style="background: <?php echo getScoreColor($total_score); ?>">
            <?php echo $total_score; ?>%
        </div>
        <p>Overall Match Score</p>
    </div>
    
    <div class="breakdown-details">
        <?php foreach ($breakdown as $criterion => $data): 
            $criterion_name = str_replace('_', ' ', ucwords($criterion));
            $percentage = round($data['score']);
            $weight_percentage = round($data['weight'] * 100);
        ?>
        <div class="criterion-row">
            <div class="criterion-info">
                <span class="criterion-name"><?php echo $criterion_name; ?></span>
                <span class="criterion-weight">Weight: <?php echo $weight_percentage; ?>%</span>
            </div>
            
            <div class="score-bar-container">
                <div class="score-bar" style="width: <?php echo $percentage; ?>%; 
                    background: <?php echo getScoreColor($data['score']); ?>">
                    <span class="score-text"><?php echo $percentage; ?>%</span>
                </div>
            </div>
            
            <div class="criterion-description">
                <?php echo $data['description']; ?>
            </div>
        </div>
        <?php endforeach; ?>
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
.match-breakdown {
    padding: 1rem;
}

.total-score {
    text-align: center;
    margin: 1.5rem 0;
}

.score-circle-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    margin: 0 auto 10px;
}

.breakdown-details {
    margin-top: 2rem;
}

.criterion-row {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.criterion-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.criterion-name {
    font-weight: 600;
    color: #2c3e50;
}

.criterion-weight {
    color: #666;
    font-size: 0.9rem;
}

.score-bar-container {
    width: 100%;
    height: 24px;
    background: #f0f0f0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 8px;
    position: relative;
}

.score-bar {
    height: 100%;
    transition: width 0.5s ease;
    position: relative;
}

.score-text {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}

.criterion-description {
    color: #555;
    font-size: 0.9rem;
    font-style: italic;
}
</style>