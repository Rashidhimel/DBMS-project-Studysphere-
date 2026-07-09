<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Applications";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

$applications = Helpers::getUserApplications($student_id);

// Fetch all universities with their programs, including ranking, program count
$universities = $db->fetchAll("
    SELECT u.*, 
           (SELECT COUNT(*) FROM programs p WHERE p.university_id = u.university_id AND p.deleted_at IS NULL) as program_count
    FROM universities u
    WHERE u.deleted_at IS NULL
");

// For each university, fetch its active programs
foreach ($universities as &$uni) {
    $uni['programs'] = $db->fetchAll("
        SELECT p.*, dl.name as degree_level_name
        FROM programs p
        LEFT JOIN degree_levels dl ON p.degree_level_id = dl.degree_level_id
        WHERE p.university_id = ? AND p.deleted_at IS NULL
        ORDER BY p.program_name
    ", [$uni['university_id']]);
}
unset($uni);

// Check existing applications to disable "Apply" for already applied programs
$existing_applications = $db->fetchAll("
    SELECT program_id FROM applications WHERE student_id = ? AND deleted_at IS NULL
", [$student_id]);
$applied_program_ids = array_column($existing_applications, 'program_id');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_application'])) {
    $program_id = intval($_POST['program_id']);
    
    $program = $db->fetchOne(
        "SELECT p.*, u.university_id, u.name as university_name 
         FROM programs p 
         JOIN universities u ON p.university_id = u.university_id 
         WHERE p.program_id = ?",
        [$program_id]
    );
    
    if ($program) {
        $existing = $db->fetchOne(
            "SELECT application_id FROM applications 
             WHERE student_id = ? AND program_id = ? AND deleted_at IS NULL",
            [$student_id, $program_id]
        );
        
        if ($existing) {
            $error = 'You have already applied to this program.';
        } else {
            $db->insert('applications', [
                'student_id' => $student_id,
                'program_id' => $program_id,
                'university_id' => $program['university_id'],
                'application_status_id' => 1, 
                'applied_at' => date('Y-m-d H:i:s'),
                'deadline' => $program['application_deadline'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'created_by' => $user['user_id']
            ]);
            
            $application_id = $db->getConnection()->lastInsertId();
            $success = 'Application created successfully!';
            $applications = Helpers::getUserApplications($student_id);
            $app_count = $db->fetchOne("SELECT COUNT(*) as count FROM applications WHERE student_id = ?", [$student_id])['count'];
            if ($app_count == 1) {
                Helpers::awardMilestone($student_id, 'first_application');
            }
            // Refresh applied program ids
            $existing_applications = $db->fetchAll("SELECT program_id FROM applications WHERE student_id = ? AND deleted_at IS NULL", [$student_id]);
            $applied_program_ids = array_column($existing_applications, 'program_id');
        }
    } else {
        $error = 'Program not found.';
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> My Applications</h1>
    <p>Browse universities and programs to start a new application</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- University & Program Browser -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-university"></i> Browse Programs by University</h3>
        <div class="sort-controls">
            <span>Sort by:</span>
            <button class="sort-btn active" data-sort="name">Name</button>
            <button class="sort-btn" data-sort="rank">Ranking</button>
            <button class="sort-btn" data-sort="programs">Program Count</button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($universities)): ?>
            <p class="text-muted">No universities available.</p>
        <?php else: ?>
            <div class="university-list" id="university-list">
                <?php foreach ($universities as $uni): 
                    $rank = $uni['qs_ranking'] ?? 999999; // for sorting, higher rank better? We'll sort ascending rank.
                ?>
                    <div class="university-item" 
                         data-name="<?php echo strtolower($uni['name']); ?>"
                         data-rank="<?php echo $uni['qs_ranking'] ?? 999999; ?>"
                         data-programs="<?php echo $uni['program_count']; ?>">
                        <div class="university-header" onclick="toggleUniversity(<?php echo $uni['university_id']; ?>)">
                            <div class="university-info">
                                <h4><?php echo htmlspecialchars($uni['name']); ?></h4>
                                <div class="university-meta">
                                    <?php if ($uni['city']): ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($uni['city']); ?><?php echo $uni['country_code'] ? ', ' . $uni['country_code'] : ''; ?></span>
                                    <?php endif; ?>
                                    <?php if ($uni['qs_ranking']): ?>
                                        <span><i class="fas fa-trophy"></i> QS Rank: <?php echo $uni['qs_ranking']; ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-book"></i> <?php echo $uni['program_count']; ?> programs</span>
                                </div>
                            </div>
                            <div class="university-toggle">
                                <i class="fas fa-chevron-down" id="icon-<?php echo $uni['university_id']; ?>"></i>
                            </div>
                        </div>
                        <div class="university-programs" id="programs-<?php echo $uni['university_id']; ?>" style="display: none;">
                            <?php if (empty($uni['programs'])): ?>
                                <p class="text-muted">No programs listed for this university.</p>
                            <?php else: ?>
                                <div class="programs-grid">
                                    <?php foreach ($uni['programs'] as $prog): 
                                        $already_applied = in_array($prog['program_id'], $applied_program_ids);
                                    ?>
                                        <div class="program-card">
                                            <h5><?php echo htmlspecialchars($prog['program_name']); ?></h5>
                                            <div class="program-details">
                                                <?php if ($prog['degree_level_name']): ?>
                                                    <span><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($prog['degree_level_name']); ?></span>
                                                <?php endif; ?>
                                                <?php if ($prog['duration_months']): ?>
                                                    <span><i class="fas fa-clock"></i> <?php echo $prog['duration_months']; ?> months</span>
                                                <?php endif; ?>
                                                <?php if ($prog['tuition_fee']): ?>
                                                    <span><i class="fas fa-dollar-sign"></i> <?php echo number_format($prog['tuition_fee']); ?> <?php echo $prog['currency']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($prog['application_deadline']): ?>
                                                    <span class="deadline"><i class="far fa-calendar-alt"></i> Deadline: <?php echo Helpers::formatDate($prog['application_deadline'], 'M d, Y'); ?></span>
                                                <?php else: ?>
                                                    <span class="deadline">Rolling admission</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="program-action">
                                                <?php if ($already_applied): ?>
                                                    <button class="btn btn-secondary btn-small" disabled>Already Applied</button>
                                                <?php else: ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="program_id" value="<?php echo $prog['program_id']; ?>">
                                                        <button type="submit" name="new_application" class="btn btn-primary btn-small">
                                                            <i class="fas fa-paper-plane"></i> Apply
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pagination-controls">
                <button id="show-more" class="btn btn-secondary">Show More</button>
                <span id="counter">Showing 10 of <?php echo count($universities); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Existing Applications Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Your Applications</h3>
    </div>
    <div class="card-body">
        <?php if (empty($applications)): ?>
            <p class="text-muted">You haven't started any applications yet. Select a program above to begin!</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Program</th>
                            <th>University</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['program_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($app['university_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($app['university_name']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($app['status_name']); ?>">
                                        <?php echo $app['status_name']; ?>
                                    </span>
                                </td>
                                <td><?php echo Helpers::formatDate($app['applied_at']); ?></td>
                                <td>
                                    <?php if ($app['deadline']): ?>
                                        <?php echo Helpers::formatDate($app['deadline']); ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="application_view.php?id=<?php echo $app['application_id']; ?>" class="btn btn-small">View</a>
                                    <?php if ($app['status_name'] == 'Draft'): ?>
                                        <a href="application_view.php?id=<?php echo $app['application_id']; ?>&action=edit" class="btn btn-small btn-primary">Continue</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Store all university items for sorting and pagination
let universityItems = [];
let currentSort = 'name';
let itemsPerPage = 10;
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('university-list');
    universityItems = Array.from(list.children);
    
    // Initial sort by name (already sorted from DB, but we'll ensure)
    sortUniversities('name');
    
    // Pagination: show only first 10
    updatePagination();
    
    // Sort button listeners
    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const sortBy = this.dataset.sort;
            currentSort = sortBy;
            sortUniversities(sortBy);
            currentPage = 1; // reset to first page
            updatePagination();
        });
    });
    
    // Show more button
    document.getElementById('show-more').addEventListener('click', function() {
        currentPage++;
        updatePagination();
    });
});

function sortUniversities(criteria) {
    universityItems.sort((a, b) => {
        if (criteria === 'name') {
            return a.dataset.name.localeCompare(b.dataset.name);
        } else if (criteria === 'rank') {
            return parseInt(a.dataset.rank) - parseInt(b.dataset.rank);
        } else if (criteria === 'programs') {
            return parseInt(b.dataset.programs) - parseInt(a.dataset.programs); // descending
        }
    });
    
    // Reorder DOM
    const list = document.getElementById('university-list');
    universityItems.forEach(item => list.appendChild(item));
}

function updatePagination() {
    const totalItems = universityItems.length;
    const maxPages = Math.ceil(totalItems / itemsPerPage);
    
    // Hide all
    universityItems.forEach(item => item.style.display = 'none');
    
    // Show items for current page
    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, totalItems);
    for (let i = start; i < end; i++) {
        universityItems[i].style.display = 'block';
    }
    
    // Update counter
    document.getElementById('counter').innerText = `Showing ${end - start} of ${totalItems}`;
    
    // Disable "Show More" if no more
    document.getElementById('show-more').disabled = (currentPage >= maxPages);
}

function toggleUniversity(id) {
    const programsDiv = document.getElementById('programs-' + id);
    const icon = document.getElementById('icon-' + id);
    if (programsDiv.style.display === 'none') {
        programsDiv.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        programsDiv.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}
</script>

<style>
/* Card header with sort controls */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #e9ecef;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.sort-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}
.sort-controls span {
    color: #6c757d;
}
.sort-btn {
    background: none;
    border: 1px solid #dee2e6;
    padding: 0.3rem 1rem;
    border-radius: 30px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s;
}
.sort-btn:hover {
    background: #f8f9fa;
}
.sort-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

/* University list - scrollable and paginated */
.university-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    max-height: 600px;
    overflow-y: auto;
    padding-right: 0.5rem;
}
.university-item {
    background: white;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.university-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* Reduced header height */
.university-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 1.2rem;
    background: #f8fafc;
    cursor: pointer;
}
.university-info h4 {
    margin: 0 0 0.3rem 0;
    color: #2c3e50;
    font-size: 1.1rem;
}
.university-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #6c757d;
    flex-wrap: wrap;
}
.university-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.university-toggle i {
    font-size: 1rem;
    color: #6c757d;
}
.university-programs {
    padding: 1.2rem;
    background: white;
    border-top: 1px solid #e9ecef;
}
.programs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.2rem;
}
.program-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    background: #ffffff;
    transition: transform 0.2s, box-shadow 0.2s;
}
.program-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}
.program-card h5 {
    margin: 0 0 0.8rem 0;
    font-size: 1rem;
    color: #2c3e50;
}
.program-details {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    color: #4a5568;
}
.program-details span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.program-details i {
    width: 16px;
    color: #3498db;
}
.deadline {
    font-weight: 500;
}
.program-action {
    text-align: right;
}
.btn-small {
    padding: 0.35rem 0.9rem;
    font-size: 0.8rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    border: none;
    color: white;
    border-radius: 30px;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(52,152,219,0.3);
}
.btn-secondary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    border-radius: 30px;
}
.btn-secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Pagination controls */
.pagination-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}
.pagination-controls span {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Table styling */
.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
}
.table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #e9ecef;
}
.table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}
.status-badge {
    display: inline-block;
    padding: 0.25rem 1rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
}
.status-draft { background: #ffc107; color: #333; }
.status-submitted { background: #007bff; color: white; }
.status-under_review { background: #17a2b8; color: white; }
.status-accepted { background: #28a745; color: white; }
.status-rejected { background: #dc3545; color: white; }
.status-withdrawn { background: #6c757d; color: white; }
.status-enrolled { background: #6610f2; color: white; }

/* Responsive */
@media (max-width: 768px) {
    .university-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .sort-controls {
        flex-wrap: wrap;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>