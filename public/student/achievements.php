<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Achievements & Activities";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$error = '';

$achievement_types = $db->fetchAll("SELECT * FROM achievement_types ORDER BY name");

$achievements = $db->fetchAll(
    "SELECT a.*, at.name as type_name 
     FROM achievements a 
     JOIN achievement_types at ON a.achievement_type_id = at.achievement_type_id 
     WHERE a.student_id = ? AND a.deleted_at IS NULL 
     ORDER BY a.end_date DESC",
    [$student_id]
);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_achievement'])) {
        $type_id = intval($_POST['achievement_type_id']);
        $title = Helpers::sanitize($_POST['title']);
        $description = Helpers::sanitize($_POST['description']);
        $organization = Helpers::sanitize($_POST['organization']);
        $start_date = Helpers::sanitize($_POST['start_date']);
        $end_date = Helpers::sanitize($_POST['end_date']);
        
        $db->insert('achievements', [
            'student_id' => $student_id,
            'achievement_type_id' => $type_id,
            'title' => $title,
            'description' => $description,
            'organization' => $organization,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $achievement_id = $db->getConnection()->lastInsertId();
        
        if (isset($_FILES['achievement_file']) && $_FILES['achievement_file']['error'] == 0) {
            $result = $upload_helper->uploadFile($_FILES['achievement_file'], $user['user_id'], 'achievement');
            
            if (isset($result['success']) && $result['success']) {
                $db->insert('achievement_documents', [
                    'achievement_id' => $achievement_id,
                    'attachment_id' => $result['attachment_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $user['user_id']
                ]);
            }
        }
        
        Helpers::redirect('achievements.php', 'Achievement added successfully!');
    }
    
    if (isset($_POST['delete_achievement'])) {
        $achievement_id = intval($_POST['achievement_id']);
        
        $db->update('achievements', 
            ['deleted_at' => date('Y-m-d H:i:s')],
            "achievement_id = $achievement_id AND student_id = $student_id"
        );
        
        Helpers::redirect('achievements.php', 'Achievement deleted successfully!');
    }
    
    if (isset($_FILES['add_document']) && $_FILES['add_document']['error'] == 0) {
        $achievement_id = intval($_POST['achievement_id']);
        
        $achievement = $db->fetchOne(
            "SELECT achievement_id FROM achievements WHERE achievement_id = ? AND student_id = ?",
            [$achievement_id, $student_id]
        );
        
        if ($achievement) {
            $result = $upload_helper->uploadFile($_FILES['add_document'], $user['user_id'], 'achievement_doc');
            
            if (isset($result['success']) && $result['success']) {
                $db->insert('achievement_documents', [
                    'achievement_id' => $achievement_id,
                    'attachment_id' => $result['attachment_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $user['user_id']
                ]);
                Helpers::redirect('achievements.php', 'Document uploaded successfully!');
            } else {
                $error = $result['error'] ?? 'Upload failed';
            }
        }
    }
}

$flash = Helpers::getFlash();
?>

<div class="page-header">
    <h1><i class="fas fa-trophy"></i> Achievements & Activities</h1>
    <p>Showcase your extracurriculars, awards, projects, and experiences</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Add Achievement Card -->
<div class="modern-card">
    <div class="card-header">
        <div class="card-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
            <i class="fas fa-plus-circle"></i>
        </div>
        <h3>Add New Achievement</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label for="achievement_type_id">Achievement Type *</label>
                    <select id="achievement_type_id" name="achievement_type_id" required>
                        <option value="">Select Type</option>
                        <?php foreach ($achievement_types as $type): ?>
                            <option value="<?php echo $type['achievement_type_id']; ?>">
                                <?php echo $type['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="title">Title/Position *</label>
                    <input type="text" id="title" name="title" required placeholder="e.g., Research Assistant, President">
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="organization">Organization/Company</label>
                    <input type="text" id="organization" name="organization" placeholder="e.g., Google, University Name">
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date">
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date">
                    <small>Leave empty if ongoing</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" rows="4" required 
                    placeholder="Describe your role, responsibilities, and achievements..."></textarea>
            </div>
            
            <div class="file-input-wrapper">
                <label for="achievement_file" class="btn btn-secondary"><i class="fas fa-upload"></i> Choose File</label>
                <input type="file" id="achievement_file" name="achievement_file" accept=".pdf,.doc,.docx,.jpg,.png">
                <span class="file-name" id="file-name">No file chosen</span>
                <small>Certificate, award letter, project report, etc. (Max 5MB)</small>
            </div>
            
            <button type="submit" name="add_achievement" class="btn btn-primary">Add Achievement</button>
        </form>
    </div>
</div>

<!-- Achievements List -->
<?php if (empty($achievements)): ?>
    <div class="empty-state">
        <i class="fas fa-trophy fa-4x"></i>
        <h4>No achievements yet</h4>
        <p>Add your first achievement using the form above.</p>
    </div>
<?php else: ?>
    <h3 class="section-title">Your Achievements</h3>
    <div class="achievements-grid">
        <?php foreach ($achievements as $achievement): ?>
            <div class="achievement-card">
                <div class="card-header">
                    <div class="type-badge" style="background: <?php 
                        $colors = ['#3498db', '#9b59b6', '#e74c3c', '#f39c12', '#2ecc71', '#1abc9c'];
                        echo $colors[$achievement['achievement_type_id'] % count($colors)];
                    ?>;">
                        <?php echo $achievement['type_name']; ?>
                    </div>
                    <div class="card-actions">
                        <form method="POST" onsubmit="return confirm('Delete this achievement?');">
                            <input type="hidden" name="achievement_id" value="<?php echo $achievement['achievement_id']; ?>">
                            <button type="submit" name="delete_achievement" class="btn-icon btn-danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <h4><?php echo htmlspecialchars($achievement['title']); ?></h4>
                    <?php if ($achievement['organization']): ?>
                        <p class="organization"><i class="fas fa-building"></i> <?php echo htmlspecialchars($achievement['organization']); ?></p>
                    <?php endif; ?>
                    <?php if ($achievement['start_date']): ?>
                        <p class="date"><i class="far fa-calendar-alt"></i> 
                            <?php echo Helpers::formatDate($achievement['start_date'], 'M Y'); ?> - 
                            <?php echo $achievement['end_date'] ? Helpers::formatDate($achievement['end_date'], 'M Y') : 'Present'; ?>
                        </p>
                    <?php endif; ?>
                    <p class="description"><?php echo nl2br(htmlspecialchars($achievement['description'])); ?></p>
                    
                    <?php 
                    $documents = $db->fetchAll(
                        "SELECT a.* FROM attachments a
                         JOIN achievement_documents ad ON a.attachment_id = ad.attachment_id
                         WHERE ad.achievement_id = ? AND a.deleted_at IS NULL",
                        [$achievement['achievement_id']]
                    );
                    ?>
                    <?php if (!empty($documents)): ?>
                        <div class="documents">
                            <strong><i class="fas fa-paperclip"></i> Documents:</strong>
                            <ul>
                                <?php foreach ($documents as $doc): ?>
                                    <li>
                                        <a href="<?php echo $doc['file_url']; ?>" target="_blank">
                                            <i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($doc['filename']); ?>
                                            <small>(<?php echo round($doc['file_size']/1024, 1); ?> KB)</small>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <button class="btn-icon" onclick="toggleUploadForm(<?php echo $achievement['achievement_id']; ?>)" title="Add Document">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <!-- Hidden upload form -->
                <div id="upload-form-<?php echo $achievement['achievement_id']; ?>" class="upload-form" style="display:none;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="achievement_id" value="<?php echo $achievement['achievement_id']; ?>">
                        <div class="form-group">
                            <label>Add Supporting Document</label>
                            <input type="file" name="add_document" accept=".pdf,.doc,.docx,.jpg,.png" required>
                            <small>Certificate, letter, report, etc. (Max 5MB)</small>
                        </div>
                        <div class="upload-actions">
                            <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleUploadForm(<?php echo $achievement['achievement_id']; ?>)">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function toggleUploadForm(id) {
    const form = document.getElementById('upload-form-' + id);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// File name display
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('achievement_file');
    const fileNameSpan = document.getElementById('file-name');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            fileNameSpan.textContent = this.files.length ? this.files[0].name : 'No file chosen';
        });
    }
});
</script>

<style>
/* Modern card for form */
.modern-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.05);
    margin-bottom: 2.5rem;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.modern-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}
.modern-card .card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
}
.card-icon {
    width: 50px;
    height: 50px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.modern-card .card-header h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
    color: #2c3e50;
}
.modern-card .card-body {
    padding: 2rem;
}

/* Form grids */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.9rem;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.7rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #f39c12;
    box-shadow: 0 0 0 3px rgba(243,156,18,0.1);
}
textarea {
    resize: vertical;
}
small {
    display: block;
    margin-top: 0.3rem;
    color: #7f8c8d;
    font-size: 0.75rem;
}

/* File input */
.file-input-wrapper {
    margin-bottom: 1rem;
}
.file-input-wrapper input[type="file"] {
    display: none;
}
.file-input-wrapper .btn {
    margin-right: 0.5rem;
}
.file-name {
    color: #7f8c8d;
    font-size: 0.9rem;
}

/* Achievements grid */
.section-title {
    margin: 2rem 0 1rem;
    font-size: 1.4rem;
    color: #2c3e50;
}
.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}
.achievement-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
}
.achievement-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.1);
}
.achievement-card .card-header {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f0f0f0;
}
.type-badge {
    padding: 0.3rem 1rem;
    border-radius: 30px;
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.card-actions {
    display: flex;
    gap: 0.3rem;
}
.achievement-card .card-body {
    padding: 1.5rem;
    flex: 1;
}
.achievement-card h4 {
    margin: 0 0 0.8rem;
    font-size: 1.2rem;
    color: #2c3e50;
}
.organization {
    color: #666;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}
.organization i {
    width: 20px;
    color: #f39c12;
}
.date {
    color: #7f8c8d;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}
.date i {
    width: 20px;
}
.description {
    color: #4a5568;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}
.documents {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 0.8rem;
    margin-top: 1rem;
}
.documents strong {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #2c3e50;
}
.documents ul {
    list-style: none;
    padding-left: 0;
    margin: 0;
}
.documents li {
    margin: 0.3rem 0;
}
.documents a {
    color: #3498db;
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.documents a:hover {
    text-decoration: underline;
}
.achievement-card .card-footer {
    padding: 0.8rem 1.5rem 1.5rem;
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid #f0f0f0;
}
.upload-form {
    padding: 1rem 1.5rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e0e0e0;
}
.upload-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    color: #495057;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}
.btn-icon:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}
.btn-icon.btn-danger {
    color: #e74c3c;
}
.btn-icon.btn-danger:hover {
    background: #fee;
}
.btn {
    padding: 0.6rem 1.2rem;
    border-radius: 40px;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(243,156,18,0.3);
}
.btn-secondary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
}
.btn-secondary:hover {
    background: #e9ecef;
}
.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
}
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    margin-top: 2rem;
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}
.empty-state h4 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}
.empty-state p {
    color: #7f8c8d;
}
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
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    .achievements-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>