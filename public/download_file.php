<?php
require_once '../app/includes/init.php';
requireLogin();

$auth = new Auth();
$db = new Database();

$attachment_id = $_GET['id'] ?? 0;

if (!$attachment_id) {
    die('No file specified');
}


$file = $db->fetchOne(
    "SELECT * FROM attachments WHERE attachment_id = ? AND deleted_at IS NULL",
    [$attachment_id]
);

if (!$file) {
    die('File not found');
}


$user = $auth->getCurrentUser();

$is_exam_resource = $db->fetchOne(
    "SELECT resource_id FROM exam_resources WHERE attachment_id = ?",
    [$attachment_id]
);

if ($is_exam_resource) {

} elseif ($file['uploader_user_id'] != $user['user_id']) {

    $is_admin = $db->fetchOne(
        "SELECT * FROM user_roles WHERE user_id = ? AND role_id IN (SELECT role_id FROM roles WHERE name IN ('admin', 'super_admin'))",
        [$user['user_id']]
    );
    if (!$is_admin) {
        die('Access denied');
    }
}


$filepath = str_replace('/storage/', '../storage/', $file['file_url']);
$filepath = realpath($filepath);

if (!file_exists($filepath)) {
    die('File not found on server');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>