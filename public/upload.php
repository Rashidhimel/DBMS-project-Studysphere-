<?php
require_once '../app/includes/init.php';
requireLogin();

$page_title = "Upload File";
require_once '../app/templates/header.php';
$auth = new Auth();
$user = $auth->getCurrentUser();
$upload_helper = new UploadHelper();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $result = $upload_helper->uploadFile($_FILES['file'], $user['user_id']);
    
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $success = "File uploaded successfully!";
    }
}


$attachments = $upload_helper->getUserAttachments($user['user_id']);
?>

<div class="page-header">
    <h1><i class="fas fa-upload"></i> Upload Files</h1>
    <p>Upload your documents, transcripts, and other files</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <h3>Upload New File</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="file">Select File</label>
            <input type="file" id="file" name="file" required>
            <small>Allowed: PDF, DOC, DOCX, JPG, PNG (Max: 5MB)</small>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>

<div class="card">
    <h3>Your Uploaded Files</h3>
    
    <?php if (empty($attachments)): ?>
        <p>No files uploaded yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attachments as $file): ?>
                    <tr>
                        <td><?php echo $file['filename']; ?></td>
                        <td><?php echo $file['file_type']; ?></td>
                        <td><?php echo round($file['file_size'] / 1024, 2); ?> KB</td>
                        <td><?php echo Helpers::formatDate($file['created_at']); ?></td>
                        <td>
                            <a href="<?php echo $file['file_url']; ?>" class="btn btn-small" target="_blank">View</a>
                            <a href="download_file.php?id=<?php echo $file['attachment_id']; ?>" class="btn btn-small">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once '../app/templates/footer.php'; ?>