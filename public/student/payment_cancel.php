<?php
require_once '../../app/includes/init.php';
requireStudent();

$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;
if (!$application_id) {
    header('Location: applications.php');
    exit;
}

$page_title = "Payment Cancelled";
require_once '../../app/templates/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-times-circle text-warning"></i> Payment Cancelled</h1>
</div>

<div class="dashboard-card text-center" style="padding: 3rem;">
    <i class="fas fa-times-circle fa-5x text-warning" style="margin-bottom: 1rem;"></i>
    <h2>Payment Not Completed</h2>
    <p class="lead">Your payment was cancelled or not completed.</p>
    <p>You can try again or contact support if you need assistance.</p>
    <div style="margin-top: 2rem;">
        <a href="pay_fee.php?application_id=<?= $application_id ?>" class="btn btn-primary">
            <i class="fas fa-credit-card"></i> Try Again
        </a>
        <a href="application_view.php?id=<?= $application_id ?>" class="btn btn-secondary">
            <i class="fas fa-eye"></i> Back to Application
        </a>
    </div>
</div>

<?php require_once '../../app/templates/footer.php'; ?>