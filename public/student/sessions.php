<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Counselling Sessions";
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$user = $auth->getCurrentUser();
$db = new Database();

// Get student's assigned counselor
$counselor = $auth->getStudentCounselor($student_id);
$counselor_id = $counselor ? $counselor['user_id'] : null;

// Handle booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_session'])) {
    $session_id = intval($_POST['session_id']);
    
    // Check if session exists and is available
    $session = $db->fetchOne(
        "SELECT * FROM counseling_sessions WHERE session_id = ? AND is_booked = 0 AND scheduled_at > NOW()",
        [$session_id]
    );
    
    if ($session && $counselor_id) {
        // Ensure the session belongs to the student's counselor (optional, but good)
        $assignment = $db->fetchOne(
            "SELECT assignment_id FROM counselor_assignments 
             WHERE student_id = ? AND counselor_id = ? AND is_active = 1",
            [$student_id, $counselor_id]
        );
        
        if ($assignment) {
            // Book the session
            $db->update('counseling_sessions', [
                'is_booked' => 1,
                'booked_by_student_id' => $student_id
            ], "session_id = $session_id");
            
            // Optionally create a notification for the counselor
            $success = "Session booked successfully!";
        } else {
            $error = "You are not assigned to this counselor.";
        }
    } else {
        $error = "This session is no longer available.";
    }
}

// Handle cancellation (if within allowed time, e.g., 24h before)
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $session_id = intval($_GET['cancel']);
    
    $session = $db->fetchOne(
        "SELECT * FROM counseling_sessions 
         WHERE session_id = ? AND booked_by_student_id = ? AND scheduled_at > DATE_ADD(NOW(), INTERVAL 24 HOUR)",
        [$session_id, $student_id]
    );
    
    if ($session) {
        $db->update('counseling_sessions', [
            'is_booked' => 0,
            'booked_by_student_id' => null
        ], "session_id = $session_id");
        $success = "Session cancelled successfully.";
    } else {
        $error = "Cannot cancel this session (too close to start time or not found).";
    }
}

// Get available sessions (created by the student's counselor, not booked, future)
$available_sessions = [];
if ($counselor_id) {
    $available_sessions = $db->fetchAll("
        SELECT cs.*, u.username as counselor_name, up.first_name, up.last_name
        FROM counseling_sessions cs
        JOIN users u ON cs.created_by = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE cs.assignment_id IN (
            SELECT assignment_id FROM counselor_assignments 
            WHERE student_id = ? AND is_active = 1
        )
        AND cs.is_booked = 0
        AND cs.scheduled_at > NOW()
        ORDER BY cs.scheduled_at ASC
    ", [$student_id]);
}

// Get student's booked sessions
$my_sessions = $db->fetchAll("
    SELECT cs.*, u.username as counselor_name, up.first_name, up.last_name
    FROM counseling_sessions cs
    JOIN users u ON cs.created_by = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE cs.booked_by_student_id = ?
    ORDER BY cs.scheduled_at DESC
", [$student_id]);

// Get past sessions (for history)
$past_sessions = array_filter($my_sessions, function($s) {
    return strtotime($s['scheduled_at']) < time();
});
$upcoming_sessions = array_filter($my_sessions, function($s) {
    return strtotime($s['scheduled_at']) >= time();
});
?>

<div class="page-header">
    <h1><i class="fas fa-calendar-alt"></i> Counselling Sessions</h1>
    <p>Book one-on-one sessions with your counselor</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!$counselor_id): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> You don't have an assigned counselor yet. Please contact admin.
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- Upcoming Booked Sessions -->
    <div class="dashboard-card">
        <h3><i class="fas fa-clock"></i> My Upcoming Sessions</h3>
        <?php if (empty($upcoming_sessions)): ?>
            <p class="text-muted">No upcoming sessions.</p>
        <?php else: ?>
            <div class="sessions-list">
                <?php foreach ($upcoming_sessions as $session): 
                    $counselor_name = $session['first_name'] ? $session['first_name'] . ' ' . $session['last_name'] : $session['counselor_name'];
                ?>
                    <div class="session-item" style="border-bottom: 1px solid #eee; padding: 1rem 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo Helpers::formatDate($session['scheduled_at'], 'F j, Y g:i A'); ?></strong><br>
                                <small>with <?php echo htmlspecialchars($counselor_name); ?></small><br>
                                <small>Type: <?php echo ucfirst($session['session_type']); ?></small>
                                <?php if ($session['meeting_link']): ?>
                                    <br><a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" target="_blank">Join Meeting</a>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <?php if ($session['is_booked'] && $session['booked_by_student_id'] == $student_id): ?>
                                    <a href="/studysphere/public/download_ics.php?id=<?= $session['session_id'] ?>" class="btn btn-small btn-success">
                                        <i class="fas fa-calendar-plus"></i> Add to Calendar
                                    </a>
                                <?php endif; ?>
                                <?php if (strtotime($session['scheduled_at']) > strtotime('+24 hours')): ?>
                                    <a href="?cancel=<?php echo $session['session_id']; ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Are you sure you want to cancel this session?')">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Available Sessions -->
    <div class="dashboard-card">
        <h3><i class="fas fa-calendar-plus"></i> Available Slots</h3>
        <?php if (!$counselor_id): ?>
            <p class="text-muted">You need an assigned counselor to book sessions.</p>
        <?php elseif (empty($available_sessions)): ?>
            <p class="text-muted">No available sessions at the moment. Check back later.</p>
        <?php else: ?>
            <div class="sessions-list">
                <?php foreach ($available_sessions as $session): 
                    $counselor_name = $session['first_name'] ? $session['first_name'] . ' ' . $session['last_name'] : $session['counselor_name'];
                ?>
                    <div class="session-item" style="border-bottom: 1px solid #eee; padding: 1rem 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo Helpers::formatDate($session['scheduled_at'], 'F j, Y g:i A'); ?></strong><br>
                                <small>with <?php echo htmlspecialchars($counselor_name); ?></small><br>
                                <small>Type: <?php echo ucfirst($session['session_type']); ?></small>
                                <?php if ($session['duration_minutes']): ?>
                                    <br><small>Duration: <?php echo $session['duration_minutes']; ?> minutes</small>
                                <?php endif; ?>
                            </div>
                            <div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                    <button type="submit" name="book_session" class="btn btn-primary btn-small">
                                        Book
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Past Sessions History -->
<div class="dashboard-card" style="margin-top: 2rem;">
    <h3><i class="fas fa-history"></i> Past Sessions</h3>
    <?php if (empty($past_sessions)): ?>
        <p class="text-muted">No past sessions.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Counselor</th>
                    <th>Type</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past_sessions as $session): 
                    $counselor_name = $session['first_name'] ? $session['first_name'] . ' ' . $session['last_name'] : $session['counselor_name'];
                ?>
                    <tr>
                        <td><?php echo Helpers::formatDate($session['scheduled_at'], 'M j, Y g:i A'); ?></td>
                        <td><?php echo htmlspecialchars($counselor_name); ?></td>
                        <td><?php echo ucfirst($session['session_type']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($session['notes'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.session-item:last-child {
    border-bottom: none !important;
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>