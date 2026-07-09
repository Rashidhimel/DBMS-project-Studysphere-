<?php
require_once '../app/includes/init.php';
requireLogin();

$session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$session_id) {
    die('Invalid request');
}

$db = new Database();
$auth = new Auth();
$user = $auth->getCurrentUser();

// Determine role and fetch session accordingly
if ($user['role'] == 'student') {
    $student_id = $auth->getStudentId();
    $session = $db->fetchOne("
        SELECT cs.*, u.username as counselor_name
        FROM counseling_sessions cs
        JOIN counselor_assignments ca ON cs.assignment_id = ca.assignment_id
        WHERE cs.session_id = ? AND ca.student_id = ? AND cs.is_booked = 1
    ", [$session_id, $student_id]);
} elseif ($user['role'] == 'counselor') {
    $session = $db->fetchOne("
        SELECT cs.*, s.student_id
        FROM counseling_sessions cs
        JOIN counselor_assignments ca ON cs.assignment_id = ca.assignment_id
        WHERE cs.session_id = ? AND ca.counselor_id = ?
    ", [$session_id, $user['user_id']]);
} else {
    die('Access denied');
}

if (!$session) {
    die('Session not found or not booked');
}

// Prepare data for ICS
$session_data = [
    'title' => 'Counselling Session with ' . ($session['counselor_name'] ?? 'Counselor'),
    'description' => $session['notes'] ?? 'Counselling session via StudySphere',
    'scheduled_at' => $session['scheduled_at'],
    'duration_minutes' => $session['duration_minutes'] ?? 60,
    'location' => $session['meeting_link'] ?? 'Online (link provided separately)'
];

$ics = Helpers::generateICS($session_data);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="session_' . $session_id . '.ics"');
echo $ics;
exit;