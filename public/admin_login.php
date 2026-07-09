<?php
require_once '../app/includes/init.php';


if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$page_title = "Admin Login";
require_once '../app/templates/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = Helpers::sanitize($_POST['email']);
    $password = $_POST['password'];
    
    $auth = new Auth();
    
    
    $db = new Database();
    $user = $db->fetchOne(
        "SELECT u.*, r.name as role_name 
         FROM users u 
         JOIN roles r ON u.default_role_id = r.role_id 
         WHERE u.email = ? AND u.is_disabled = 0 
         AND r.name IN ('admin', 'super_admin', 'counselor')",
        [$email]
    );
    
    if ($user && password_verify($password, $user['password_hash'])) {
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role_name'];
        $_SESSION['is_verified'] = $user['is_verified'];
        
        
        $db->update('users', 
            ['last_login_at' => date('Y-m-d H:i:s')],
            "user_id = " . $user['user_id']
        );
        
        Helpers::redirect('/studysphere/public/admin/dashboard.php', 'Admin login successful!');
    } else {
        $error = 'Invalid email, password, or insufficient privileges.';
    }
}
?>

<div class="auth-container">
    <div class="auth-card">
        <h2><i class="fas fa-lock"></i> Admin Login</h2>
        <p class="text-center">Access restricted to administrators and staff only.</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       placeholder="admin@studysphere.local">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-sign-in-alt"></i> Login as Admin
            </button>
            
            <div class="auth-links">
                <a href="login.php">Student Login</a>
                <a href="index.php">Back to Home</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../app/templates/footer.php'; ?>