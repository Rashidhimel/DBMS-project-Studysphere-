<?php
require_once '../../app/includes/init.php';
requireStudent();

$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;
if (!$application_id) {
    header('Location: applications.php');
    exit;
}

$page_title = "Payment Successful";
require_once '../../app/templates/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-check-circle text-success"></i> Payment Successful</h1>
</div>

<div class="dashboard-card text-center" style="padding: 3rem;">
    <i class="fas fa-check-circle fa-5x text-success" style="margin-bottom: 1rem;"></i>
    <h2>Thank You!</h2>
    <p class="lead">Your payment has been received successfully.</p>
    <p>You will receive a confirmation email shortly.</p>
    <div style="margin-top: 2rem;">
        <a href="application_view.php?id=<?= $application_id ?>" class="btn btn-primary">
            <i class="fas fa-eye"></i> View Application
        </a>
        <a href="applications.php" class="btn btn-secondary">
            <i class="fas fa-list"></i> All Applications
        </a>
    </div>
</div>

<?php require_once '../../app/templates/footer.php'; ?>