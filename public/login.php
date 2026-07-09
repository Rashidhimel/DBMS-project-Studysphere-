<?php
require_once '../app/includes/init.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'super_admin' || $_SESSION['role'] == 'counselor') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit();
}

$page_title = "Login";
require_once '../app/templates/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = Helpers::sanitize($_POST['email']);
    $password = $_POST['password'];
    
    $auth = new Auth();
    if ($auth->login($email, $password)) {
        if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'super_admin' || $_SESSION['role'] == 'counselor') {
            Helpers::redirect('/studysphere/public/admin/dashboard.php', 'Login successful!');
        } else {
            Helpers::redirect('/studysphere/public/student/dashboard.php', 'Login successful!');
        }
    } else {
        $error = 'Invalid email or password';
    }
}
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <i class="fas fa-graduation-cap fa-3x"></i>
            <h2>Welcome Back</h2>
            <p class="text-muted">Sign in to continue to StudySphere</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="Enter your email">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </div>
            
            <div class="auth-links">
                <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                <a href="#"><i class="fas fa-key"></i> Forgot Password?</a>
            </div>
            
            <hr>
            
            <!-- Login Tips Section (replaces demo accounts) -->
            <div class="login-tips">
                <h4><i class="fas fa-lightbulb"></i> Login Tips</h4>
                <ul>
                    <li><i class="fas fa-check-circle text-success"></i> Use the email and password you registered with</li>
                    <li><i class="fas fa-check-circle text-success"></i> Students can manage applications, upload documents, and track visas</li>
                    <li><i class="fas fa-check-circle text-success"></i> Counselors oversee assigned students and schedule sessions</li>
                    <li><i class="fas fa-check-circle text-success"></i> Administrators have full system access</li>
                </ul>
                <p class="mt-2 text-muted">
                    <i class="fas fa-info-circle"></i> 
                    Forgot your password? Contact your administrator or use the "Forgot Password" link above.
                </p>
            </div>
            
            <div class="admin-signup">
                <small>Administrator? <a href="register_admin.php"><i class="fas fa-user-shield"></i> Register as Admin</a></small>
            </div>
        </form>
    </div>
</div>

<style>
.auth-container {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
}

.auth-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08), 0 6px 12px rgba(0,0,0,0.05);
    padding: 2.5rem;
    width: 100%;
    max-width: 450px;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-header i {
    color: #3498db;
    margin-bottom: 1rem;
}

.auth-header h2 {
    margin: 0 0 0.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.auth-header p {
    margin: 0;
    color: #7f8c8d;
}

/* Form */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.9rem;
}

.input-icon {
    position: relative;
}

.input-icon i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #95a5a6;
    font-size: 1rem;
    pointer-events: none;
}

.input-icon input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.input-icon input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}

/* Buttons */
.btn-block {
    width: 100%;
    padding: 0.75rem;
    font-size: 1rem;
    border-radius: 30px;
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-block:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(52,152,219,0.3);
}

/* Auth links */
.auth-links {
    display: flex;
    justify-content: space-between;
    margin: 1rem 0;
}

.auth-links a {
    color: #3498db;
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    transition: color 0.2s;
}

.auth-links a:hover {
    color: #2980b9;
    text-decoration: underline;
}

hr {
    border: none;
    border-top: 1px solid #eef2f6;
    margin: 1.5rem 0;
}

/* Login Tips */
.login-tips {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1.2rem;
    margin-bottom: 1rem;
}

.login-tips h4 {
    margin: 0 0 1rem;
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.login-tips ul {
    list-style: none;
    padding: 0;
    margin: 0 0 1rem;
}

.login-tips li {
    margin-bottom: 0.6rem;
    font-size: 0.9rem;
    color: #4a5568;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.login-tips i.text-success {
    color: #27ae60;
    width: 18px;
}

.login-tips p {
    margin: 0;
    font-size: 0.8rem;
    color: #7f8c8d;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.mt-2 {
    margin-top: 0.75rem;
}

/* Admin signup */
.admin-signup {
    text-align: center;
    margin-top: 1rem;
}

.admin-signup a {
    color: #e74c3c;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.admin-signup a:hover {
    text-decoration: underline;
}

/* Alert */
.alert-error {
    background: #fee;
    color: #c0392b;
    border: 1px solid #fcc;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Responsive */
@media (max-width: 480px) {
    .auth-card {
        padding: 1.5rem;
    }
    .auth-links {
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
}
</style>

<?php require_once '../app/templates/footer.php'; ?>