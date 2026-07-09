<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Edit User";
require_once '../../app/templates/header.php';

$auth = new Auth();
$current_user = $auth->getCurrentUser();
$db = new Database();

$error = '';
$success = '';

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    Helpers::redirect('users.php', 'User ID not specified.', 'error');
}

$user = $db->fetchOne("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN roles r ON u.default_role_id = r.role_id 
    WHERE u.user_id = ?
", [$user_id]);

if (!$user) {
    Helpers::redirect('users.php', 'User not found.', 'error');
}

$roles = $db->fetchAll("SELECT * FROM roles ORDER BY name");
$profile = $db->fetchOne("SELECT * FROM user_profiles WHERE user_id = ?", [$user_id]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = Helpers::sanitize($_POST['username']);
    $email = Helpers::sanitize($_POST['email']);
    $role_id = intval($_POST['role_id']);
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;
    $is_disabled = isset($_POST['is_disabled']) ? 1 : 0;
    $first_name = Helpers::sanitize($_POST['first_name']);
    $last_name = Helpers::sanitize($_POST['last_name']);
    $gender = Helpers::sanitize($_POST['gender']);
    $dob = Helpers::sanitize($_POST['dob']);
    // If nationality is empty, set to NULL to avoid FK constraint error
    $nationality = !empty($_POST['nationality']) ? Helpers::sanitize($_POST['nationality']) : null;
    
    if ($user_id == $current_user['user_id']) {
        if ($is_disabled) {
            $error = 'You cannot disable your own account!';
        }
        if ($role_id != $user['default_role_id']) {
            $error = 'You cannot change your own role!';
        }
    }
    
    if (!$error) {
        $existing = $db->fetchOne(
            "SELECT user_id FROM users WHERE (email = ? OR username = ?) AND user_id != ?",
            [$email, $username, $user_id]
        );
        
        if ($existing) {
            $error = 'Email or username already exists';
        } else {
            // Update users table
            $db->update('users', [
                'username' => $username,
                'email' => $email,
                'default_role_id' => $role_id,
                'is_verified' => $is_verified,
                'is_disabled' => $is_disabled,
                'updated_at' => date('Y-m-d H:i:s')
            ], "user_id = $user_id");
            
            // Update user_roles
            $db->query("DELETE FROM user_roles WHERE user_id = ?", [$user_id]);
            $db->insert('user_roles', [
                'user_id' => $user_id,
                'role_id' => $role_id
            ]);
            
            // Update or insert user_profiles
            if ($profile) {
                $db->update('user_profiles', [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'gender' => $gender,
                    'dob' => $dob,
                    'nationality' => $nationality,
                    'updated_at' => date('Y-m-d H:i:s')
                ], "user_id = $user_id");
            } else {
                $db->insert('user_profiles', [
                    'user_id' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'gender' => $gender,
                    'dob' => $dob,
                    'nationality' => $nationality,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $success = 'User updated successfully!';
            // Refresh data
            $user = $db->fetchOne("
                SELECT u.*, r.name as role_name 
                FROM users u 
                JOIN roles r ON u.default_role_id = r.role_id 
                WHERE u.user_id = ?
            ", [$user_id]);
            $profile = $db->fetchOne("SELECT * FROM user_profiles WHERE user_id = ?", [$user_id]);
        }
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> Edit User</h1>
    <p class="text-muted"><?= htmlspecialchars($user['username']) ?></p>
    <a href="users.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Back to Users
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error alert-dismissible fade show" role="alert">
        <?= $error ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Left column: User avatar and stats -->
    <div class="col-md-4">
        <div class="card user-card">
            <div class="card-body text-center">
                <div class="avatar-wrapper">
                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                </div>
                <h3 class="user-name"><?= htmlspecialchars($profile['first_name'] ?? '') ?> <?= htmlspecialchars($profile['last_name'] ?? '') ?></h3>
                <p class="user-username">@<?= htmlspecialchars($user['username']) ?></p>
                <p class="user-email"><?= htmlspecialchars($user['email']) ?></p>
                <div class="user-role">
                    <span class="role-badge"><?= ucfirst($user['role_name']) ?></span>
                </div>
                <hr>
                <table class="info-table">
                    <tr><td>Account created:</td><td><?= Helpers::formatDate($user['created_at']) ?></td></tr>
                    <tr><td>Last login:</td><td><?= $user['last_login_at'] ? Helpers::formatDate($user['last_login_at']) : 'Never' ?></td></tr>
                    <tr><td>Status:</td><td>
                        <?php if ($user['is_disabled']): ?>
                            <span class="badge badge-danger">Disabled</span>
                        <?php elseif ($user['is_verified']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Unverified</span>
                        <?php endif; ?>
                    </td></tr>
                </table>
            </div>
        </div>

        <?php if ($user_id != $current_user['user_id']): ?>
            <div class="card danger-card">
                <div class="card-header">
                    <h4><i class="fas fa-exclamation-triangle text-danger"></i> Danger Zone</h4>
                </div>
                <div class="card-body">
                    <p>Permanently delete this user account and all associated data (applications, documents, etc.). This action cannot be undone.</p>
                    <a href="users.php?delete=<?= $user_id ?>" 
                       class="btn btn-danger btn-block"
                       onclick="return confirm('⚠️ WARNING: This will PERMANENTLY DELETE this user account and ALL associated data. This action CANNOT BE UNDONE. Are you ABSOLUTELY SURE?')">
                        <i class="fas fa-trash"></i> Permanently Delete Account
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right column: Edit form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-cog"></i> Account Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" 
                                   value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_id">Role *</label>
                        <select id="role_id" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['role_id'] ?>" 
                                    <?= ($user['default_role_id'] == $role['role_id']) ? 'selected' : '' ?>
                                    <?= ($user_id == $current_user['user_id'] && $user['default_role_id'] != $role['role_id']) ? 'disabled' : '' ?>>
                                    <?= ucfirst($role['name']) ?> - <?= $role['description'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($user_id == $current_user['user_id']): ?>
                            <small class="text-muted">You cannot change your own role.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-check">
                            <input type="checkbox" id="is_verified" name="is_verified" value="1" <?= $user['is_verified'] ? 'checked' : '' ?>>
                            <label for="is_verified">Email Verified</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="is_disabled" name="is_disabled" value="1" <?= $user['is_disabled'] ? 'checked' : '' ?>
                                   <?= ($user_id == $current_user['user_id']) ? 'disabled' : '' ?>>
                            <label for="is_disabled">Account Disabled</label>
                            <?php if ($user_id == $current_user['user_id']): ?>
                                <small class="text-muted">(You cannot disable your own account)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h4>Profile Information</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?= $profile ? htmlspecialchars($profile['first_name']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?= $profile ? htmlspecialchars($profile['last_name']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select</option>
                                <option value="male" <?= ($profile && $profile['gender'] == 'male') ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($profile && $profile['gender'] == 'female') ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= ($profile && $profile['gender'] == 'other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" 
                                   value="<?= $profile ? $profile['dob'] : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nationality">Nationality</label>
                        <select id="nationality" name="nationality">
                            <option value="">Select Country</option>
                            <?php 
                            $countries = Helpers::getCountries();
                            foreach ($countries as $country): ?>
                                <option value="<?= $country['iso2'] ?>" 
                                    <?= ($profile && $profile['nationality'] == $country['iso2']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Leave empty if not applicable</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern page styling */
.page-header {
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}
.page-header h1 {
    margin: 0 0.5rem 0 0;
}
.page-header p {
    margin: 0 1rem 0 0;
}
.btn-outline-secondary {
    border-color: #ced4da;
    color: #6c757d;
}
.btn-outline-secondary:hover {
    background: #e2e6ea;
}

/* Cards */
.card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    margin-bottom: 1.5rem;
    border: none;
    overflow: hidden;
}
.card-header {
    background: white;
    border-bottom: 1px solid #eef2f6;
    padding: 1.2rem 1.5rem;
}
.card-header h3, .card-header h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-body {
    padding: 1.5rem;
}

/* User card */
.user-card {
    text-align: center;
}
.avatar-wrapper {
    margin-bottom: 1rem;
}
.avatar-wrapper i {
    color: #3498db;
}
.user-name {
    margin: 0 0 0.2rem 0;
    font-size: 1.3rem;
    font-weight: 600;
}
.user-username {
    color: #7f8c8d;
    margin-bottom: 0.2rem;
}
.user-email {
    color: #7f8c8d;
    margin-bottom: 1rem;
}
.role-badge {
    display: inline-block;
    padding: 0.25rem 1rem;
    background: #eef2f6;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #2c3e50;
}
hr {
    margin: 1.5rem 0;
    border: none;
    border-top: 1px solid #eef2f6;
}

/* Info table */
.info-table {
    width: 100%;
    border-collapse: collapse;
}
.info-table td {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f1f1;
}
.info-table td:first-child {
    font-weight: 600;
    color: #4a5568;
}
.info-table tr:last-child td {
    border-bottom: none;
}

/* Danger card */
.danger-card {
    border: 1px solid #f5c6cb;
    background: #fef9fa;
}
.danger-card .card-header {
    background: #fef9fa;
    border-bottom-color: #f5c6cb;
}
.danger-card .card-header h4 {
    color: #e74c3c;
}
.btn-danger {
    background: #e74c3c;
    border: none;
    color: white;
    border-radius: 30px;
    padding: 0.6rem 1.2rem;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-danger:hover {
    background: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(231,76,60,0.2);
}

/* Form */
.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.form-group {
    flex: 1;
    min-width: 200px;
}
.form-group label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 500;
    color: #4a5568;
}
.form-group input,
.form-group select {
    width: 100%;
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: border 0.2s;
}
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}
.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.form-check input {
    width: auto;
    margin: 0;
}
hr {
    margin: 2rem 0 1rem;
}
h4 {
    font-size: 1.1rem;
    margin: 1.5rem 0 1rem;
    color: #2c3e50;
}
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    border-radius: 30px;
    padding: 0.6rem 2rem;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52,152,219,0.3);
}
.btn-outline-secondary {
    border-radius: 30px;
    padding: 0.6rem 2rem;
}

/* Badges */
.badge {
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    font-weight: 500;
    font-size: 0.8rem;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>