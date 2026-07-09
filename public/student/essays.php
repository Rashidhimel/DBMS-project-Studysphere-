<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "My Essays";
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$user = $auth->getCurrentUser();
$db = new Database();

// Get counselor info (if assigned)
$counselor = $auth->getStudentCounselor($student_id);

// Handle new essay submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_essay'])) {
    $title = Helpers::sanitize($_POST['title']);
    $content = Helpers::sanitize($_POST['content']);
    $attachment_id = null;
    
    // Handle file upload if any
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $uploader = new UploadHelper();
        $result = $uploader->uploadFile($_FILES['attachment'], $user['user_id'], 'essay');
        if (isset($result['success']) && $result['success']) {
            $attachment_id = $result['attachment_id'];
        } else {
            $error = "File upload failed: " . ($result['error'] ?? 'Unknown error');
        }
    }
    
    if (empty($title)) {
        $error = "Title is required.";
    } elseif (empty($content) && !$attachment_id) {
        $error = "Please provide either content or upload a file.";
    } else {
        $db->insert('essays', [
            'student_id' => $student_id,
            'title' => $title,
            'content' => $content,
            'attachment_id' => $attachment_id,
            'status' => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $success = "Essay submitted for review.";
    }
}

// Handle essay deletion (only drafts)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $essay_id = intval($_GET['delete']);
    $essay = $db->fetchOne("SELECT * FROM essays WHERE essay_id = ? AND student_id = ? AND status = 'draft'", [$essay_id, $student_id]);
    
    if ($essay) {
        // Delete attachment if exists
        if ($essay['attachment_id']) {
            $uploader = new UploadHelper();
            $uploader->deleteAttachment($essay['attachment_id']);
        }
        // Delete feedback first (foreign key)
        $db->query("DELETE FROM essay_feedback WHERE essay_id = ?", [$essay_id]);
        // Delete essay
        $db->query("DELETE FROM essays WHERE essay_id = ?", [$essay_id]);
        $success = "Essay deleted.";
    } else {
        $error = "Cannot delete this essay (only drafts can be deleted).";
    }
}

// Get student's essays with all feedback
$essays = $db->fetchAll("
    SELECT e.*, 
           (SELECT COUNT(*) FROM essay_feedback WHERE essay_id = e.essay_id) as feedback_count,
           (SELECT MAX(created_at) FROM essay_feedback WHERE essay_id = e.essay_id) as last_feedback
    FROM essays e
    WHERE e.student_id = ?
    ORDER BY e.created_at DESC
", [$student_id]);

// For each essay, fetch its feedback
foreach ($essays as &$essay) {
    $essay['feedback'] = $db->fetchAll("
        SELECT ef.*, u.username as counselor_name, up.first_name, up.last_name
        FROM essay_feedback ef
        JOIN users u ON ef.counselor_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE ef.essay_id = ?
        ORDER BY ef.created_at DESC
    ", [$essay['essay_id']]);
}

// Compute stats
$total = count($essays);
$submitted = count(array_filter($essays, fn($e) => $e['status'] == 'submitted'));
$under_review = count(array_filter($essays, fn($e) => $e['status'] == 'under_review'));
$reviewed = count(array_filter($essays, fn($e) => $e['status'] == 'reviewed'));
?>

<div class="page-header">
    <h1><i class="fas fa-pen"></i> Essay Review</h1>
    <p class="text-muted">Submit your personal statements and essays for counselor feedback</p>
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

<?php if (!$counselor): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> You don't have an assigned counselor yet. Submissions will be saved but not reviewed until a counselor is assigned.
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total ?></h3>
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

<div class="dashboard-grid">
    <!-- Left column: New essay form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Submit New Essay</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Essay Title</label>
                    <input type="text" name="title" id="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="content">Content (or upload file below)</label>
                    <textarea name="content" id="content" class="form-control" rows="5"></textarea>
                </div>
                <div class="form-group">
                    <label for="attachment">Upload File (PDF, DOC, etc.)</label>
                    <input type="file" name="attachment" id="attachment" class="form-control">
                    <small class="text-muted">Max size: 5MB</small>
                </div>
                <button type="submit" name="submit_essay" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> Submit for Review
                </button>
            </form>
        </div>
    </div>

    <!-- Right column: Essays list -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> My Essays</h3>
        </div>
        <div class="card-body">
            <?php if (empty($essays)): ?>
                <div class="empty-state">
                    <i class="fas fa-pen fa-4x"></i>
                    <h4>No essays yet</h4>
                    <p>Submit your first essay using the form on the left.</p>
                </div>
            <?php else: ?>
                <div class="essays-container">
                    <?php foreach ($essays as $essay): ?>
                        <div class="essay-card">
                            <!-- Essay Header -->
                            <div class="essay-header">
                                <div>
                                    <h4 class="essay-title"><?= htmlspecialchars($essay['title']) ?></h4>
                                    <div class="essay-meta">
                                        <span class="status-badge status-<?= $essay['status'] ?>">
                                            <?= ucfirst($essay['status']) ?>
                                        </span>
                                        <span class="essay-date">
                                            <i class="far fa-calendar"></i> <?= Helpers::formatDate($essay['submitted_at']) ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($essay['status'] == 'draft'): ?>
                                    <div class="essay-actions">
                                        <a href="essay_edit.php?id=<?= $essay['essay_id'] ?>" class="btn-icon btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?= $essay['essay_id'] ?>" class="btn-icon btn-danger" title="Delete"
                                           onclick="return confirm('Delete this essay?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Essay Content Preview -->
                            <div class="essay-content">
                                <?php if ($essay['content']): ?>
                                    <div class="content-preview" id="preview-<?= $essay['essay_id'] ?>">
                                        <?= nl2br(htmlspecialchars(substr($essay['content'], 0, 300))) ?>
                                        <?php if (strlen($essay['content']) > 300): ?>
                                            ... <a href="#" class="read-more" data-id="<?= $essay['essay_id'] ?>">Read more</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="content-full" id="full-<?= $essay['essay_id'] ?>" style="display: none;">
                                        <?= nl2br(htmlspecialchars($essay['content'])) ?>
                                        <a href="#" class="read-less" data-id="<?= $essay['essay_id'] ?>">Show less</a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($essay['attachment_id']): ?>
                                    <div class="attachment">
                                        <a href="/studysphere/public/download_file.php?id=<?= $essay['attachment_id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-file"></i> View Attachment
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Feedback Section -->
                            <div class="feedback-section">
                                <h5 class="feedback-title">
                                    <i class="fas fa-comments"></i> Feedback
                                    <?php if ($essay['feedback_count'] > 0): ?>
                                        <span class="badge badge-success"><?= $essay['feedback_count'] ?></span>
                                    <?php endif; ?>
                                </h5>

                                <?php if (empty($essay['feedback'])): ?>
                                    <p class="text-muted">No feedback yet.</p>
                                <?php else: ?>
                                    <div class="feedback-list">
                                        <?php foreach ($essay['feedback'] as $fb): 
                                            $counselor_name = $fb['first_name'] ? $fb['first_name'] . ' ' . $fb['last_name'] : $fb['counselor_name'];
                                        ?>
                                            <div class="feedback-item">
                                                <div class="feedback-header">
                                                    <strong><?= htmlspecialchars($counselor_name) ?></strong>
                                                    <small class="text-muted"><?= Helpers::formatDate($fb['created_at']) ?></small>
                                                </div>
                                                <div class="feedback-body">
                                                    <?= nl2br(htmlspecialchars($fb['feedback'])) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Footer -->
                            <div class="essay-footer">
                                <small class="text-muted">
                                    Last feedback: <?= $essay['last_feedback'] ? Helpers::formatDate($essay['last_feedback']) : 'Never' ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Read more / less toggles
    document.querySelectorAll('.read-more').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            document.getElementById('preview-' + id).style.display = 'none';
            document.getElementById('full-' + id).style.display = 'block';
        });
    });
    document.querySelectorAll('.read-less').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            document.getElementById('full-' + id).style.display = 'none';
            document.getElementById('preview-' + id).style.display = 'block';
        });
    });
});
</script>

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

/* Form styling */
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
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    border-radius: 30px;
    padding: 0.6rem 1.5rem;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52,152,219,0.3);
}
.btn-block {
    width: 100%;
}
.btn-outline-primary {
    border: 1px solid #3498db;
    color: #3498db;
    background: transparent;
    border-radius: 30px;
    padding: 0.4rem 1.2rem;
    transition: all 0.2s;
}
.btn-outline-primary:hover {
    background: #3498db;
    color: white;
}
.btn-sm {
    font-size: 0.85rem;
    padding: 0.3rem 1rem;
}

/* Essay cards */
.essays-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.essay-card {
    border: 1px solid #eef2f6;
    border-radius: 18px;
    background: white;
    overflow: hidden;
    transition: all 0.2s;
}
.essay-card:hover {
    box-shadow: 0 12px 28px rgba(0,0,0,0.04);
}
.essay-header {
    padding: 1.2rem 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.essay-title {
    margin: 0 0 0.3rem 0;
    font-size: 1.1rem;
    font-weight: 600;
}
.essay-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.85rem;
}
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.8rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}
.status-draft { background: #6c757d; }
.status-submitted { background: #f39c12; }
.status-under_review { background: #17a2b8; }
.status-reviewed { background: #28a745; }
.essay-date {
    color: #64748b;
}
.essay-actions {
    display: flex;
    gap: 0.5rem;
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
    cursor: pointer;
    transition: all 0.2s;
}
.btn-icon.btn-primary { background: #3498db; }
.btn-icon.btn-danger { background: #e74c3c; }
.btn-icon:hover {
    opacity: 0.8;
    transform: translateY(-2px);
}

.essay-content {
    padding: 1.5rem;
    border-bottom: 1px solid #eef2f6;
}
.content-preview, .content-full {
    line-height: 1.6;
    color: #2c3e50;
}
.attachment {
    margin-top: 1rem;
}

.feedback-section {
    padding: 1.5rem;
    background: #f8fafc;
}
.feedback-title {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.badge-success {
    background: #28a745;
    color: white;
    padding: 0.25rem 0.6rem;
    border-radius: 30px;
    font-size: 0.75rem;
}
.feedback-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.feedback-item {
    background: white;
    border-left: 4px solid #3498db;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.feedback-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}
.feedback-body {
    color: #2c3e50;
    line-height: 1.5;
}

.essay-footer {
    padding: 0.75rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #eef2f6;
    font-size: 0.85rem;
}

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
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr !important;
    }
    .essay-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>