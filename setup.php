<?php
// setup.php - One-time setup script
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudySphere Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3498db; }
        .step h3 { margin-top: 0; }
    </style>
</head>
<body>
    <h1>StudySphere Setup Assistant</h1>';

// Generate password hashes
echo '<div class="step">
    <h3>Step 1: Generate Password Hashes</h3>
    <p>Run this PHP code to generate password hashes:</p>
    <pre>&lt;?php
echo "Admin Hash: " . password_hash("admin123", PASSWORD_DEFAULT) . "\\n";
echo "Student Hash: " . password_hash("student123", PASSWORD_DEFAULT) . "\\n";
?&gt;</pre>
    
    <p>Or click here to generate hashes now:</p>
    <a href="generate_hash.php" target="_blank" style="display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px;">Generate Hashes</a>
</div>';

// Check database connection
echo '<div class="step">
    <h3>Step 2: Database Connection</h3>';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=studysphere', 'root', '');
    echo '<p class="success">✓ Database connection successful!</p>';
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo '<p>Found ' . count($tables) . ' tables in database.</p>';
    
    // Check users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    echo '<p>Users in database: ' . $user_count . '</p>';
    
} catch (PDOException $e) {
    echo '<p class="error">✗ Database connection failed: ' . $e->getMessage() . '</p>';
    echo '<p>Make sure:</p>
    <ol>
        <li>You have imported the SQL files (studysphere_schema.sql and studysphere_seed.sql)</li>
        <li>Database name is "studysphere"</li>
        <li>Username is "root" (default for XAMPP)</li>
        <li>Password is empty (default for XAMPP)</li>
    </ol>';
}

echo '</div>';

// Check directories
echo '<div class="step">
    <h3>Step 3: Directory Permissions</h3>';

$dirs = [
    'storage/uploads/attachments' => 'storage/uploads/attachments',
    'storage/uploads/profile_photos' => 'storage/uploads/profile_photos'
];

foreach ($dirs as $name => $path) {
    if (file_exists($path) && is_writable($path)) {
        echo '<p class="success">✓ ' . $name . ' is writable</p>';
    } else {
        echo '<p class="error">✗ ' . $name . ' is not writable</p>';
        echo '<p>Run these commands:</p>
        <pre>mkdir -p ' . $path . '
chmod 755 ' . $path . '</pre>';
    }
}

echo '</div>';

// Login information
echo '<div class="step">
    <h3>Step 4: Login Information</h3>
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #f8f9fa;">
            <th>Role</th>
            <th>Email</th>
            <th>Default Password</th>
            <th>Login URL</th>
        </tr>
        <tr>
            <td><strong>Super Admin</strong></td>
            <td>admin@studysphere.local</td>
            <td>admin123</td>
            <td><a href="public/login.php">Login Page</a></td>
        </tr>
        <tr>
            <td><strong>Student</strong></td>
            <td>demo@student.com</td>
            <td>student123</td>
            <td><a href="public/login.php">Login Page</a></td>
        </tr>
    </table>
    
    <p><strong>Note:</strong> You MUST update the password hashes in the database using the generated hashes from Step 1.</p>
</div>';

// Important URLs
echo '<div class="step">
    <h3>Step 5: Important URLs</h3>
    <ul>
        <li><a href="public/" target="_blank">Home Page</a></li>
        <li><a href="public/login.php" target="_blank">Login Page</a></li>
        <li><a href="public/register.php" target="_blank">Student Registration</a></li>
        <li><a href="public/admin/dashboard.php" target="_blank">Admin Dashboard</a> (Login as admin first)</li>
        <li><a href="public/student/dashboard.php" target="_blank">Student Dashboard</a> (Login as student first)</li>
        <li><a href="public/admin/users.php" target="_blank">User Management</a> (Admin only)</li>
    </ul>
</div>';

// Update database script
echo '<div class="step">
    <h3>Step 6: Update Passwords in Database</h3>
    <p>After generating hashes, run these SQL queries in phpMyAdmin:</p>
    <pre>-- Update admin password
UPDATE users SET password_hash = \'$2y$10$YOUR_ADMIN_HASH_HERE\' 
WHERE email = \'admin@studysphere.local\';

-- Update student password  
UPDATE users SET password_hash = \'$2y$10$YOUR_STUDENT_HASH_HERE\'
WHERE email = \'demo@student.com\';</pre>
    
    <p>Or use this one-time update script:</p>
    <a href="update_passwords.php" target="_blank" style="display: inline-block; padding: 10px 20px; background: #27ae60; color: white; text-decoration: none; border-radius: 4px;">Update Passwords</a>
</div>';

echo '</body></html>';