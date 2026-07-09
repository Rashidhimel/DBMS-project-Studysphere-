<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Profile";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

$profile = $db->fetchOne("SELECT * FROM user_profiles WHERE user_id = ?", [$user['user_id']]);
$student = $db->fetchOne("SELECT * FROM students WHERE user_id = ?", [$user['user_id']]);
$addresses = $db->fetchAll("SELECT * FROM addresses WHERE user_id = ?", [$user['user_id']]);

$success = '';
$error = '';

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['error'] == UPLOAD_ERR_OK) {
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Only JPG, PNG, and GIF images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size must be less than 2MB.';
        } else {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user['user_id'] . '_' . time() . '.' . $ext;
            $upload_dir = STORAGE_PATH . '/uploads/profile_photos/';
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Delete old photo if exists
                if ($profile && !empty($profile['profile_photo_url'])) {
                    $old_file = STORAGE_PATH . '/uploads/profile_photos/' . basename($profile['profile_photo_url']);
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Update database
                $photo_url = '/storage/uploads/profile_photos/' . $filename;
                if ($profile) {
                    $db->update('user_profiles', 
                        ['profile_photo_url' => $photo_url, 'updated_at' => date('Y-m-d H:i:s')],
                        "user_id = " . $user['user_id']
                    );
                } else {
                    $db->insert('user_profiles', [
                        'user_id' => $user['user_id'],
                        'profile_photo_url' => $photo_url,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
                $success = 'Profile photo updated successfully!';
                // Refresh profile data
                $profile = $db->fetchOne("SELECT * FROM user_profiles WHERE user_id = ?", [$user['user_id']]);
            } else {
                $error = 'Failed to upload file.';
            }
        }
    } elseif ($file['error'] != UPLOAD_ERR_NO_FILE) {
        $error = 'Upload error: ' . $file['error'];
    }
}

// Handle personal info update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    $first_name = Helpers::sanitize($_POST['first_name']);
    $last_name = Helpers::sanitize($_POST['last_name']);
    $gender = Helpers::sanitize($_POST['gender']);
    $dob = Helpers::sanitize($_POST['dob']);
    $nationality = Helpers::sanitize($_POST['nationality']);
    $bio = Helpers::sanitize($_POST['bio']);
    
    if ($profile) {
        $db->update('user_profiles', [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'dob' => $dob,
            'nationality' => $nationality,
            'bio' => $bio,
            'updated_at' => date('Y-m-d H:i:s')
        ], "user_id = " . $user['user_id']);
    } else {
        $db->insert('user_profiles', [
            'user_id' => $user['user_id'],
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'dob' => $dob,
            'nationality' => $nationality,
            'bio' => $bio,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Refresh profile
    $profile = $db->fetchOne("SELECT * FROM user_profiles WHERE user_id = ?", [$user['user_id']]);
    
    // Check for milestone
    $profile_check = $db->fetchOne("SELECT first_name, last_name, dob, nationality FROM user_profiles WHERE user_id = ?", [$user['user_id']]);
    if (!empty($profile_check['first_name']) && !empty($profile_check['last_name']) && !empty($profile_check['dob']) && !empty($profile_check['nationality'])) {
        Helpers::awardMilestone($student['student_id'], 'profile_completed');
    }
    
    $success = 'Profile updated successfully!';
}

// Determine display name
$display_name = ($profile && !empty($profile['first_name'])) ? $profile['first_name'] . ' ' . $profile['last_name'] : $user['username'];
?>

<div class="page-header">
    <h1><i class="fas fa-user-circle"></i> My Profile</h1>
    <p>Manage your personal information and profile picture</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="profile-modern">
    <!-- Sidebar / Avatar Card -->
    <div class="profile-sidebar-modern">
        <div class="avatar-card">
            <div class="avatar-container">
                <?php if ($profile && !empty($profile['profile_photo_url'])): ?>
                    <img src="<?php echo $profile['profile_photo_url']; ?>" alt="Profile Photo" class="avatar-image">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="fas fa-user-circle"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($display_name); ?></h3>
            <p class="user-email"><?php echo $user['email']; ?></p>
            <p class="member-since">Member since: <?php echo Helpers::formatDate($user['created_at'], 'M Y'); ?></p>
            
            <div class="photo-upload">
                <form method="POST" enctype="multipart/form-data">
                    <label for="profile_photo" class="btn btn-secondary btn-block">Choose Photo</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                </form>
                <small>Max 2MB (JPG, PNG, GIF)</small>
            </div>
        </div>
        
        <div class="quick-links">
            <h4>Quick Links</h4>
            <a href="education.php"><i class="fas fa-graduation-cap"></i> Education</a>
            <a href="tests.php"><i class="fas fa-file-alt"></i> Test Scores</a>
            <a href="achievements.php"><i class="fas fa-trophy"></i> Achievements</a>
            <a href="uploads.php"><i class="fas fa-file-upload"></i> Documents</a>
            <a href="addresses.php"><i class="fas fa-map-marker-alt"></i> Addresses</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="profile-main">
        <!-- Personal Information Card -->
        <div class="card modern-card">
            <h3><i class="fas fa-user-edit"></i> Personal Information</h3>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?php echo $profile ? $profile['first_name'] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?php echo $profile ? $profile['last_name'] : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select</option>
                            <option value="male" <?php echo ($profile && $profile['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($profile && $profile['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($profile && $profile['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob" 
                               value="<?php echo $profile ? $profile['dob'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="nationality">Nationality</label>
                    <select id="nationality" name="nationality">
                        <option value="">Select Country</option>
                        <?php foreach (Helpers::getCountries() as $country): ?>
                            <option value="<?php echo $country['iso2']; ?>"
                                <?php echo ($profile && $profile['nationality'] == $country['iso2']) ? 'selected' : ''; ?>>
                                <?php echo $country['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bio">Bio / Introduction</label>
                    <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself..."><?php echo $profile ? $profile['bio'] : ''; ?></textarea>
                </div>
                
                <button type="submit" name="update_info" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
        
        <!-- Student Information Card -->
        <?php if ($student): ?>
        <div class="card modern-card">
            <h3><i class="fas fa-graduation-cap"></i> Student Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Degree Level</span>
                    <span class="info-value">
                        <?php 
                        $degree = $db->fetchOne("SELECT name FROM degree_levels WHERE degree_level_id = ?", [$student['degree_level_id']]);
                        echo $degree ? $degree['name'] : 'Not set';
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Country of Origin</span>
                    <span class="info-value">
                        <?php 
                        if ($student['country_from']) {
                            $country = $db->fetchOne("SELECT name FROM countries WHERE iso2 = ?", [$student['country_from']]);
                            echo $country ? $country['name'] : $student['country_from'];
                        } else {
                            echo 'Not set';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Budget Range</span>
                    <span class="info-value">
                        <?php 
                        if ($student['budget_min'] || $student['budget_max']) {
                            echo '$' . number_format($student['budget_min'] ?: 0) . ' - $' . number_format($student['budget_max'] ?: 0);
                        } else {
                            echo 'Not set';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Addresses Card -->
        <div class="card modern-card">
            <div class="card-header-flex">
                <h3><i class="fas fa-map-marker-alt"></i> Addresses</h3>
                <a href="addresses.php" class="btn btn-small btn-primary">Manage Addresses</a>
            </div>
            <?php if (empty($addresses)): ?>
                <p class="text-muted">No addresses added yet.</p>
            <?php else: ?>
                <div class="address-list">
                    <?php foreach ($addresses as $addr): ?>
                        <div class="address-item">
                            <span class="address-type"><?php echo ucfirst($addr['address_type']); ?></span>
                            <div class="address-details">
                                <div><?php echo htmlspecialchars($addr['address_line1']); ?></div>
                                <?php if ($addr['address_line2']): ?>
                                    <div><?php echo htmlspecialchars($addr['address_line2']); ?></div>
                                <?php endif; ?>
                                <div><?php echo htmlspecialchars($addr['city']); ?>, <?php echo $addr['region'] ? $addr['region'] . ', ' : ''; ?><?php echo $addr['postal_code']; ?></div>
                                <div class="address-country">
                                    <?php 
                                    if ($addr['country_code']) {
                                        $c = $db->fetchOne("SELECT name FROM countries WHERE iso2 = ?", [$addr['country_code']]);
                                        echo $c ? $c['name'] : $addr['country_code'];
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Modern profile layout */
.profile-modern {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    margin-top: 1.5rem;
}

/* Sidebar */
.profile-sidebar-modern {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.avatar-card {
    background: white;
    border-radius: 20px;
    padding: 2rem 1.5rem;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    text-align: center;
}

.avatar-container {
    width: 120px;
    height: 120px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    color: white;
}

.avatar-card h3 {
    margin: 0 0 0.3rem;
    color: #2c3e50;
    font-size: 1.4rem;
}

.user-email {
    color: #666;
    margin-bottom: 0.3rem;
}

.member-since {
    color: #999;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}

.photo-upload {
    margin-top: 1rem;
}
.photo-upload .btn {
    border-radius: 40px;
    padding: 0.6rem;
    font-size: 0.9rem;
}

.quick-links {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.quick-links h4 {
    margin: 0 0 1rem;
    color: #2c3e50;
    font-size: 1.1rem;
}

.quick-links a {
    display: block;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    background: #f8f9fa;
    border-radius: 12px;
    color: #2c3e50;
    text-decoration: none;
    transition: all 0.2s;
}
.quick-links a:hover {
    background: #e9ecef;
    transform: translateX(5px);
}
.quick-links a i {
    width: 24px;
    color: #3498db;
}

/* Main content */
.profile-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.modern-card {
    border: none;
    border-radius: 20px;
    background: white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    padding: 1.8rem;
    transition: box-shadow 0.2s;
}
.modern-card:hover {
    box-shadow: 0 12px 35px rgba(0,0,0,0.1);
}

.modern-card h3 {
    margin: 0 0 1.5rem;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #2c3e50;
}
.modern-card h3 i {
    color: #3498db;
}

.card-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.card-header-flex h3 {
    margin: 0;
}

/* Form styling */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.9rem;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}

/* Info grid for student info */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}
.info-item {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1rem;
}
.info-label {
    display: block;
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-bottom: 0.3rem;
}
.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
}

/* Address list */
.address-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.address-item {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.2rem;
    border-left: 4px solid #3498db;
}
.address-type {
    display: inline-block;
    background: #3498db;
    color: white;
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.address-details {
    color: #2c3e50;
    line-height: 1.6;
}
.address-country {
    margin-top: 0.5rem;
    font-weight: 500;
}

/* Buttons */
.btn {
    padding: 0.6rem 1.2rem;
    border-radius: 40px;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.95rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(52,152,219,0.3);
}
.btn-secondary {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    color: #2c3e50;
}
.btn-secondary:hover {
    background: #e9ecef;
}
.btn-small {
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
}
.btn-block {
    width: 100%;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}
.alert-success {
    background: #e8f5e9;
    color: #2e7d32;
    border-left: 4px solid #2e7d32;
}
.alert-error {
    background: #ffebee;
    color: #c62828;
    border-left: 4px solid #c62828;
}

/* Responsive */
@media (max-width: 992px) {
    .profile-modern {
        grid-template-columns: 1fr;
    }
    .profile-sidebar-modern {
        max-width: 400px;
        margin: 0 auto;
    }
}
</style>

<script>
// Optional: auto-submit on file select (already done with onchange)
</script>

<?php require_once '../../app/templates/footer.php'; ?>