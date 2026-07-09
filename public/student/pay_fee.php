<?php
require_once '../../app/includes/init.php';
requireStudent();

// Load Stripe library
require_once __DIR__ . '/../../vendor/autoload.php';

// Load Stripe keys from environment (set in .env or config)
$stripe_secret_key = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_...'; // fallback for testing
$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_...';

\Stripe\Stripe::setApiKey($stripe_secret_key);

$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;
$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

// Verify application belongs to student
$application = $db->fetchOne("
    SELECT a.*, p.program_name, u.name as university_name,
           af.amount, af.currency, af.application_fee_id
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    LEFT JOIN application_fees af ON p.program_id = af.program_id
    WHERE a.application_id = ? AND a.student_id = ? AND a.deleted_at IS NULL
", [$application_id, $student_id]);

if (!$application) {
    Helpers::redirect('applications.php', 'Application not found.', 'error');
}

// Check if already paid
if (Helpers::isFeePaid($application_id)) {
    Helpers::redirect('application_view.php?id=' . $application_id, 'Fee already paid.', 'info');
}

// If no fee, redirect
if (!$application['amount']) {
    Helpers::redirect('application_view.php?id=' . $application_id, 'No fee required.', 'info');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['stripeToken'])) {
    $token = $_POST['stripeToken'];
    $amount_in_cents = round($application['amount'] * 100); // Assuming amount in dollars

    try {
        // Create a charge
        $charge = \Stripe\Charge::create([
            'amount' => $amount_in_cents,
            'currency' => strtolower($application['currency']),
            'description' => 'Application fee for ' . $application['program_name'] . ' - ' . $application['university_name'],
            'source' => $token,
            'metadata' => [
                'application_id' => $application_id,
                'student_id' => $student_id,
                'program_id' => $application['program_id']
            ]
        ]);

        // Record payment in database
        $db->insert('fee_payments', [
            'application_id' => $application_id,
            'amount_paid' => $application['amount'],
            'currency' => $application['currency'],
            'payment_method' => 'stripe',
            'transaction_id' => $charge->id,
            'paid_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
            'gateway_data' => json_encode($charge->toArray()),
            'created_by' => $_SESSION['user_id']
        ]);

        // After inserting fee_payment record
        $student = $db->fetchOne("SELECT u.email, u.username, up.first_name, up.last_name FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id WHERE u.user_id = ?", [$_SESSION['user_id']]);
        $student_name = $student['first_name'] ?: $student['username'];
        $mailer = new MailHelper();
        $mailer->sendTemplate('payment_received', $student['email'], $student_name, [
            'student_name' => $student_name,
            'program_name' => $application['program_name'],
            'university_name' => $application['university_name'],
            'application_id' => $application_id,
            'amount' => $application['amount'] . ' ' . $application['currency'],
            'transaction_id' => $charge->id
        ]);

        Helpers::redirect('payment_success.php?application_id=' . $application_id, 'Payment successful!');
    } catch (\Stripe\Exception\CardException $e) {
        $error = 'Card declined: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = 'Payment error: ' . $e->getMessage();
    }
}

$page_title = "Pay Application Fee";
require_once '../../app/templates/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-credit-card"></i> Pay Application Fee</h1>
    <p>Complete payment for your application</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>Payment Details</h3>
        <table class="table">
            <tr><th>Program:</th><td><?= htmlspecialchars($application['program_name']) ?></td></tr>
            <tr><th>University:</th><td><?= htmlspecialchars($application['university_name']) ?></td></tr>
            <tr><th>Amount:</th><td><strong><?= number_format($application['amount'], 2) ?> <?= $application['currency'] ?></strong></td></tr>
        </table>
    </div>

    <div class="dashboard-card">
        <h3>Pay with Stripe</h3>
        <form action="" method="POST" id="payment-form">
            <div class="form-group">
                <label for="card-element">Credit or debit card</label>
                <div id="card-element" class="form-control" style="padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
                    <!-- Stripe Element will be inserted here -->
                </div>
                <div id="card-errors" role="alert" style="color: #dc3545; margin-top: 5px;"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="submit-button">
                <i class="fas fa-lock"></i> Pay <?= number_format($application['amount'], 2) ?> <?= $application['currency'] ?>
            </button>
        </form>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
    var stripe = Stripe('<?= $stripe_publishable_key ?>');
    var elements = stripe.elements();

    var style = {
        base: {
            fontSize: '16px',
            color: '#32325d',
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            '::placeholder': {
                color: '#aab7c4'
            }
        }
    };

    var card = elements.create('card', {style: style});
    card.mount('#card-element');

    card.on('change', function(event) {
        var displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });

    var form = document.getElementById('payment-form');
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        document.getElementById('submit-button').disabled = true;

        stripe.createToken(card).then(function(result) {
            if (result.error) {
                var errorElement = document.getElementById('card-errors');
                errorElement.textContent = result.error.message;
                document.getElementById('submit-button').disabled = false;
            } else {
                var hiddenInput = document.createElement('input');
                hiddenInput.setAttribute('type', 'hidden');
                hiddenInput.setAttribute('name', 'stripeToken');
                hiddenInput.setAttribute('value', result.token.id);
                form.appendChild(hiddenInput);
                form.submit();
            }
        });
    });
</script>

<?php require_once '../../app/templates/footer.php'; ?>