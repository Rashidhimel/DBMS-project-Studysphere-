<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Test Email";
require_once '../../app/templates/header.php';

$db = new Database();
$mailer = new MailHelper();

$templates = $db->fetchAll("SELECT * FROM email_templates ORDER BY name");

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_test'])) {
    $template_name = $_POST['template_name'];
    $test_email = $_POST['test_email'];
    $placeholders = [];
    // Parse any additional placeholders from form (optional)
    if (isset($_POST['placeholders']) && is_array($_POST['placeholders'])) {
        foreach ($_POST['placeholders'] as $key => $value) {
            $placeholders[$key] = $value;
        }
    }

    $result = $mailer->sendTemplate($template_name, $test_email, 'Test User', $placeholders);
    if ($result) {
        $success = "Test email sent successfully to $test_email";
    } else {
        $error = "Failed to send test email. Check logs.";
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Test Email Templates</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<div class="dashboard-card">
    <form method="POST">
        <div class="form-group">
            <label for="template_name">Select Template</label>
            <select name="template_name" id="template_name" class="form-control" required>
                <option value="">-- Choose --</option>
                <?php foreach ($templates as $tpl): ?>
                    <option value="<?= htmlspecialchars($tpl['name']) ?>">
                        <?= htmlspecialchars($tpl['name']) ?> - <?= htmlspecialchars($tpl['subject']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="test_email">Recipient Email</label>
            <input type="email" name="test_email" id="test_email" class="form-control" required value="<?= $_SESSION['email'] ?? '' ?>">
        </div>
        <div id="placeholders-container">
            <!-- Placeholder inputs will be added dynamically via JS -->
        </div>
        <button type="submit" name="send_test" class="btn btn-primary">Send Test Email</button>
    </form>
</div>

<script>
document.getElementById('template_name').addEventListener('change', function() {
    // Here you could fetch template details via AJAX to show required placeholders
    // For simplicity, we'll just show a generic placeholder input
    let container = document.getElementById('placeholders-container');
    container.innerHTML = '<div class="form-group"><label>Additional Placeholders (key=value per line)</label><textarea name="placeholders_raw" class="form-control" rows="4" placeholder="user_name=John&#10;application_id=123"></textarea></div>';
});
</script>

<?php require_once '../../app/templates/footer.php'; ?>