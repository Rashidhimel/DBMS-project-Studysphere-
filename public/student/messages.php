<?php
require_once '../../app/includes/init.php';
requireStudent();

$auth = new Auth();
$student_id = $auth->getStudentId();
$user = $auth->getCurrentUser();
$db = new Database();

// Get student's assigned counselor
$counselor = $auth->getStudentCounselor($student_id);
$counselor_id = $counselor ? $counselor['user_id'] : null;

// Initialize message variables
$success = null;
$error = null;

// Handle compose/reply
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $subject = Helpers::sanitize($_POST['subject'] ?? '');
    $body = Helpers::sanitize($_POST['body']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

    if (empty($body)) {
        $error = "Message body cannot be empty.";
    } else {
        // Insert message
        $db->insert('messages', [
            'sender_id' => $user['user_id'],
            'receiver_id' => $receiver_id,
            'subject' => $subject,
            'body' => $body,
            'parent_id' => $parent_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $success = "Message sent.";

        // If this is a reply, redirect back to the thread
        if ($parent_id) {
            header("Location: messages.php?thread=" . $parent_id);
            exit;
        } else {
            // For a new message, redirect to the messages list
            header("Location: messages.php");
            exit;
        }
    }
}

// Mark messages as read when viewing a thread
if (isset($_GET['thread'])) {
    $thread_id = intval($_GET['thread']);
    $db->query("
        UPDATE messages SET is_read = 1 
        WHERE (message_id = ? OR parent_id = ?) AND receiver_id = ? AND is_read = 0
    ", [$thread_id, $thread_id, $user['user_id']]);
}

// Get threads (conversations) for the user
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

// Determine if user can compose new message (needs a counselor)
$can_compose = $counselor !== null;

$page_title = "Messages";
require_once '../../app/templates/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Messages</h1>
    <p>Communicate with your counselor</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!$can_compose): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> You don't have an assigned counselor yet. Please contact admin.
    </div>
<?php endif; ?>

<div class="messages-container" style="display: flex; gap: 1rem; min-height: 500px;">
    <!-- Sidebar: threads list -->
    <div class="threads-sidebar" style="width: 300px; border-right: 1px solid #e9ecef; padding-right: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3>Conversations</h3>
            <?php if ($can_compose): ?>
                <button class="btn btn-primary btn-small" onclick="showComposeModal()">
                    <i class="fas fa-plus"></i> New
                </button>
            <?php endif; ?>
        </div>
        <?php if (empty($threads)): ?>
            <p class="text-muted">No conversations yet.</p>
        <?php else: ?>
            <div class="thread-list">
                <?php foreach ($threads as $thread): 
                    $other_party = ($thread['sender_id'] == $user['user_id']) ? $thread['receiver_name'] : $thread['sender_name'];
                    $unread = ($thread['receiver_id'] == $user['user_id'] && !$thread['is_read']);
                ?>
                    <a href="?thread=<?php echo $thread['message_id']; ?>" class="thread-item <?php echo (isset($_GET['thread']) && $_GET['thread'] == $thread['message_id']) ? 'active' : ''; ?>" style="display: block; padding: 0.75rem; border-bottom: 1px solid #f1f1f1; text-decoration: none; color: inherit;">
                        <div style="display: flex; justify-content: space-between;">
                            <strong><?php echo htmlspecialchars($other_party); ?></strong>
                            <?php if ($unread): ?>
                                <span class="badge" style="background: #dc3545; color: white; font-size: 0.7rem;">New</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars(substr($thread['subject'] ?: '(no subject)', 0, 30)); ?></div>
                        <div style="font-size: 0.8rem; color: #999;"><?php echo Helpers::formatDate($thread['last_reply'] ?: $thread['created_at']); ?></div>
                        <?php if ($thread['reply_count'] > 0): ?>
                            <small><?php echo $thread['reply_count']; ?> replies</small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main content: current thread -->
    <div class="thread-view" style="flex: 1; padding-left: 1rem;">
        <?php if ($selected_thread): ?>
            <h3><?php echo htmlspecialchars($selected_thread['subject'] ?: '(no subject)'); ?></h3>
            <div class="messages-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem; background: #f9f9f9;">
                <?php foreach ($thread_messages as $msg): 
                    $is_me = ($msg['sender_id'] == $user['user_id']);
                ?>
                    <div class="message <?php echo $is_me ? 'message-me' : 'message-other'; ?>" style="margin-bottom: 1rem; display: flex; <?php echo $is_me ? 'justify-content: flex-end;' : ''; ?>">
                        <div style="max-width: 70%; background: <?php echo $is_me ? '#007bff' : '#e9ecef'; ?>; color: <?php echo $is_me ? 'white' : '#333'; ?>; padding: 0.75rem; border-radius: 8px;">
                            <div style="font-size: 0.8rem; margin-bottom: 0.25rem;">
                                <strong><?php echo $is_me ? 'You' : htmlspecialchars($msg['sender_name']); ?></strong> · <?php echo Helpers::formatDate($msg['created_at']); ?>
                            </div>
                            <div><?php echo nl2br(htmlspecialchars($msg['body'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply form -->
            <?php if ($can_compose): ?>
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="receiver_id" value="<?php echo ($selected_thread['sender_id'] == $user['user_id']) ? $selected_thread['receiver_id'] : $selected_thread['sender_id']; ?>">
                    <input type="hidden" name="parent_id" value="<?php echo $selected_thread['message_id']; ?>">
                    <input type="hidden" name="subject" value="Re: <?php echo htmlspecialchars($selected_thread['subject']); ?>">
                    <div class="form-group">
                        <textarea name="body" class="form-control" rows="3" placeholder="Type your reply..."></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary">
                        <i class="fas fa-reply"></i> Send Reply
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state" style="text-align: center; padding: 3rem;">
                <i class="fas fa-comments fa-4x" style="color: #ccc;"></i>
                <h3>Select a conversation</h3>
                <p>Choose a thread from the left or start a new message.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Compose Modal -->
<div id="composeModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>New Message</h3>
            <button class="close" onclick="closeModal('composeModal')">&times;</button>
        </div>
        <form method="POST">
            <?php if ($counselor): ?>
                <input type="hidden" name="receiver_id" value="<?php echo $counselor['user_id']; ?>">
                <div class="form-group">
                    <label>To:</label>
                    <p class="form-control-static"><?php echo htmlspecialchars($counselor['first_name'] . ' ' . $counselor['last_name']); ?> (Counselor)</p>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" name="subject" id="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="body">Message</label>
                <textarea name="body" id="body" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" name="send_message" class="btn btn-primary btn-block">Send</button>
        </form>
    </div>
</div>

<script>
function showComposeModal() {
    document.getElementById('composeModal').style.display = 'block';
}
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>