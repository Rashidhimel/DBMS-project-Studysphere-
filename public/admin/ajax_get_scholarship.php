<?php
require_once '../../app/includes/init.php';
requireAdmin();

$db = new Database();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid scholarship ID']);
    exit();
}

$id = intval($_GET['id']);

$scholarship = $db->fetchOne("
    SELECT s.*, p.program_name, u.name as university_name
    FROM scholarships s
    JOIN programs p ON s.program_id = p.program_id
    JOIN universities u ON p.university_id = u.university_id
    WHERE s.scholarship_id = ?
", [$id]);

if (!$scholarship) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Scholarship not found']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($scholarship);
?>