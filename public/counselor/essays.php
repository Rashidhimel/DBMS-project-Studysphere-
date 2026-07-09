<?php
require_once '../../app/includes/init.php';
requireCounselor();

$page_title = "Essay Reviews";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $essay_id = intval($_POST['essay_id']);
    $feedback = Helpers::sanitize($_POST['feedback']);

    // Verify this essay belongs to a student assigned to this counselor
    $essay = $db->fetchOne("
        SELECT e.essay_id, e.student_id 
        FROM essays e
        JOIN counselor_assignments ca ON e.student_id = ca.student_id
        WHERE e.essay_id = ? AND ca.counselor_id = ?
    ", [$essay_id, $user['user_id']]);

    if ($essay) {
        $db->insert('essay_feedback', [
            'essay_id' => $essay_id,
            'counselor_id' => $user['user_id'],
            'feedback' => $feedback,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $db->update('essays', ['status' => 'reviewed'], "essay_id = $essay_id");
        $success = "Feedback submitted.";
    } else {
        $error = "Essay not found or not assigned to you.";
    }
}

// Get list of essays from assigned students
$essays = $db->fetchAll("
    SELECT e.*, 
           s.student_id, u.username, up.first_name, up.last_name,
           (SELECT COUNT(*) FROM essay_feedback WHERE essay_id = e.essay_id) as feedback_count
    FROM essays e
    JOIN students s ON e.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    JOIN counselor_assignments ca ON s.student_id = ca.student_id
    WHERE ca.counselor_id = ?
    ORDER BY 
        CASE e.status 
            WHEN 'submitted' THEN 1
            WHEN 'under_review' THEN 2
            WHEN 'reviewed' THEN 3
            ELSE 4
        END,
        e.submitted_at DESC
", [$user['user_id']]);

// Compute stats
$total_essays = count($essays);
$submitted = count(array_filter($essays, fn($e) => $e['status'] == 'submitted'));
$under_review = count(array_filter($essays, fn($e) => $e['status'] == 'under_review'));
$reviewed = count(array_filter($essays, fn($e) => $e['status'] == 'reviewed'));

// If reviewing a specific essay
$review_essay = null;
if (isset($_GET['review']) && is_numeric($_GET['review'])) {
    $essay_id = intval($_GET['review']);
    $review_essay = $db->fetchOne("
        SELECT e.*, 
               s.student_id, u.username, up.first_name, up.last_name
        FROM essays e
        JOIN students s ON e.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        JOIN counselor_assignments ca ON s.student_id = ca.student_id
        WHERE e.essay_id = ? AND ca.counselor_id = ?
    ", [$essay_id, $user['user_id']]);

    if ($review_essay) {
        // Mark as under review if not already
        if ($review_essay['status'] == 'submitted') {
            $db->update('essays', ['status' => 'under_review'], "essay_id = $essay_id");
            $review_essay['status'] = 'under_review';
        }
        // Get previous feedback
        $feedback_history = $db->fetchAll("
            SELECT ef.*, u.username as counselor_name
            FROM essay_feedback ef
            JOIN users u ON ef.counselor_id = u.user_id
            WHERE ef.essay_id = ?
            ORDER BY ef.created_at DESC
        ", [$essay_id]);
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-pen"></i> Essay Reviews</h1>
    <p class="text-muted">Review and provide feedback on student essays</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-error alert-dismissible fade show" role="alert">
        <?= $error ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
    </div>
<?php endif; ?>

<?php if ($review_essay): ?>
    <!-- Review specific essay -->
    <div class="page-header">
        <a href="essays.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <!-- Stats cards for this essay? Not needed, but we can show simple info -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> <?= htmlspecialchars($review_essay['title']) ?></h3>
        </div>
        <div class="card-body">
            <div class="essay-meta">
                <p><strong>Student:</strong> <?= htmlspecialchars($review_essay['first_name'] . ' ' . $review_essay['last_name'] ?: $review_essay['username']) ?></p>
                <p><strong>Submitted:</strong> <?= Helpers::formatDate($review_essay['submitted_at']) ?></p>
                <p><strong>Status:</strong> <span class="status-badge" style="background: <?= 
                    $review_essay['status'] == 'submitted' ? '#f39c12' : ($review_essay['status'] == 'under_review' ? '#17a2b8' : ($review_essay['status'] == 'reviewed' ? '#28a745' : '#6c757d')) ?>;">
                    <?= ucfirst($review_essay['status']) ?>
                </span></p>
            </div>

            <div class="essay-content">
                <h4>Essay Content</h4>
                <div class="content-box">
                    <?= nl2br(htmlspecialchars($review_essay['content'])) ?>
                </div>
            </div>

            <?php if ($review_essay['attachment_id']): ?>
                <div class="attachment">
                    <a href="/studysphere/public/download.php?id=<?= $review_essay['attachment_id'] ?>" class="btn btn-outline-primary">
                        <i class="fas fa-download"></i> Download Attachment
                    </a>
                </div>
            <?php endif; ?>

            <!-- Feedback form -->
            <div class="feedback-section">
                <h4>Provide Feedback</h4>
                <form method="POST">
                    <input type="hidden" name="essay_id" value="<?= $review_essay['essay_id'] ?>">
                    <div class="form-group">
                        <textarea name="feedback" class="form-control" rows="6" placeholder="Write your feedback, suggestions, corrections..." required></textarea>
                    </div>
                    <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
                </form>
            </div>

            <!-- Feedback history -->
            <?php if (!empty($feedback_history)): ?>
                <div class="feedback-history">
                    <h4>Previous Feedback</h4>
                    <?php foreach ($feedback_history as $fb): ?>
                        <div class="feedback-item">
                            <div class="feedback-header">
                                <strong><?= htmlspecialchars($fb['counselor_name']) ?></strong>
                                <small><?= Helpers::formatDate($fb['created_at']) ?></small>
                            </div>
                            <div class="feedback-body">
                                <?= nl2br(htmlspecialchars($fb['feedback'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= $total_essays ?></h3>
                <p>Total Essays</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?= $submitted ?></h3>
                <p>Submitted</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #17a2b8, #138496);">
                <i class="fas fa-search"></i>
            </div>
            <div class="stat-content">
                <h3><?= $under_review ?></h3>
                <p>Under Review</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(145deg, #28a745, #218838);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $reviewed ?></h3>
                <p>Reviewed</p>
            </div>
        </div>
    </div>

    <!-- Essays Table Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Student Essays (<?= count($essays) ?>)</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Title</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Feedback</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($essays)): ?>
                            <tr><td colspan="6" class="text-center py-4">No essays from your students yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($essays as $e): 
                                $student_name = $e['first_name'] ? $e['first_name'] . ' ' . $e['last_name'] : $e['username'];
                                $status_colors = [
                                    'submitted' => '#f39c12',
                                    'under_review' => '#17a2b8',
                                    'reviewed' => '#28a745'
                                ];
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($student_name) ?></strong></td>
                                    <td><?= htmlspecialchars($e['title']) ?></td>
                                    <td><?= $e['submitted_at'] ? Helpers::formatDate($e['submitted_at']) : 'Draft' ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?= $status_colors[$e['status']] ?? '#6c757d' ?>;">
                                            <?= ucfirst($e['status']) ?>
                                        </span>
                                    </td>
                                    <td><span class="badge badge-info"><?= $e['feedback_count'] ?></span></td>
                                    <td>
                                        <a href="?review=<?= $e['essay_id'] ?>" class="btn-icon btn-primary" title="Review">
                                            <i class="fas fa-search"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
/* Reuse modern styles from other pages */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.06);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}
.stat-content h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.8rem;
    font-weight: 500;
}

.card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.02);
    margin-bottom: 2rem;
    overflow: hidden;
}
.card-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-body {
    padding: 1.5rem;
}
.card-body.p-0 {
    padding: 0;
}

.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
}
.table td {
    padding: 1rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}
.table tbody tr:last-child td {
    border-bottom: none;
}
.table-hover tbody tr:hover {
    background: #f9fbfd;
}

.status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
}
.badge-info {
    background: #17a2b8;
    color: white;
    padding: 0.25rem 0.6rem;
    border-radius: 30px;
    font-size: 0.75rem;
}

.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-icon.btn-primary { background: #3498db; }
.btn-icon:hover {
    opacity: 0.8;
    transform: translateY(-2px);
}
.btn-outline-primary {
    border: 1px solid #3498db;
    color: #3498db;
    background: transparent;
    border-radius: 30px;
    padding: 0.5rem 1.5rem;
    transition: all 0.2s;
}
.btn-outline-primary:hover {
    background: #3498db;
    color: white;
}
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    border-radius: 30px;
    padding: 0.6rem 2rem;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52,152,219,0.3);
}

/* Essay review page specific */
.essay-meta {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}
.essay-meta p {
    margin: 0.3rem 0;
}
.content-box {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid #eef2f6;
    margin: 1rem 0 1.5rem;
    white-space: pre-wrap;
}
.attachment {
    margin: 1.5rem 0;
}
.feedback-section {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #eef2f6;
}
.feedback-history {
    margin-top: 2rem;
}
.feedback-item {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
}
.feedback-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    color: #4a5568;
}
.feedback-body {
    white-space: pre-wrap;
}
.form-group {
    margin-bottom: 1.2rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 500;
    color: #4a5568;
}
.form-control {
    width: 100%;
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
}
.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>