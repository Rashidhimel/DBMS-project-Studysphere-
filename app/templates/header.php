<?php

if (!isset($page_title)) {
    $page_title = 'StudySphere';
}


$current_user = isset($_SESSION['user_id']) ? (new Auth())->getCurrentUser() : null;
$admin_exists = false;
try {
    $db = new Database();
    $admin_count = $db->fetchOne(
        "SELECT COUNT(*) as count FROM users u 
         JOIN roles r ON u.default_role_id = r.role_id 
         WHERE r.name IN ('admin', 'super_admin') AND u.deleted_at IS NULL"
    );
    $admin_exists = $admin_count['count'] > 0;
} catch(Exception $e) {
    
    $admin_exists = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - StudySphere</title>
    <link rel="stylesheet" href="/studysphere/public/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="/studysphere/public/assets/images/favicon.ico">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/studysphere/public/index.php">
                    <i class="fas fa-graduation-cap"></i> StudySphere
                </a>
            </div>
            
            
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-menu" id="navMenu">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] == 'student'): ?>
                        
                        <a href="/studysphere/public/student/dashboard.php" class="nav-link">
                            <i class="fas fa-home"></i> <span class="nav-text">Dashboard</span>
                        </a>
                        <a href="/studysphere/public/student/applications.php" class="nav-link">
                            <i class="fas fa-file-alt"></i> <span class="nav-text">Applications</span>
                        </a>
                        <a href="/studysphere/public/student/program_recommendations.php" class="nav-link">
                            <i class="fas fa-star"></i> <span class="nav-text">Recommendations</span>
                        </a>
                        <a href="/studysphere/public/student/recommenders.php" class="nav-link">
                        <i class="fas fa-users"></i> <span class="nav-text">Recommenders</span>
                        </a>
                        <a href="/studysphere/public/student/jobs.php" class="nav-link">
                        <i class="fas fa-briefcase"></i> <span class="nav-text">Part-Time Jobs</span>
                        </a>
                        <div class="nav-dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fas fa-user"></i> <span class="nav-text">Profile</span> <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a href="/studysphere/public/student/profile.php">My Profile</a>
                            <a href="/studysphere/public/student/education.php">Education</a>
                            <a href="/studysphere/public/student/tests.php">Test Scores</a>
                            <a href="/studysphere/public/student/achievements.php">Achievements</a>
                            <a href="/studysphere/public/student/uploads.php">Documents</a>
                            <a href="/studysphere/public/student/settings.php">Settings</a>
                            <div class="dropdown-divider"></div>
                            <a href="/studysphere/public/student/budget_planning.php">Budget Planning</a>
                            <a href="/studysphere/public/student/finances.php">Finances</a>
                            <div class="dropdown-divider"></div>
                            <a href="/studysphere/public/student/visa.php">Visa Tracker</a>
                            <a href="/studysphere/public/student/visa_documents.php">Visa Documents</a>
                            <a href="/studysphere/public/student/scholarships.php">Scholarships</a>
                            <a href="/studysphere/public/student/jobs.php">Part-Time Jobs</a>
                            <a href="/studysphere/public/student/messages.php">Messages</a>
                            <a href="/studysphere/public/student/sessions.php">Counselling Sessions</a>
                            <a href="/studysphere/public/student/exam_resources.php">Exam Preparation</a>
                            <a href="/studysphere/public/student/essays.php">Essay Review</a>
                            <a href="/studysphere/public/student/milestones.php">Milestones</a>
                            <a href="/studysphere/public/student/todos.php">To-Do List</a>
                        </div>
                    </div>
                    <?php elseif ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'super_admin'): ?>
    
    <a href="/studysphere/public/admin/dashboard.php" class="nav-link">
        <i class="fas fa-tachometer-alt"></i> <span class="nav-text">Dashboard</span>
    </a>
    <a href="/studysphere/public/admin/users.php" class="nav-link">
        <i class="fas fa-users"></i> <span class="nav-text">Users</span>
    </a>
    <a href="/studysphere/public/admin/universities.php" class="nav-link">
        <i class="fas fa-university"></i> <span class="nav-text">Universities</span>
    </a>
    <a href="/studysphere/public/admin/programs.php" class="nav-link">
        <i class="fas fa-book"></i> <span class="nav-text">Programs</span>
    </a>
    <a href="/studysphere/public/admin/applications.php" class="nav-link">
        <i class="fas fa-file-alt"></i> <span class="nav-text">Applications</span>
    </a>
    <a href="/studysphere/public/admin/scholarships.php" class="nav-link">
        <i class="fas fa-award"></i> <span class="nav-text">Scholarships</span>
    </a>
    <div class="nav-dropdown">
        <a href="#" class="nav-link dropdown-toggle">
            <i class="fas fa-cog"></i> <span class="nav-text">Tools</span> <i class="fas fa-chevron-down"></i>
        </a>
        <div class="dropdown-menu">
            <a href="/studysphere/public/admin/attachments_moderation.php">
                <i class="fas fa-file-check"></i> Moderate Files
            </a>
            <a href="/studysphere/public/admin/visa_management.php">
                <i class="fas fa-passport"></i> Manage Visas
            </a>
            <a href="/studysphere/public/admin/report.php">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <?php if ($_SESSION['role'] == 'super_admin'): ?>
                <div class="dropdown-divider"></div>
                <a href="/studysphere/public/register_admin.php">
                    <i class="fas fa-user-shield"></i> Register Admin
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($_SESSION['role'] == 'counselor'): ?>
                        
                            <a href="/studysphere/public/counselor/dashboard.php" class="nav-link">
        <i class="fas fa-tachometer-alt"></i> <span class="nav-text">Dashboard</span>
    </a>
    <a href="/studysphere/public/counselor/students.php" class="nav-link">
        <i class="fas fa-users"></i> <span class="nav-text">Students</span>
    </a>
    <a href="/studysphere/public/counselor/sessions.php" class="nav-link">
        <i class="fas fa-calendar-alt"></i> <span class="nav-text">Sessions</span>
    </a>
    <a href="/studysphere/public/counselor/messages.php" class="nav-link">
        <i class="fas fa-envelope"></i> <span class="nav-text">Messages</span>
    </a>
    <a href="/studysphere/public/counselor/essays.php" class="nav-link">
        <i class="fas fa-pen"></i> <span class="nav-text">Essays</span>
    </a>
    <a href="/studysphere/public/counselor/todos.php" class="nav-link">
        <i class="fas fa-tasks"></i> <span class="nav-text">Tasks</span>
    </a>
                    <?php endif; ?>
                    <div class="nav-dropdown lang-switcher">
    <a href="#" class="nav-link dropdown-toggle">
        <i class="fas fa-globe"></i> <?= strtoupper(Lang::getInstance()->getCurrentLang()) ?>
    </a>
    <div class="dropdown-menu">
        <a href="#" onclick="setLanguage('en')">English</a>
        <a href="#" onclick="setLanguage('es')">Español</a>
    </div>
</div>
                    <div class="nav-dropdown user-dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fas fa-user-circle"></i> 
                            <span class="nav-text">
                                <?php 
                                if ($current_user && !empty($current_user['first_name'])) {
                                    echo htmlspecialchars($current_user['first_name']);
                                } else {
                                    echo htmlspecialchars($_SESSION['username']);
                                }
                                ?>
                            </span> 
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <strong><?php echo htmlspecialchars($_SESSION['email']); ?></strong><br>
                                <small>Role: <?php echo ucfirst($_SESSION['role']); ?></small>
                            </div>
                            <a href="/studysphere/public/student/profile.php">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <a href="/studysphere/public/student/settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="/studysphere/public/logout.php" class="logout-link">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
<?php else: ?>
    
    <a href="/studysphere/public/index.php" class="nav-link">
        <i class="fas fa-home"></i> <span class="nav-text">Home</span>
    </a>
    <a href="/studysphere/public/login.php" class="nav-link">
        <i class="fas fa-sign-in-alt"></i> <span class="nav-text">Login</span>
    </a>
    <a href="/studysphere/public/register.php" class="nav-link">
        <i class="fas fa-user-plus"></i> <span class="nav-text">Register</span>
    </a>
    
    
    <a href="/studysphere/public/register_admin.php" class="nav-link admin-register">
        <i class="fas fa-user-shield"></i> <span class="nav-text">Admin Setup</span>
    </a>
    
    <!-- Language Switcher for public visitors -->
    <div class="nav-dropdown lang-switcher">
        <a href="#" class="nav-link dropdown-toggle">
            <i class="fas fa-globe"></i> <?= strtoupper(Lang::getInstance()->getCurrentLang()) ?>
        </a>
        <div class="dropdown-menu">
            <a href="#" onclick="setLanguage('en')">English</a>
            <a href="#" onclick="setLanguage('es')">Español</a>
        </div>
    </div>
<?php endif; ?>
            </div>
        </div>
    </nav>
        <script>
    function setLanguage(lang) {
        fetch('/studysphere/public/set_language.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'lang=' + lang
        }).then(() => location.reload());
    }
    </script>
    <div class="container">
        <?php
        
        $flash = Helpers::getFlash();
        if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>