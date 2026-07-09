<?php
require_once '../app/includes/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$page_title = "Register";
require_once '../app/templates/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = Helpers::sanitize($_POST['username']);
    $email = Helpers::sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $auth = new Auth();
        $db = new Database();

        $existing = $db->fetchOne(
            "SELECT user_id FROM users WHERE email = ? OR username = ?",
            [$email, $username]
        );

        if ($existing) {
            $error = 'Email or username already exists';
        } else {
            $user_id = $auth->register($username, $email, $password, 'student');

            if ($user_id) {
                if ($auth->login($email, $password)) {
                    Helpers::redirect('/studysphere/public/student/dashboard.php', 'Registration successful!');
                } else {
                    Helpers::redirect('/studysphere/public/login.php', 'Registration successful! Please login.');
                }
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h2><i class="fas fa-user-graduate"></i> Student Registration</h2>
            <p class="text-muted">Create your account to start your journey</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="Choose a username" required
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" class="form-control" 
                       placeholder="you@example.com" required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Minimum 6 characters" required>
                <small class="text-muted">At least 6 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                       placeholder="Re-enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-user-plus"></i> Create Account
            </button>

            <div class="auth-footer">
                <a href="login.php">Already have an account? Login</a>
                <span class="separator">•</span>
                <a href="register_admin.php">Admin Registration</a>
            </div>
        </form>
    </div>
</div>

<style>
/* Reuse same styles as admin registration for consistency */
.auth-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 200px);
    padding: 2rem;
    background: #f8fafc;
}
.auth-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
    max-width: 450px;
    width: 100%;
    padding: 2.5rem;
}
.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}
.auth-header h2 {
    font-size: 1.8rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.auth-header h2 i {
    color: #3498db;
}
.auth-header p {
    margin: 0;
    color: #7f8c8d;
}
.auth-form .form-group {
    margin-bottom: 1.5rem;
}
.auth-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #4a5568;
}
.auth-form label i {
    margin-right: 0.3rem;
    color: #3498db;
}
.auth-form .form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.2s;
}
.auth-form .form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}
.auth-form small {
    display: block;
    margin-top: 0.25rem;
    color: #95a5a6;
    font-size: 0.85rem;
}
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    border-radius: 30px;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(52,152,219,0.3);
}
.btn-block {
    width: 100%;
}
.auth-footer {
    margin-top: 2rem;
    text-align: center;
    font-size: 0.95rem;
}
.auth-footer a {
    color: #3498db;
    text-decoration: none;
    transition: color 0.2s;
}
.auth-footer a:hover {
    color: #1f5a8a;
    text-decoration: underline;
}
.separator {
    margin: 0 0.5rem;
    color: #cbd5e0;
}
.alert {
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border: none;
}
.alert-error {
    background: #fee;
    color: #c0392b;
    border-left: 4px solid #e74c3c;
}
.alert .close {
    float: right;
    font-size: 1.5rem;
    line-height: 1;
    color: inherit;
    opacity: 0.5;
    border: none;
    background: none;
    cursor: pointer;
}
.alert .close:hover {
    opacity: 1;
}

/* Responsive */
@media (max-width: 576px) {
    .auth-wrapper {
        padding: 1rem;
    }
    .auth-card {
        padding: 1.5rem;
    }
}
</style>

<?php require_once '../app/templates/footer.php'; ?>