<?php
require_once '../app/includes/init.php';

$page_title = "Admin Registration";
require_once '../app/templates/header.php';

$db = new Database();
$auth = new Auth();

$error = '';
$success = '';

// Get all roles including admin roles
$roles = $db->fetchAll(
    "SELECT * FROM roles WHERE name IN ('admin', 'super_admin', 'counselor', 'student') ORDER BY name"
);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = Helpers::sanitize($_POST['username']);
    $email = Helpers::sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = Helpers::sanitize($_POST['role']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        // Check if user already exists
        $existing = $db->fetchOne(
            "SELECT user_id FROM users WHERE email = ? OR username = ?",
            [$email, $username]
        );
        
        if ($existing) {
            $error = 'Email or username already exists';
        } else {
            // Register the user
            $user_id = $auth->register($username, $email, $password, $role);
            
            if ($user_id) {
                // Auto-verify admin accounts
                if (in_array($role, ['admin', 'super_admin', 'counselor'])) {
                    $db->update('users', 
                        ['is_verified' => 1, 'is_disabled' => 0],
                        "user_id = $user_id"
                    );
                    
                    // If it's the first admin account ever, make it super_admin
                    $admin_count = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM users u 
                         JOIN roles r ON u.default_role_id = r.role_id 
                         WHERE r.name IN ('admin', 'super_admin') AND u.deleted_at IS NULL"
                    );
                    
                    if ($admin_count['count'] == 1) {
                        $super_admin_role = $db->fetchOne(
                            "SELECT role_id FROM roles WHERE name = 'super_admin'"
                        );
                        if ($super_admin_role) {
                            $db->update('users', 
                                ['default_role_id' => $super_admin_role['role_id']],
                                "user_id = $user_id"
                            );
                            // Update user_roles
                            $db->query("DELETE FROM user_roles WHERE user_id = ?", [$user_id]);
                            $db->insert('user_roles', [
                                'user_id' => $user_id,
                                'role_id' => $super_admin_role['role_id']
                            ]);
                        }
                    }
                }
                
                // Login automatically
                if ($auth->login($email, $password)) {
                    if (in_array($role, ['admin', 'super_admin', 'counselor'])) {
                        Helpers::redirect('/studysphere/public/admin/dashboard.php', 'Admin account created and logged in!');
                    } else {
                        Helpers::redirect('/studysphere/public/student/dashboard.php', 'Account created and logged in!');
                    }
                } else {
                    $success = 'Account created! Please login.';
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
            <h2><i class="fas fa-user-shield"></i> Admin Registration</h2>
            <p class="text-muted">Create an administrator, counselor, or staff account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <a href="login.php" class="btn btn-primary btn-block">Login Now</a>
        <?php else: ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username *</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="Choose a username" required
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" 
                       placeholder="you@example.com" required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="role"><i class="fas fa-tag"></i> Account Type *</label>
                <select id="role" name="role" class="form-control" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role_option): ?>
                        <option value="<?= $role_option['name'] ?>" 
                            <?= (isset($_POST['role']) && $_POST['role'] == $role_option['name']) ? 'selected' : '' ?>>
                            <?= ucfirst($role_option['name']) ?> – <?= $role_option['description'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Choose "admin" or "super_admin" for full administrative access</small>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password *</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Minimum 8 characters" required>
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                       placeholder="Re‑enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-user-plus"></i> Create Account
            </button>

            <div class="auth-footer">
                <a href="register.php">Register as Student Instead</a>
                <span class="separator">•</span>
                <a href="login.php">Back to Login</a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

<style>
/* Modern auth page styling */
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
    max-width: 500px;
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
.alert-success {
    background: #e8f5e9;
    color: #27ae60;
    border-left: 4px solid #2ecc71;
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