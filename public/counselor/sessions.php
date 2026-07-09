<?php
require_once '../../app/includes/init.php';
requireCounselor();

$page_title = "Manage Sessions";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_session'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $scheduled_at = $_POST['scheduled_at'] . ' ' . $_POST['scheduled_time'];
        $duration = intval($_POST['duration']);
        $session_type = Helpers::sanitize($_POST['session_type']);
        $meeting_link = Helpers::sanitize($_POST['meeting_link'] ?? '');
        $notes = Helpers::sanitize($_POST['notes'] ?? '');

        $db->insert('counseling_sessions', [
            'assignment_id' => $assignment_id,
            'scheduled_at' => $scheduled_at,
            'duration_minutes' => $duration,
            'session_type' => $session_type,
            'meeting_link' => $meeting_link,
            'notes' => $notes,
            'created_by' => $user['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $success = "Session slot created.";
    } elseif (isset($_POST['update_session'])) {
        $session_id = intval($_POST['session_id']);
        $scheduled_at = $_POST['scheduled_at'] . ' ' . $_POST['scheduled_time'];
        $duration = intval($_POST['duration']);
        $session_type = Helpers::sanitize($_POST['session_type']);
        $meeting_link = Helpers::sanitize($_POST['meeting_link'] ?? '');
        $notes = Helpers::sanitize($_POST['notes'] ?? '');

        $db->update('counseling_sessions', [
            'scheduled_at' => $scheduled_at,
            'duration_minutes' => $duration,
            'session_type' => $session_type,
            'meeting_link' => $meeting_link,
            'notes' => $notes
        ], "session_id = $session_id");
        $success = "Session updated.";
    } elseif (isset($_POST['delete_session'])) {
        $session_id = intval($_POST['session_id']);
        $session = $db->fetchOne("SELECT is_booked FROM counseling_sessions WHERE session_id = ?", [$session_id]);
        if ($session && !$session['is_booked']) {
            $db->query("DELETE FROM counseling_sessions WHERE session_id = ?", [$session_id]);
            $success = "Session deleted.";
        } else {
            $error = "Cannot delete a booked session.";
        }
    }
}

// Get all assignments (students) for this counselor
$assignments = $db->fetchAll("
    SELECT ca.assignment_id, s.student_id, u.username, up.first_name, up.last_name
    FROM counselor_assignments ca
    JOIN students s ON ca.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE ca.counselor_id = ? AND ca.is_active = 1
", [$user['user_id']]);

// Get all sessions
$sessions = $db->fetchAll("
    SELECT cs.*, 
           s.student_id, u.username as student_username, 
           up.first_name, up.last_name,
           ca.assignment_id
    FROM counseling_sessions cs
    JOIN counselor_assignments ca ON cs.assignment_id = ca.assignment_id
    LEFT JOIN students s ON ca.student_id = s.student_id
    LEFT JOIN users u ON s.user_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE ca.counselor_id = ?
    ORDER BY cs.scheduled_at DESC
", [$user['user_id']]);

// Compute stats
$total_sessions = count($sessions);
$booked_sessions = count(array_filter($sessions, fn($s) => $s['is_booked']));
$available_sessions = $total_sessions - $booked_sessions;
$upcoming_sessions = count(array_filter($sessions, fn($s) => strtotime($s['scheduled_at']) > time()));

// For editing, fetch single session
$edit_session = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_session = $db->fetchOne("SELECT * FROM counseling_sessions WHERE session_id = ?", [$edit_id]);
}
?>

<div class="page-header">
    <h1><i class="fas fa-calendar-alt"></i> Manage Counselling Sessions</h1>
    <p class="text-muted">Create, edit, and view all your counselling sessions</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-error alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-calendar"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $total_sessions; ?></h3>
            <p>Total Sessions</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #27ae60, #2ecc71);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $booked_sessions; ?></h3>
            <p>Booked</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $available_sessions; ?></h3>
            <p>Available</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #9b59b6, #8e44ad);">
            <i class="fas fa-hourglass-start"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $upcoming_sessions; ?></h3>
            <p>Upcoming</p>
        </div>
    </div>
</div>

<!-- Two‑column layout -->
<div class="dashboard-grid">
    <!-- Left column: Create/Edit Form -->
    <div class="card form-card">
        <div class="card-header">
            <h3><?php echo $edit_session ? 'Edit Session' : 'Create New Session Slot'; ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($edit_session): ?>
                    <input type="hidden" name="session_id" value="<?php echo $edit_session['session_id']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="assignment_id">Student</label>
                    <select name="assignment_id" id="assignment_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($assignments as $a): 
                            $student_name = $a['first_name'] ? $a['first_name'] . ' ' . $a['last_name'] : $a['username'];
                        ?>
                            <option value="<?php echo $a['assignment_id']; ?>" 
                                <?php if ($edit_session && $edit_session['assignment_id'] == $a['assignment_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($student_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label for="scheduled_at">Date</label>
                        <input type="date" name="scheduled_at" id="scheduled_at" class="form-control" required 
                               value="<?php echo $edit_session ? date('Y-m-d', strtotime($edit_session['scheduled_at'])) : ''; ?>">
                    </div>
                    <div class="form-group flex-1">
                        <label for="scheduled_time">Time</label>
                        <input type="time" name="scheduled_time" id="scheduled_time" class="form-control" required
                               value="<?php echo $edit_session ? date('H:i', strtotime($edit_session['scheduled_at'])) : ''; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="duration">Duration (minutes)</label>
                    <input type="number" name="duration" id="duration" class="form-control" min="15" step="15" required
                           value="<?php echo $edit_session ? $edit_session['duration_minutes'] : '30'; ?>">
                </div>
                <div class="form-group">
                    <label for="session_type">Session Type</label>
                    <select name="session_type" id="session_type" class="form-control" required>
                        <option value="call" <?php echo ($edit_session && $edit_session['session_type'] == 'call') ? 'selected' : ''; ?>>Phone Call</option>
                        <option value="video" <?php echo ($edit_session && $edit_session['session_type'] == 'video') ? 'selected' : ''; ?>>Video Call</option>
                        <option value="meeting" <?php echo ($edit_session && $edit_session['session_type'] == 'meeting') ? 'selected' : ''; ?>>In-person Meeting</option>
                        <option value="email" <?php echo ($edit_session && $edit_session['session_type'] == 'email') ? 'selected' : ''; ?>>Email</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="meeting_link">Meeting Link (if applicable)</label>
                    <input type="url" name="meeting_link" id="meeting_link" class="form-control"
                           value="<?php echo $edit_session ? htmlspecialchars($edit_session['meeting_link']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="notes">Notes (optional)</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"><?php echo $edit_session ? htmlspecialchars($edit_session['notes']) : ''; ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="<?php echo $edit_session ? 'update_session' : 'create_session'; ?>" class="btn btn-primary">
                        <i class="fas fa-<?php echo $edit_session ? 'save' : 'plus'; ?>"></i>
                        <?php echo $edit_session ? 'Update Session' : 'Create Session'; ?>
                    </button>
                    <?php if ($edit_session): ?>
                        <a href="sessions.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Right column: Sessions List -->
    <div class="card list-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Sessions (<?php echo $total_sessions; ?>)</h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($sessions)): ?>
                <p class="empty-message">No sessions created yet.</p>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Scheduled</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): 
                                $student_name = $s['first_name'] ? $s['first_name'] . ' ' . $s['last_name'] : ($s['student_username'] ?: 'No student');
                                $is_past = strtotime($s['scheduled_at']) < time();
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student_name); ?></td>
                                    <td><?php echo Helpers::formatDate($s['scheduled_at'], 'M j, Y g:i A'); ?></td>
                                    <td><?php echo ucfirst($s['session_type']); ?></td>
                                    <td><?php echo $s['duration_minutes']; ?> min</td>
                                    <td>
                                        <?php if ($s['is_booked']): ?>
                                            <span class="badge badge-success">Booked</span>
                                        <?php elseif ($is_past): ?>
                                            <span class="badge badge-secondary">Past</span>
                                        <?php else: ?>
                                            <span class="badge badge-primary">Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if (!$s['is_booked']): ?>
                                                <a href="?edit=<?php echo $s['session_id']; ?>" class="btn-icon-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this session?');">
                                                    <input type="hidden" name="session_id" value="<?php echo $s['session_id']; ?>">
                                                    <button type="submit" name="delete_session" class="btn-icon-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($s['is_booked']): ?>
                                                <a href="/studysphere/public/download_ics.php?id=<?= $s['session_id'] ?>" class="btn-icon-sm btn-success" title="Add to Calendar">
                                                    <i class="fas fa-calendar-plus"></i>
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
    </div>
</div>

<style>
/* Reuse styles from other admin/counselor pages */
.page-header {
    margin-bottom: 2rem;
}
.page-header h1 {
    margin-bottom: 0.25rem;
}

/* Stats grid (4 cards) */
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

/* Two‑column grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Cards */
.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    overflow: hidden;
}
.card-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
}
.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
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

/* Form elements */
.form-group {
    margin-bottom: 1.2rem;
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
.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.2rem;
}
.flex-2 { flex: 2; }
.flex-1 { flex: 1; }
.form-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

/* Scrollable table */
.scrollable-table {
    max-height: 500px;
    overflow-y: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}
.table th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    padding: 0.75rem 0.75rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
    z-index: 2;
}
.table td {
    padding: 0.75rem 0.75rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}
.table tr:hover td {
    background: #f8fafc;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-primary { background: #cce5ff; color: #004085; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}
.btn-icon-sm {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.btn-icon-sm.btn-primary { background: #3498db; }
.btn-icon-sm.btn-primary:hover { background: #2980b9; }
.btn-icon-sm.btn-danger { background: #e74c3c; }
.btn-icon-sm.btn-danger:hover { background: #c0392b; }
.btn-icon-sm.btn-success { background: #27ae60; }
.btn-icon-sm.btn-success:hover { background: #219653; }

/* Buttons */
.btn {
    border-radius: 30px;
    padding: 0.5rem 1.2rem;
    border: none;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
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

/* Empty message */
.empty-message {
    padding: 2rem;
    text-align: center;
    color: #7f8c8d;
    margin: 0;
}

/* Responsive */
@media (max-width: 900px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>