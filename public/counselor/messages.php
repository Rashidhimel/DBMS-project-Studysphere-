<?php
require_once '../../app/includes/init.php';
requireCounselor();

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

// Initialize message variables
$success = null;
$error = null;

// Get all students assigned to this counselor for quick "to" dropdown
$students = $auth->getAssignedStudents($user['user_id']);

// Handle compose/reply
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $subject = Helpers::sanitize($_POST['subject'] ?? '');
    $body = Helpers::sanitize($_POST['body']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

    if (empty($body)) {
        $error = "Message body cannot be empty.";
    } else {
        $db->insert('messages', [
            'sender_id' => $user['user_id'],
            'receiver_id' => $receiver_id,
            'subject' => $subject,
            'body' => $body,
            'parent_id' => $parent_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $success = "Message sent.";
        if ($parent_id) {
            header("Location: messages.php?thread=" . $parent_id);
            exit;
        } else {
            header("Location: messages.php");
            exit;
        }
    }
}

// Mark messages as read when viewing thread
if (isset($_GET['thread'])) {
    $thread_id = intval($_GET['thread']);
    $db->query("
        UPDATE messages SET is_read = 1 
        WHERE (message_id = ? OR parent_id = ?) AND receiver_id = ? AND is_read = 0
    ", [$thread_id, $thread_id, $user['user_id']]);
}

// Get threads (conversations) for this counselor
$threads = $db->fetchAll("
    SELECT 
        m.message_id,
        m.subject,
        m.body,
        m.created_at,
        m.is_read,
        sender.username as sender_name,
        sender.user_id as sender_id,
        receiver.username as receiver_name,
        receiver.user_id as receiver_id,
        (SELECT COUNT(*) FROM messages WHERE parent_id = m.message_id) as reply_count,
        (SELECT MAX(created_at) FROM messages WHERE parent_id = m.message_id) as last_reply
    FROM messages m
    JOIN users sender ON m.sender_id = sender.user_id
    JOIN users receiver ON m.receiver_id = receiver.user_id
    WHERE m.parent_id IS NULL AND (m.sender_id = ? OR m.receiver_id = ?)
    ORDER BY COALESCE(last_reply, m.created_at) DESC
", [$user['user_id'], $user['user_id']]);

// Compute stats
$unread_count = 0;
foreach ($threads as $t) {
    if ($t['receiver_id'] == $user['user_id'] && !$t['is_read']) {
        $unread_count++;
    }
}
$total_threads = count($threads);
$total_replies = 0;
foreach ($threads as $t) {
    $total_replies += $t['reply_count'];
}

// If a thread is selected, load its messages
$selected_thread = null;
$thread_messages = [];
if (isset($_GET['thread'])) {
    $thread_id = intval($_GET['thread']);
    $selected_thread = $db->fetchOne("
        SELECT m.*, sender.username as sender_name, receiver.username as receiver_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.user_id
        JOIN users receiver ON m.receiver_id = receiver.user_id
        WHERE m.message_id = ?
    ", [$thread_id]);
    if ($selected_thread) {
        $thread_messages = $db->fetchAll("
            SELECT m.*, sender.username as sender_name, receiver.username as receiver_name
            FROM messages m
            JOIN users sender ON m.sender_id = sender.user_id
            JOIN users receiver ON m.receiver_id = receiver.user_id
            WHERE m.message_id = ? OR m.parent_id = ?
            ORDER BY m.created_at ASC
        ", [$thread_id, $thread_id]);
    }
}

// Pre-fill recipient if specified
$to_user_id = isset($_GET['to']) ? intval($_GET['to']) : 0;
$to_user = null;
if ($to_user_id) {
    $to_user = $db->fetchOne("SELECT user_id, username FROM users WHERE user_id = ?", [$to_user_id]);
}

$page_title = "Messages";
require_once '../../app/templates/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Messages</h1>
    <p class="text-muted">Communicate with your assigned students</p>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-comments"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_threads; ?></h3>
            <p>Conversations</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_replies; ?></h3>
            <p>Total Messages</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-envelope-open-text"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $unread_count; ?></h3>
            <p>Unread</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo count($students); ?></h3>
            <p>Assigned Students</p>
        </div>
    </div>
</div>

<!-- Messages Container (Two‑panel layout) -->
<div class="messages-container">
    <!-- Sidebar: threads list -->
    <div class="threads-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-list"></i> Conversations</h3>
            <button class="btn-icon btn-primary" onclick="showComposeModal()" title="New Message">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div class="thread-list scrollable">
            <?php if (empty($threads)): ?>
                <p class="empty-message">No conversations yet.</p>
            <?php else: ?>
                <?php foreach ($threads as $thread): 
                    $other_party = ($thread['sender_id'] == $user['user_id']) ? $thread['receiver_name'] : $thread['sender_name'];
                    $unread = ($thread['receiver_id'] == $user['user_id'] && !$thread['is_read']);
                ?>
                    <a href="?thread=<?php echo $thread['message_id']; ?>" class="thread-item <?php echo (isset($_GET['thread']) && $_GET['thread'] == $thread['message_id']) ? 'active' : ''; ?>">
                        <div class="thread-header">
                            <span class="thread-name"><?php echo htmlspecialchars($other_party); ?></span>
                            <?php if ($unread): ?>
                                <span class="badge badge-danger">New</span>
                            <?php endif; ?>
                        </div>
                        <div class="thread-subject"><?php echo htmlspecialchars($thread['subject'] ?: '(no subject)'); ?></div>
                        <div class="thread-date"><?php echo Helpers::formatDate($thread['last_reply'] ?: $thread['created_at']); ?></div>
                        <?php if ($thread['reply_count'] > 0): ?>
                            <small><?php echo $thread['reply_count']; ?> replies</small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main content: current thread -->
    <div class="thread-view">
        <?php if ($selected_thread): ?>
            <div class="thread-header">
                <h3><?php echo htmlspecialchars($selected_thread['subject'] ?: '(no subject)'); ?></h3>
            </div>
            <div class="messages-list scrollable">
                <?php foreach ($thread_messages as $msg): 
                    $is_me = ($msg['sender_id'] == $user['user_id']);
                ?>
                    <div class="message <?php echo $is_me ? 'message-me' : 'message-other'; ?>">
                        <div class="message-bubble">
                            <div class="message-meta">
                                <strong><?php echo $is_me ? 'You' : htmlspecialchars($msg['sender_name']); ?></strong>
                                <span>· <?php echo Helpers::formatDate($msg['created_at']); ?></span>
                            </div>
                            <div class="message-body"><?php echo nl2br(htmlspecialchars($msg['body'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply form -->
            <div class="reply-form">
                <form method="POST">
                    <input type="hidden" name="receiver_id" value="<?php echo ($selected_thread['sender_id'] == $user['user_id']) ? $selected_thread['receiver_id'] : $selected_thread['sender_id']; ?>">
                    <input type="hidden" name="parent_id" value="<?php echo $selected_thread['message_id']; ?>">
                    <input type="hidden" name="subject" value="Re: <?php echo htmlspecialchars($selected_thread['subject']); ?>">
                    <div class="form-group">
                        <textarea name="body" class="form-control" rows="2" placeholder="Type your reply..."></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary">
                        <i class="fas fa-reply"></i> Send Reply
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-comments fa-4x"></i>
                <h3>Select a conversation</h3>
                <p>Choose a thread from the left or start a new message.</p>
                <button class="btn btn-primary" onclick="showComposeModal()">
                    <i class="fas fa-plus"></i> New Message
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Compose Modal -->
<div id="composeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>New Message</h3>
            <button class="close" onclick="closeModal('composeModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label for="receiver_id">To:</label>
                <select name="receiver_id" id="receiver_id" class="form-control" required>
                    <option value="">Select Student</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['user_id']; ?>" <?php echo ($to_user && $to_user['user_id'] == $s['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] ?: $s['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" name="subject" id="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="body">Message</label>
                <textarea name="body" id="body" class="form-control" rows="5" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="send_message" class="btn btn-primary">Send</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('composeModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Reuse stats grid from other pages */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 18px;
    padding: 1rem 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.stat-content h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Messages container */
.messages-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 1.5rem;
    min-height: 500px;
}

/* Sidebar */
.threads-sidebar {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.sidebar-header {
    padding: 1rem 1.2rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sidebar-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    background: #f8f9fa;
    color: #2c3e50;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-icon.btn-primary {
    background: #3498db;
    color: white;
}
.btn-icon.btn-primary:hover {
    background: #2980b9;
}
.thread-list {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
}
.thread-item {
    display: block;
    padding: 0.75rem 1.2rem;
    border-bottom: 1px solid #f1f1f1;
    text-decoration: none;
    color: inherit;
    transition: background 0.2s;
}
.thread-item:hover {
    background: #f8fafc;
}
.thread-item.active {
    background: #e3f2fd;
    border-left: 4px solid #3498db;
}
.thread-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.2rem;
}
.thread-name {
    font-weight: 600;
    color: #2c3e50;
}
.thread-subject {
    font-size: 0.9rem;
    color: #4a5568;
    margin-bottom: 0.2rem;
}
.thread-date {
    font-size: 0.75rem;
    color: #7f8c8d;
}
.badge.badge-danger {
    background: #dc3545;
    color: white;
    padding: 0.15rem 0.5rem;
    border-radius: 30px;
    font-size: 0.7rem;
}

/* Thread view */
.thread-view {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.thread-view .thread-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
}
.thread-view .thread-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
}
.messages-list {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: #f9f9f9;
    max-height: 400px;
}
.message {
    display: flex;
    margin-bottom: 1rem;
}
.message.message-me {
    justify-content: flex-end;
}
.message-bubble {
    max-width: 70%;
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 18px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.message-me .message-bubble {
    background: #007bff;
    color: white;
}
.message-other .message-bubble {
    background: #e9ecef;
    color: #333;
}
.message-meta {
    font-size: 0.75rem;
    margin-bottom: 0.25rem;
    opacity: 0.8;
}
.message-body {
    word-wrap: break-word;
}
.reply-form {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eef2f6;
}

/* Form elements */
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.3rem;
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
.btn {
    border-radius: 30px;
    padding: 0.5rem 1.5rem;
    border: none;
    cursor: pointer;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: #3498db;
    color: white;
}
.btn-primary:hover {
    background: #2980b9;
}
.btn-secondary {
    background: #e2e8f0;
    color: #2c3e50;
}
.btn-secondary:hover {
    background: #cbd5e0;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #7f8c8d;
}
.empty-state i {
    color: #ccc;
    margin-bottom: 1rem;
}
.empty-state h3 {
    margin: 0 0 0.5rem;
    color: #2c3e50;
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
    background: white;
    max-width: 500px;
    width: 90%;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: modalSlide 0.3s ease;
    overflow: hidden;
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
}
.close {
    background: none;
    border: none;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    color: #a0aec0;
}
.close:hover {
    color: #718096;
}
.modal-body {
    padding: 2rem;
}
.form-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 1rem;
}

/* Scrollable areas */
.scrollable {
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f1f5f9;
}
.scrollable::-webkit-scrollbar {
    width: 6px;
}
.scrollable::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.scrollable::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 900px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .messages-container {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showComposeModal() {
    document.getElementById('composeModal').style.display = 'flex';
}
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('composeModal');
    if (event.target == modal) {
        closeModal('composeModal');
    }
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>