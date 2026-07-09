<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Scholarships";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

$program_id = $_GET['program_id'] ?? 0;

if (isset($_POST['from_application'])) {
    $_SESSION['return_to_application'] = $_POST['from_application'];
}

// Show all scholarships (including expired)
$where_conditions = ["1=1"];
$params = [$student_id];

if ($program_id) {
    $where_conditions[] = "s.program_id = ?";
    $params[] = $program_id;
}

$where_clause = implode(" AND ", $where_conditions);

$scholarships = $db->fetchAll("
    SELECT s.*, p.program_name, u.name as university_name, 
           dl.name as degree_level, 
           (SELECT COUNT(*) FROM student_scholarships ss WHERE ss.scholarship_id = s.scholarship_id AND ss.student_id = ? AND ss.status != 'withdrawn') as has_applied
    FROM scholarships s
    JOIN programs p ON s.program_id = p.program_id
    JOIN universities u ON p.university_id = u.university_id
    JOIN degree_levels dl ON p.degree_level_id = dl.degree_level_id
    WHERE $where_clause
    ORDER BY s.deadline ASC
", $params);

$my_applications = $db->fetchAll("
    SELECT ss.*, s.name as scholarship_name, s.amount, s.currency,
           s.description, s.criteria, s.deadline as scholarship_deadline,
           p.program_name, u.name as university_name
    FROM student_scholarships ss
    JOIN scholarships s ON ss.scholarship_id = s.scholarship_id
    JOIN programs p ON s.program_id = p.program_id
    JOIN universities u ON p.university_id = u.university_id
    WHERE ss.student_id = ?
    ORDER BY ss.applied_at DESC
", [$student_id]);

// Compute status counts
$applied_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'applied'));
$under_review_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'under_review'));
$awarded_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'awarded'));
$withdrawn_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'withdrawn'));

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_scholarship'])) {
    $scholarship_id = intval($_POST['scholarship_id']);
    
    // Check if there's an existing application that is not withdrawn
    $existing = $db->fetchOne(
        "SELECT id, status FROM student_scholarships WHERE student_id = ? AND scholarship_id = ? AND status != 'withdrawn'",
        [$student_id, $scholarship_id]
    );
    
    if ($existing) {
        $error = "You have already applied for this scholarship.";
    } else {
        // If there's a withdrawn application, update it
        $withdrawn = $db->fetchOne(
            "SELECT id FROM student_scholarships WHERE student_id = ? AND scholarship_id = ? AND status = 'withdrawn'",
            [$student_id, $scholarship_id]
        );
        if ($withdrawn) {
            $db->update('student_scholarships', 
                ['status' => 'applied', 'applied_at' => date('Y-m-d H:i:s')],
                "id = " . $withdrawn['id']
            );
        } else {
            $db->insert('student_scholarships', [
                'student_id' => $student_id,
                'scholarship_id' => $scholarship_id,
                'applied_at' => date('Y-m-d H:i:s'),
                'status' => 'applied',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Redirect to refresh the page with success message
        $redirect_url = 'scholarships.php' . ($program_id ? '?program_id=' . $program_id : '');
        Helpers::redirect($redirect_url, 'Scholarship application submitted successfully!');
    }
}

if (isset($_GET['withdraw'])) {
    $application_id = intval($_GET['withdraw']);
    
    $application = $db->fetchOne(
        "SELECT ss.* FROM student_scholarships ss 
         WHERE ss.id = ? AND ss.student_id = ? AND ss.status IN ('applied', 'under_review')",
        [$application_id, $student_id]
    );
    
    if ($application) {
        $db->update('student_scholarships', 
            ['status' => 'withdrawn'],
            "id = $application_id"
        );
        $redirect_url = 'scholarships.php' . ($program_id ? '?program_id=' . $program_id : '');
        Helpers::redirect($redirect_url, 'Scholarship application withdrawn successfully!');
    } else {
        $error = "Cannot withdraw this application.";
    }
}

// Refresh applications after any action (if not redirected)
$my_applications = $db->fetchAll("
    SELECT ss.*, s.name as scholarship_name, s.amount, s.currency,
           s.description, s.criteria, s.deadline as scholarship_deadline,
           p.program_name, u.name as university_name
    FROM student_scholarships ss
    JOIN scholarships s ON ss.scholarship_id = s.scholarship_id
    JOIN programs p ON s.program_id = p.program_id
    JOIN universities u ON p.university_id = u.university_id
    WHERE ss.student_id = ?
    ORDER BY ss.applied_at DESC
", [$student_id]);

// Recompute counts after any action
$applied_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'applied'));
$under_review_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'under_review'));
$awarded_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'awarded'));
$withdrawn_count = count(array_filter($my_applications, fn($a) => $a['status'] == 'withdrawn'));
?>

<div class="page-header">
    <h1><i class="fas fa-award"></i> Scholarships</h1>
    <p>Find and apply for scholarships to fund your education</p>
</div>

<?php
$flash = Helpers::getFlash();
if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $flash['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="dashboard-card stats-card">
        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Application Overview</h3>
        <div class="stats-grid modern">
            <div class="stat-item" data-tooltip="Scholarships you can apply for">
                <div class="stat-icon" style="background: linear-gradient(145deg, #4CAF50, #2E7D32);">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-content">
                    <h4><?php echo count($scholarships); ?></h4>
                    <p>Available</p>
                </div>
            </div>
            <div class="stat-item" data-tooltip="Applications pending initial review">
                <div class="stat-icon" style="background: linear-gradient(145deg, #2196F3, #0D47A1);">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-content">
                    <h4><?php echo $applied_count; ?></h4>
                    <p>Applied</p>
                </div>
            </div>
            <div class="stat-item" data-tooltip="Applications being reviewed">
                <div class="stat-icon" style="background: linear-gradient(145deg, #FF9800, #E65100);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h4><?php echo $under_review_count; ?></h4>
                    <p>Under Review</p>
                </div>
            </div>
            <div class="stat-item" data-tooltip="Scholarships awarded to you">
                <div class="stat-icon" style="background: linear-gradient(145deg, #9C27B0, #4A148C);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h4><?php echo $awarded_count; ?></h4>
                    <p>Awarded</p>
                </div>
            </div>
            <div class="stat-item" data-tooltip="Applications you withdrew">
                <div class="stat-icon" style="background: linear-gradient(145deg, #F44336, #B71C1C);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h4><?php echo $withdrawn_count; ?></h4>
                    <p>Withdrawn</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card filter-card">
        <h3 class="card-title"><i class="fas fa-sliders-h"></i> Quick Filters</h3>
        <div class="filter-buttons">
            <button class="btn btn-small active" data-filter="all">All</button>
            <button class="btn btn-small" data-filter="eligible">Eligible</button>
            <button class="btn btn-small" data-filter="deadline">Near Deadline</button>
            <button class="btn btn-small" data-filter="high-value">High Value</button>
        </div>
        
        <div class="form-group mt-3">
            <input type="text" id="searchScholarships" class="form-control" placeholder="🔍 Search scholarships...">
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Available Scholarships</h3>
        <div class="card-actions">
            <select id="sortScholarships" class="form-control">
                <option value="deadline">Sort by Deadline</option>
                <option value="amount">Sort by Amount</option>
                <option value="university">Sort by University</option>
            </select>
        </div>
    </div>
    
    <?php if (empty($scholarships)): ?>
        <div class="empty-state">
            <i class="fas fa-award fa-4x"></i>
            <h4>No scholarships available</h4>
            <p>Check back later for new scholarship opportunities.</p>
        </div>
    <?php else: ?>
        <div class="scholarships-grid" id="scholarshipsList">
            <?php foreach ($scholarships as $scholarship): ?>
                <div class="scholarship-item" 
                     data-amount="<?php echo $scholarship['amount'] ?? 0; ?>"
                     data-deadline="<?php echo $scholarship['deadline']; ?>"
                     data-university="<?php echo strtolower($scholarship['university_name']); ?>">
                    <div class="scholarship-item-header">
                        <h4><?php echo htmlspecialchars($scholarship['name']); ?></h4>
                        <div class="scholarship-amount">
                            <?php if ($scholarship['amount']): ?>
                                <?php echo $scholarship['currency'] . ' ' . number_format($scholarship['amount'], 0); ?>
                            <?php else: ?>
                                Variable
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="scholarship-item-body">
                        <p><i class="fas fa-university"></i> <?php echo htmlspecialchars($scholarship['university_name']); ?></p>
                        <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($scholarship['program_name']); ?></p>
                        <p><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($scholarship['degree_level']); ?></p>
                        
                        <?php if ($scholarship['deadline']): ?>
                            <p><i class="fas fa-calendar-alt"></i> 
                                Deadline: <?php echo Helpers::formatDate($scholarship['deadline'], 'M d, Y'); ?>
                                <?php 
                                $days_left = floor((strtotime($scholarship['deadline']) - time()) / (60 * 60 * 24));
                                if ($days_left >= 0 && $days_left <= 7): ?>
                                    <span class="deadline-warning" data-tooltip="Only <?php echo $days_left; ?> day(s) left!">
                                        (<?php echo $days_left; ?> days left)
                                    </span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p><i class="fas fa-calendar-alt"></i> Rolling admission</p>
                        <?php endif; ?>
                        
                        <?php if ($scholarship['criteria']): ?>
                            <div class="criteria">
                                <strong>Criteria:</strong>
                                <?php 
                                $criteria = json_decode($scholarship['criteria'], true);
                                if (is_array($criteria)):
                                    $parts = [];
                                    foreach ($criteria as $key => $value) {
                                        if (is_array($value)) {
                                            $value = implode(', ', $value);
                                        }
                                        $parts[] = "$key: $value";
                                    }
                                    echo implode(', ', $parts);
                                endif;
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="scholarship-item-footer">
                        <?php if ($scholarship['has_applied']): ?>
                            <button class="btn btn-secondary btn-block" disabled>
                                <i class="fas fa-check"></i> Applied
                            </button>
                        <?php else: ?>
                            <form method="POST" style="width: 100%;">
                                <input type="hidden" name="scholarship_id" value="<?php echo $scholarship['scholarship_id']; ?>">
                                <button type="submit" name="apply_scholarship" class="btn btn-primary btn-block">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary btn-block mt-1" onclick="viewScholarshipDetails(<?php echo htmlspecialchars(json_encode($scholarship)); ?>)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3><i class="fas fa-history"></i> My Scholarship Applications</h3>
    
    <?php if (empty($my_applications)): ?>
        <p class="text-muted text-center py-4">You haven't applied for any scholarships yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Scholarship</th>
                        <th>University</th>
                        <th>Amount</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_applications as $app): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($app['scholarship_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($app['program_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['university_name']); ?></td>
                            <td>
                                <?php if ($app['amount']): ?>
                                    <?php echo $app['currency'] . ' ' . number_format($app['amount'], 0); ?>
                                <?php else: ?>
                                    Variable
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helpers::formatDate($app['applied_at'], 'M d, Y'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($app['status']); ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($app['scholarship_deadline']): ?>
                                    <?php echo Helpers::formatDate($app['scholarship_deadline'], 'M d, Y'); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-icon btn-info" onclick='viewApplicationDetails(<?php echo htmlspecialchars(json_encode($app)); ?>)' data-tooltip="View details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($app['status'] == 'applied' || $app['status'] == 'under_review'): ?>
                                        <a href="scholarships.php?withdraw=<?php echo $app['id']; ?><?php echo $program_id ? '&program_id=' . $program_id : ''; ?>" 
                                           class="btn btn-icon btn-danger"
                                           data-tooltip="Withdraw application"
                                           onclick="return confirm('Are you sure you want to withdraw this application?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Scholarship Details</h3>
            <button class="close" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="detailsContent"></div>
    </div>
</div>

<style>
/* Modern stats grid */
.stats-grid.modern {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.5rem;
    padding: 0.25rem;
}

.stat-item {
    position: relative;
    display: flex;
    align-items: center;
    padding: 0.5rem;
    gap: 0.35rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
    border: 1px solid #f0f0f0;
}

.stat-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-color: #e0e0e0;
}

.stat-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    flex-shrink: 0;
}

.stat-content {
    min-width: 0;
    flex: 1;
}

.stat-content h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-content p {
    margin: 0;
    font-size: 0.65rem;
    color: #7f8c8d;
    font-weight: 500;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}


/* Tooltip */
[data-tooltip] {
    position: relative;
    cursor: help;
}
[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #2c3e50;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s;
    z-index: 10;
}
[data-tooltip]:hover:before {
    opacity: 1;
}

/* Card titles */
.card-title {
    margin-bottom: 1.5rem;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #2c3e50;
}
.card-title i {
    color: #3498db;
}

/* Stats card */
.stats-card {
    grid-column: span 2;
    padding: 1rem;
}

/* Filter card */
.filter-card {
    display: flex;
    flex-direction: column;
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.filter-buttons .btn {
    padding: 6px 14px;
    font-size: 0.85rem;
    border-radius: 30px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    color: #495057;
    transition: all 0.2s;
}

.filter-buttons .btn:hover {
    background: #e9ecef;
}

.filter-buttons .btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

/* Scholarships grid */
.scholarships-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.8rem;
    margin-top: 1.5rem;
}

.scholarship-item {
    border: none;
    border-radius: 18px;
    overflow: hidden;
    background: white;
    box-shadow: 0 8px 20px rgba(0,0,0,0.04);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: flex;
    flex-direction: column;
}

.scholarship-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 30px rgba(0,0,0,0.12);
}

.scholarship-item-header {
    padding: 1.8rem 1.8rem 1.2rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    position: relative;
}

.scholarship-item-header h4 {
    margin: 0 0 1rem 0;
    font-size: 1.3rem;
    font-weight: 600;
    line-height: 1.4;
    min-height: 3.5rem;
}

.scholarship-amount {
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    background: rgba(255,255,255,0.2);
    display: inline-block;
    padding: 0.3rem 1.2rem;
    border-radius: 40px;
    backdrop-filter: blur(4px);
}

.scholarship-item-body {
    padding: 1.8rem;
    flex: 1;
}

.scholarship-item-body p {
    margin: 0.6rem 0;
    font-size: 0.95rem;
    color: #4a5568;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.scholarship-item-body i {
    width: 20px;
    color: #667eea;
    font-size: 1rem;
}

.deadline-warning {
    color: #e53e3e;
    font-weight: 600;
    margin-left: 0.5rem;
    background: #fee;
    padding: 0.2rem 0.5rem;
    border-radius: 16px;
    font-size: 0.8rem;
}

.criteria {
    margin-top: 1rem;
    padding: 0.8rem;
    background: #f7fafc;
    border-radius: 10px;
    font-size: 0.85rem;
    color: #2d3748;
    border-left: 4px solid #667eea;
}

.scholarship-item-footer {
    padding: 1rem 1.8rem 1.8rem;
    border-top: 1px solid #edf2f7;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.btn-block {
    width: 100%;
    border-radius: 40px;
    padding: 0.8rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
}

.btn-primary {
    background: linear-gradient(145deg, #667eea, #764ba2);
    color: white;
}

.btn-primary:hover {
    transform: scale(1.02);
    box-shadow: 0 10px 20px rgba(102,126,234,0.3);
}

.btn-secondary {
    background: #edf2f7;
    color: #4a5568;
    border: 1px solid #cbd5e0;
}

.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-outline-secondary {
    background: transparent;
    border: 1px solid #cbd5e0;
    color: #4a5568;
}

.btn-outline-secondary:hover {
    background: #f8f9fa;
}

/* Action buttons in table */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    color: #495057;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.btn-icon.btn-info {
    color: #3498db;
}

.btn-icon.btn-danger {
    color: #e74c3c;
}

.btn-icon.btn-danger:hover {
    background: #fee;
}

/* Table enhancements */
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
    font-size: 0.9rem;
}

.table td {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    vertical-align: middle;
}

.status-badge {
    padding: 0.3rem 1rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    min-width: 90px;
}

.status-applied {
    background: #e3f2fd;
    color: #1976d2;
}
.status-under_review {
    background: #fff3e0;
    color: #f57c00;
}
.status-awarded {
    background: #e8f5e9;
    color: #388e3c;
}
.status-rejected, .status-withdrawn {
    background: #ffebee;
    color: #c62828;
}

/* Modal */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    max-width: 600px;
    width: 90%;
    border-radius: 24px;
    box-shadow: 0 30px 60px -15px rgba(0,0,0,0.3);
    animation: modalSlide 0.3s ease;
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
    color: #2d3748;
    font-size: 1.4rem;
}

.close {
    background: none;
    border: none;
    font-size: 2.2rem;
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

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #718096;
}

.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}

.empty-state h4 {
    margin-bottom: 0.5rem;
    color: #4a5568;
    font-size: 1.2rem;
}

/* Responsive */
@media (max-width: 900px) {
    .stats-grid.modern {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    }
    .stat-item {
        padding: 0.5rem;
    }
    .stat-icon {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
    .stat-content h4 {
        font-size: 0.9rem;
    }
    .stat-content p {
        font-size: 0.6rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchScholarships');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.scholarship-item');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // Filter buttons
    const filterButtons = document.querySelectorAll('.filter-buttons .btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            filterScholarships(filter);
        });
    });

    // Sort dropdown
    const sortSelect = document.getElementById('sortScholarships');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortScholarships(this.value);
        });
    }

    // Modal close button
    const modal = document.getElementById('detailsModal');
    const closeBtn = modal ? modal.querySelector('.close') : null;
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            closeModal('detailsModal');
        });
    }
    // Close on outside click
    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal('detailsModal');
            }
        });
    }
});

// Global functions
function filterScholarships(filter) {
    const items = document.querySelectorAll('.scholarship-item');
    items.forEach(item => {
        let show = true;
        switch(filter) {
            case 'eligible':
                show = true; // Placeholder; implement actual logic if needed
                break;
            case 'deadline':
                const deadline = item.dataset.deadline;
                if (deadline) {
                    const daysLeft = Math.floor((new Date(deadline) - new Date()) / (1000 * 60 * 60 * 24));
                    show = daysLeft <= 30 && daysLeft >= 0;
                } else {
                    show = false;
                }
                break;
            case 'high-value':
                const amount = parseFloat(item.dataset.amount) || 0;
                show = amount >= 5000;
                break;
            default:
                show = true;
        }
        item.style.display = show ? '' : 'none';
    });
}

function sortScholarships(criteria) {
    const container = document.getElementById('scholarshipsList');
    if (!container) return;
    const items = Array.from(container.querySelectorAll('.scholarship-item'));
    
    items.sort((a, b) => {
        switch(criteria) {
            case 'amount':
                const amountA = parseFloat(a.dataset.amount) || 0;
                const amountB = parseFloat(b.dataset.amount) || 0;
                return amountB - amountA;
            case 'university':
                const uniA = a.dataset.university;
                const uniB = b.dataset.university;
                return uniA.localeCompare(uniB);
            case 'deadline':
            default:
                const deadlineA = a.dataset.deadline || '9999-12-31';
                const deadlineB = b.dataset.deadline || '9999-12-31';
                return new Date(deadlineA) - new Date(deadlineB);
        }
    });
    
    items.forEach(item => container.appendChild(item));
}

function viewScholarshipDetails(scholarship) {
    let criteria = '';
    if (scholarship.criteria) {
        try {
            let criteriaObj = JSON.parse(scholarship.criteria);
            let parts = [];
            for (let key in criteriaObj) {
                parts.push(key + ': ' + criteriaObj[key]);
            }
            criteria = parts.join(', ');
        } catch(e) {
            criteria = scholarship.criteria;
        }
    }
    
    const content = `
        <div class="scholarship-details">
            <h4>${scholarship.name}</h4>
            <p><strong>University:</strong> ${scholarship.university_name}</p>
            <p><strong>Program:</strong> ${scholarship.program_name}</p>
            <p><strong>Degree Level:</strong> ${scholarship.degree_level}</p>
            <p><strong>Amount:</strong> ${scholarship.amount ? scholarship.currency + ' ' + Number(scholarship.amount).toLocaleString() : 'Variable'}</p>
            <p><strong>Deadline:</strong> ${scholarship.deadline ? scholarship.deadline : 'Rolling'}</p>
            <p><strong>Criteria:</strong> ${criteria ? criteria : 'No specific criteria'}</p>
            <p><strong>Description:</strong> ${scholarship.description ? scholarship.description : 'No description provided.'}</p>
        </div>
    `;
    document.getElementById('detailsContent').innerHTML = content;
    showModal('detailsModal');
}

function viewApplicationDetails(app) {
    let criteria = '';
    if (app.criteria) {
        try {
            let criteriaObj = JSON.parse(app.criteria);
            let parts = [];
            for (let key in criteriaObj) {
                parts.push(key + ': ' + criteriaObj[key]);
            }
            criteria = parts.join(', ');
        } catch(e) {
            criteria = app.criteria;
        }
    }
    
    const content = `
        <div class="application-details">
            <h4>${app.scholarship_name}</h4>
            <p><strong>University:</strong> ${app.university_name}</p>
            <p><strong>Program:</strong> ${app.program_name}</p>
            <p><strong>Amount:</strong> ${app.amount ? app.currency + ' ' + Number(app.amount).toLocaleString() : 'Variable'}</p>
            <p><strong>Applied:</strong> ${app.applied_at}</p>
            <p><strong>Status:</strong> ${app.status}</p>
            <p><strong>Deadline:</strong> ${app.scholarship_deadline ? app.scholarship_deadline : 'N/A'}</p>
            <p><strong>Description:</strong> ${app.description ? app.description : 'No description'}</p>
            <p><strong>Criteria:</strong> ${criteria ? criteria : 'No specific criteria'}</p>
        </div>
    `;
    document.getElementById('detailsContent').innerHTML = content;
    showModal('detailsModal');
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php require_once '../../app/templates/footer.php'; 
if (isset($success) && isset($_SESSION['return_to_application'])) {
    $return_id = $_SESSION['return_to_application'];
    unset($_SESSION['return_to_application']);
    echo "<script>
            setTimeout(function() {
                window.location.href = 'application_view.php?id=$return_id';
            }, 2000);
          </script>";
}
?>