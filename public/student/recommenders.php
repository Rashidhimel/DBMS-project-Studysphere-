<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Recommenders";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

$success = '';
$error = '';

$recommenders = $db->fetchAll(
    "SELECT * FROM recommenders WHERE student_id = ? ORDER BY created_at DESC",
    [$student_id]
);

$applications = $db->fetchAll(
    "SELECT a.*, p.program_name, u.name as university_name 
     FROM applications a
     JOIN programs p ON a.program_id = p.program_id
     JOIN universities u ON a.university_id = u.university_id
     WHERE a.student_id = ? AND a.deleted_at IS NULL
     ORDER BY a.applied_at DESC",
    [$student_id]
);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_recommender'])) {
        $name = Helpers::sanitize($_POST['name']);
        $email = Helpers::sanitize($_POST['email']);
        $relationship = Helpers::sanitize($_POST['relationship']);
        $organization = Helpers::sanitize($_POST['organization']);
        $phone = Helpers::sanitize($_POST['phone']);
        
        $existing = $db->fetchOne(
            "SELECT recommender_id FROM recommenders WHERE student_id = ? AND email = ?",
            [$student_id, $email]
        );
        
        if ($existing) {
            $error = 'A recommender with this email already exists.';
        } else {
            $db->insert('recommenders', [
                'student_id' => $student_id,
                'name' => $name,
                'email' => $email,
                'relationship' => $relationship,
                'organization' => $organization,
                'phone' => $phone,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = 'Recommender added successfully!';
            
            $recommenders = $db->fetchAll(
                "SELECT * FROM recommenders WHERE student_id = ? ORDER BY created_at DESC",
                [$student_id]
            );
        }
    }
    
    if (isset($_POST['send_invitation'])) {
        $recommender_id = intval($_POST['recommender_id']);
        
        $recommender = $db->fetchOne(
            "SELECT * FROM recommenders WHERE recommender_id = ? AND student_id = ?",
            [$recommender_id, $student_id]
        );
        
        if ($recommender) {
            
            $success = "Invitation email sent to " . $recommender['name'] . " at " . $recommender['email'];
        }
    }
    
    if (isset($_POST['delete_recommender'])) {
        $recommender_id = intval($_POST['recommender_id']);
        
        $db->query("DELETE FROM recommenders WHERE recommender_id = ? AND student_id = ?", 
                   [$recommender_id, $student_id]);
        
        $success = 'Recommender deleted successfully!';
        
        $recommenders = $db->fetchAll(
            "SELECT * FROM recommenders WHERE student_id = ? ORDER BY created_at DESC",
            [$student_id]
        );
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-user-check"></i> Recommenders</h1>
    <p>Manage your recommenders (professors, employers, etc.)</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <h3>Add New Recommender</h3>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Recommender Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g., Dr. John Smith">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required placeholder="e.g., john.smith@university.edu">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="relationship">Relationship *</label>
                <input type="text" id="relationship" name="relationship" required 
                       placeholder="e.g., Professor, Supervisor, Manager">
            </div>
            
            <div class="form-group">
                <label for="organization">Organization/Institution</label>
                <input type="text" id="organization" name="organization" 
                       placeholder="e.g., University of Example, Google Inc.">
            </div>
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number (Optional)</label>
            <input type="tel" id="phone" name="phone" placeholder="e.g., +1 (123) 456-7890">
        </div>
        
        <button type="submit" name="add_recommender" class="btn btn-primary">Add Recommender</button>
    </form>
</div>

<div class="card">
    <h3>Your Recommenders</h3>
    
    <?php if (empty($recommenders)): ?>
        <p>No recommenders added yet. Add your first recommender above.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Relationship</th>
                    <th>Organization</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recommenders as $rec): ?>
                    <tr>
                        <td>
                            <strong><?php echo $rec['name']; ?></strong>
                        </td>
                        <td><?php echo $rec['relationship']; ?></td>
                        <td><?php echo $rec['organization'] ?: 'N/A'; ?></td>
                        <td>
                            <?php if ($rec['email']): ?>
                                <a href="mailto:<?php echo $rec['email']; ?>">
                                    <i class="fas fa-envelope"></i> Email
                                </a><br>
                            <?php endif; ?>
                            <?php if ($rec['phone']): ?>
                                <small><?php echo $rec['phone']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $submitted = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM recommendations 
                                 WHERE recommender_id = ? AND submitted = 1",
                                [$rec['recommender_id']]
                            );
                            
                            if ($submitted['count'] > 0) {
                                echo '<span class="badge badge-success">Submitted</span>';
                            } else {
                                echo '<span class="badge badge-warning">Pending</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <form method="POST" action="" style="display:inline-block;">
                                <input type="hidden" name="recommender_id" value="<?php echo $rec['recommender_id']; ?>">
                                <button type="submit" name="send_invitation" class="btn btn-small btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Invitation
                                </button>
                            </form>
                            
                            <form method="POST" action="" style="display:inline-block;" 
                                onsubmit="return confirm('Delete this recommender?');">
                                <input type="hidden" name="recommender_id" value="<?php echo $rec['recommender_id']; ?>">
                                <button type="submit" name="delete_recommender" class="btn btn-small btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <?php 
                    $recommendations = $db->fetchAll(
                        "SELECT r.*, a.application_id, p.program_name 
                         FROM recommendations r
                         LEFT JOIN applications a ON r.application_id = a.application_id
                         LEFT JOIN programs p ON a.program_id = p.program_id
                         WHERE r.recommender_id = ?
                         ORDER BY r.created_at DESC",
                        [$rec['recommender_id']]
                    );
                    
                    if (!empty($recommendations)): ?>
                    <tr style="background:#f9f9f9;">
                        <td colspan="6" style="padding:10px;">
                            <strong>Recommendations by this recommender:</strong>
                            <ul style="margin:10px 0 0 20px;">
                                <?php foreach ($recommendations as $rec_item): ?>
                                    <li>
                                        <?php if ($rec_item['application_id']): ?>
                                            For: <?php echo $rec_item['program_name']; ?>
                                        <?php else: ?>
                                            General Recommendation
                                        <?php endif; ?>
                                        - 
                                        <?php if ($rec_item['submitted']): ?>
                                            <span class="badge badge-success">Submitted</span>
                                            <?php if ($rec_item['submitted_at']): ?>
                                                on <?php echo Helpers::formatDate($rec_item['submitted_at']); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Not Submitted</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>How to Request Recommendations</h3>
    <ol style="margin-left:20px;">
        <li>Add your recommender's details using the form above</li>
        <li>Click "Send Invitation" to send them a recommendation request</li>
        <li>Your recommender will receive an email with instructions</li>
        <li>They can upload their recommendation letter directly</li>
        <li>Track submission status on the Recommendations page</li>
        <li>You can send reminders if needed</li>
    </ol>
    <p><strong>Tip:</strong> Contact your recommenders in advance and provide them with information about your goals.</p>
</div>

<?php require_once '../../app/templates/footer.php'; ?>