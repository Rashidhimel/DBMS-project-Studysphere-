<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "University Recommendations";
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

require_once '../../app/includes/match_engine.php';
$matchEngine = new MatchEngine();

// Get recommendations (you can adjust limit)
$recommendations = $matchEngine->getTopRecommendations($student_id, 50);

// Get student profile with all needed fields
$student = $matchEngine->getStudentProfile($student_id);
if (!$student) {
    $student = [];
}

// Get country name for display if needed
if (!empty($student['country_from'])) {
    $country = $db->fetchOne("SELECT name FROM countries WHERE iso2 = ?", [$student['country_from']]);
    $student['country_name'] = $country ? $country['name'] : $student['country_from'];
}
?>

<div class="page-header">
    <h1><i class="fas fa-star"></i> University Recommendations</h1>
    <p class="lead">Personalized program matches based on your profile and preferences</p>
</div>

<?php if (empty($student) || empty($student['budget_min'])): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <h4><i class="fas fa-info-circle"></i> Complete Your Profile</h4>
        <p>To get the most accurate recommendations, please complete your student profile first.</p>
        <a href="onboarding.php" class="btn btn-primary">Complete Profile Now</a>
    </div>
<?php endif; ?>

<div class="recommendations-layout">
    <!-- Sidebar Filters -->
    <aside class="filters-sidebar">
        <div class="profile-summary">
            <h4><i class="fas fa-user-graduate"></i> Your Profile</h4>
            <div class="summary-item">
                <span class="label">Degree Level:</span>
                <span class="value"><?php echo $student['degree_level_name'] ?? 'Not set'; ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Country:</span>
                <span class="value"><?php echo $student['country_name'] ?? 'Not set'; ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Budget:</span>
                <span class="value">
                    <?php 
                    if (!empty($student['budget_min']) || !empty($student['budget_max'])) {
                        echo '$' . number_format($student['budget_min'] ?? 0) . ' - $' . number_format($student['budget_max'] ?? 0);
                    } else {
                        echo 'Not set';
                    }
                    ?>
                </span>
            </div>
            <div class="summary-item">
                <span class="label">Subject Interest:</span>
                <span class="value"><?php echo $student['subject_name'] ?? 'Not set'; ?></span>
            </div>
        </div>

        <div class="filters">
            <h4><i class="fas fa-sliders-h"></i> Filters</h4>
            <form id="filterForm">
                <div class="form-group">
                    <label for="scoreRange">Minimum Match Score: <span id="scoreValue">70%</span></label>
                    <input type="range" id="scoreRange" min="50" max="100" value="70" step="1" class="slider">
                </div>

                <div class="form-group">
                    <label for="maxTuition">Maximum Tuition ($)</label>
                    <input type="number" id="maxTuition" class="form-control" placeholder="Any" min="0">
                </div>

                <div class="form-group">
                    <label for="degreeLevel">Degree Level</label>
                    <select id="degreeLevel" class="form-control">
                        <option value="">All Levels</option>
                        <?php 
                        $levels = Helpers::getDegreeLevels();
                        foreach($levels as $level): ?>
                            <option value="<?php echo $level['degree_level_id']; ?>">
                                <?php echo $level['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="button" onclick="applyFilters()" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <button type="button" onclick="resetFilters()" class="btn btn-secondary">
                        <i class="fas fa-redo-alt"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="recommendations-content">
        <div class="results-header">
            <h3>Recommended Programs <span class="badge" id="resultCount"><?php echo count($recommendations); ?> found</span></h3>
            <div class="sort-options">
                <select id="sortBy" onchange="sortRecommendations()" class="form-control">
                    <option value="score">Sort by Match Score</option>
                    <option value="tuition">Sort by Tuition (low to high)</option>
                    <option value="deadline">Sort by Deadline (earliest first)</option>
                </select>
            </div>
        </div>

        <?php if (empty($recommendations)): ?>
            <div class="empty-state">
                <i class="fas fa-search fa-4x"></i>
                <h4>No matching programs found</h4>
                <p>Try adjusting your profile preferences or filters to see more options.</p>
                <a href="profile.php" class="btn btn-primary">Update Profile</a>
            </div>
        <?php else: ?>
            <div class="recommendations-grid" id="recommendationsGrid">
                <?php foreach($recommendations as $rec): 
                    // Pre‑compute country name for display
                    $countryName = $db->fetchOne("SELECT name FROM countries WHERE iso2 = ?", [$rec['country_code']])['name'] ?? $rec['country_code'];
                    $score = $rec['match_score'];
                    $scoreColor = $score >= 90 ? '#4CAF50' : ($score >= 80 ? '#8BC34A' : ($score >= 70 ? '#FFC107' : ($score >= 60 ? '#FF9800' : '#F44336')));
                ?>
                    <div class="recommendation-card" 
                         data-score="<?php echo $score; ?>"
                         data-tuition="<?php echo $rec['tuition_fee'] ?? 0; ?>"
                         data-degree="<?php echo $rec['degree_level_id'] ?? ''; ?>"
                         data-deadline="<?php echo $rec['application_deadline'] ?? ''; ?>">
                        
                        <div class="card-header">
                            <div class="match-score" style="background: <?php echo $scoreColor; ?>;">
                                <?php echo $score; ?>%
                            </div>
                            <span class="match-category"><?php echo $rec['match_category']; ?></span>
                        </div>

                        <div class="card-body">
                            <h4><?php echo htmlspecialchars($rec['program_name']); ?></h4>
                            <p class="university"><i class="fas fa-university"></i> <?php echo htmlspecialchars($rec['university_name']); ?></p>

                            <div class="program-meta">
                                <div class="meta-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span><?php echo $rec['degree_level']; ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>
                                        <?php if(!empty($rec['tuition_fee'])): ?>
                                            $<?php echo number_format($rec['tuition_fee']); ?> <?php echo $rec['currency']; ?>
                                        <?php else: ?>
                                            Tuition not specified
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>
                                        <?php if(!empty($rec['application_deadline'])): ?>
                                            Deadline: <?php echo Helpers::formatDate($rec['application_deadline'], 'M j, Y'); ?>
                                        <?php else: ?>
                                            Rolling admission
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-globe-americas"></i>
                                    <span><?php echo $countryName; ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <a href="application_view.php?program_id=<?php echo $rec['program_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-file-alt"></i> Apply
                                </a>
                                <a href="program_details.php?id=<?php echo $rec['program_id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-info-circle"></i> Details
                                </a>
                                <button class="btn btn-icon" onclick="showMatchBreakdown(<?php echo $student_id; ?>, <?php echo $rec['program_id']; ?>)" title="Match breakdown">
                                    <i class="fas fa-chart-pie"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Match Breakdown Modal -->
<div id="matchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Match Score Breakdown</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="matchDetails">
            <div class="text-center p-4">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Loading breakdown...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Helper for score color (used only for the badge)
function getScoreColor(score) {
    if (score >= 90) return '#4CAF50';
    if (score >= 80) return '#8BC34A';
    if (score >= 70) return '#FFC107';
    if (score >= 60) return '#FF9800';
    return '#F44336';
}

// Filtering
function applyFilters() {
    const minScore = parseInt(document.getElementById('scoreRange').value);
    const maxTuition = document.getElementById('maxTuition').value;
    const degreeLevel = document.getElementById('degreeLevel').value;
    
    const cards = document.querySelectorAll('.recommendation-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const score = parseInt(card.dataset.score);
        const tuition = parseFloat(card.dataset.tuition) || 0;
        const degree = card.dataset.degree;
        
        let show = true;
        
        if (score < minScore) show = false;
        if (maxTuition && tuition > parseFloat(maxTuition)) show = false;
        if (degreeLevel && degree !== degreeLevel) show = false;
        
        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });
    
    document.getElementById('resultCount').textContent = visibleCount + ' found';
}

function resetFilters() {
    document.getElementById('scoreRange').value = 70;
    document.getElementById('scoreValue').textContent = '70%';
    document.getElementById('maxTuition').value = '';
    document.getElementById('degreeLevel').value = '';
    
    const cards = document.querySelectorAll('.recommendation-card');
    cards.forEach(card => card.style.display = 'block');
    document.getElementById('resultCount').textContent = cards.length + ' found';
}

// Sorting
function sortRecommendations() {
    const sortBy = document.getElementById('sortBy').value;
    const container = document.getElementById('recommendationsGrid');
    const cards = Array.from(container.querySelectorAll('.recommendation-card'));
    
    cards.sort((a, b) => {
        if (sortBy === 'score') {
            return parseInt(b.dataset.score) - parseInt(a.dataset.score);
        } else if (sortBy === 'tuition') {
            const aTuition = parseFloat(a.dataset.tuition) || Infinity;
            const bTuition = parseFloat(b.dataset.tuition) || Infinity;
            return aTuition - bTuition;
        } else if (sortBy === 'deadline') {
            const aDate = a.dataset.deadline ? new Date(a.dataset.deadline) : new Date('9999-12-31');
            const bDate = b.dataset.deadline ? new Date(b.dataset.deadline) : new Date('9999-12-31');
            return aDate - bDate;
        }
        return 0;
    });
    
    // Re‑append sorted cards
    cards.forEach(card => container.appendChild(card));
}

// Match breakdown modal
function showMatchBreakdown(studentId, programId) {
    const modal = document.getElementById('matchModal');
    const details = document.getElementById('matchDetails');
    
    // Show loading
    details.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading breakdown...</p></div>';
    modal.style.display = 'flex';
    
    // Fetch breakdown (ensure match_breakdown.php exists or adjust URL)
    fetch(`match_breakdown.php?student_id=${studentId}&program_id=${programId}`)
        .then(response => response.text())
        .then(html => {
            details.innerHTML = html;
        })
        .catch(error => {
            details.innerHTML = '<div class="alert alert-error">Failed to load breakdown. Please try again.</div>';
        });
}

function closeModal() {
    document.getElementById('matchModal').style.display = 'none';
}

// Update displayed score value
document.getElementById('scoreRange').addEventListener('input', function() {
    document.getElementById('scoreValue').textContent = this.value + '%';
});

// Close modal on outside click
window.addEventListener('click', function(event) {
    const modal = document.getElementById('matchModal');
    if (event.target === modal) {
        closeModal();
    }
});
</script>

<style>
/* Layout */
.recommendations-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    margin-top: 1.5rem;
}

/* Sidebar */
.filters-sidebar {
    position: sticky;
    top: 2rem;
    height: fit-content;
}

.profile-summary, .filters {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    border: 1px solid #f0f0f0;
    transition: box-shadow 0.2s;
}
.profile-summary:hover, .filters:hover {
    box-shadow: 0 6px 18px rgba(0,0,0,0.05);
}

.profile-summary h4, .filters h4 {
    margin-top: 0;
    margin-bottom: 1.2rem;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #2c3e50;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.7rem;
    font-size: 0.95rem;
}
.summary-item .label {
    color: #7f8c8d;
    font-weight: 500;
}
.summary-item .value {
    color: #2c3e50;
    font-weight: 600;
    text-align: right;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1.5rem;
}
.filter-actions .btn {
    flex: 1;
    padding: 0.6rem;
}

/* Results Header */
.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}
.results-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}
.badge {
    background: #eef2f6;
    color: #2c3e50;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 0.5rem;
}
.sort-options select {
    width: 200px;
}

/* Recommendations Grid */
.recommendations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.8rem;
}

.recommendation-card {
    background: white;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0,0,0,0.03);
    transition: all 0.25s ease;
    border: 1px solid #f0f0f0;
    display: flex;
    flex-direction: column;
}
.recommendation-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 30px rgba(0,0,0,0.08);
    border-color: #e0e0e0;
}

.card-header {
    padding: 1.2rem 1.5rem;
    background: #f9fafc;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.match-score {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.match-category {
    font-size: 0.85rem;
    color: #5d6d7e;
    background: #eef2f6;
    padding: 0.3rem 1rem;
    border-radius: 30px;
}

.card-body {
    padding: 1.5rem;
    flex: 1;
}
.card-body h4 {
    margin: 0 0 0.3rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    line-height: 1.3;
}
.university {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    color: #5d6d7e;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.university i {
    color: #667eea;
    font-size: 0.9rem;
}

.program-meta {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    margin: 1rem 0;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    font-size: 0.9rem;
    color: #4a5568;
}
.meta-item i {
    width: 18px;
    color: #667eea;
    font-size: 0.9rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1.2rem;
    flex-wrap: wrap;
}
.card-actions .btn {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    border-radius: 30px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: linear-gradient(145deg, #667eea, #764ba2);
    color: white;
    border: none;
}
.btn-primary:hover {
    transform: scale(1.02);
    box-shadow: 0 6px 14px rgba(102,126,234,0.3);
}
.btn-outline {
    background: transparent;
    border: 1px solid #cbd5e0;
    color: #4a5568;
}
.btn-outline:hover {
    background: #f8fafc;
    border-color: #a0aec0;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 1px solid #e2e8f0;
    color: #4a5568;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-icon:hover {
    background: #edf2f7;
    transform: translateY(-2px);
    border-color: #cbd5e0;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}
.empty-state h4 {
    margin-bottom: 0.5rem;
    color: #2c3e50;
}
.empty-state p {
    color: #718096;
    margin-bottom: 1.5rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: white;
    max-width: 600px;
    width: 90%;
    border-radius: 24px;
    box-shadow: 0 30px 60px -15px rgba(0,0,0,0.3);
    animation: modalSlide 0.3s ease;
    max-height: 80vh;
    overflow-y: auto;
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
    font-size: 1.3rem;
}
.close {
    background: none;
    border: none;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    color: #a0aec0;
    transition: color 0.2s;
}
.close:hover {
    color: #718096;
}
.modal-body {
    padding: 2rem;
}
.text-center {
    text-align: center;
}
.p-4 {
    padding: 2rem;
}

/* Responsive */
@media (max-width: 900px) {
    .recommendations-layout {
        grid-template-columns: 1fr;
    }
    .filters-sidebar {
        position: static;
        margin-bottom: 1rem;
    }
    .results-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.8rem;
    }
    .sort-options select {
        width: 100%;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>