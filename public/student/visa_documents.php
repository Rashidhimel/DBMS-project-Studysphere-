<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Visa Documents";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$error = '';

// Ensure student record has visa_checklist column (JSON)
$student = $db->fetchOne("SELECT visa_checklist FROM students WHERE student_id = ?", [$student_id]);
if (!$student) {
    $student = ['visa_checklist' => '[]'];
}

// Decode checklist
$checklist = json_decode($student['visa_checklist'] ?? '[]', true);
if (!is_array($checklist)) {
    $checklist = [];
}

// Standard checklist items
$checklist_items = [
    'passport' => 'Valid Passport (6+ months validity)',
    'acceptance' => 'Letter of Acceptance',
    'financial' => 'Proof of Financial Support',
    'photos' => 'Passport-sized Photographs',
    'application_form' => 'Completed Application Form',
    'health_insurance' => 'Health Insurance Proof',
    'transcripts' => 'Academic Transcripts',
    'accommodation' => 'Proof of Accommodation',
    'visa_fee' => 'Visa Fee Payment Receipt',
    'biometric' => 'Biometric Appointment Confirmation',
    'travel_itinerary' => 'Travel Itinerary',
    'criminal_record' => 'Police Clearance Certificate'
];

// Get all visa documents
$documents = $db->fetchAll("
    SELECT vd.*, a.filename, a.file_url, a.file_type, a.file_size, a.created_at as attachment_created_at,
           v.visa_id, vt.name as visa_type_name, v.status as visa_status,
           p.program_name, u.name as university_name
    FROM visa_documents vd
    JOIN attachments a ON vd.attachment_id = a.attachment_id
    JOIN student_visas v ON vd.visa_id = v.visa_id
    JOIN visa_types vt ON v.visa_type_id = vt.visa_type_id
    LEFT JOIN programs p ON v.program_id = p.program_id
    LEFT JOIN universities u ON p.university_id = u.university_id
    WHERE v.student_id = ? AND a.deleted_at IS NULL
    ORDER BY vd.uploaded_at DESC
", [$student_id]);

// Get visa applications
$visas = $db->fetchAll("
    SELECT v.*, vt.name as visa_type_name, p.program_name, u.name as university_name
    FROM student_visas v
    JOIN visa_types vt ON v.visa_type_id = vt.visa_type_id
    LEFT JOIN programs p ON v.program_id = p.program_id
    LEFT JOIN universities u ON p.university_id = u.university_id
    WHERE v.student_id = ?
    ORDER BY v.created_at DESC
", [$student_id]);

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['new_document'])) {
    $visa_id = intval($_POST['visa_id']);
    $document_type = Helpers::sanitize($_POST['document_type']);
    
    $visa = $db->fetchOne(
        "SELECT visa_id FROM student_visas WHERE visa_id = ? AND student_id = ?",
        [$visa_id, $student_id]
    );
    
    if ($visa) {
        $result = $upload_helper->uploadFile($_FILES['new_document'], $user['user_id'], 'visa');
        
        if (isset($result['success']) && $result['success']) {
            $db->insert('visa_documents', [
                'visa_id' => $visa_id,
                'attachment_id' => $result['attachment_id'],
                'document_type' => $document_type,
                'uploaded_by' => $user['user_id']
            ]);
            Helpers::redirect('visa_documents.php', 'Document uploaded successfully!');
        } else {
            $error = $result['error'] ?? 'Upload failed';
        }
    } else {
        $error = 'Invalid visa selection';
    }
}

// Handle document delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = intval($_GET['delete']);
    
    $document = $db->fetchOne("
        SELECT vd.* FROM visa_documents vd
        JOIN student_visas v ON vd.visa_id = v.visa_id
        WHERE vd.id = ? AND v.student_id = ?
    ", [$doc_id, $student_id]);
    
    if ($document) {
        $db->update('attachments', [
            'deleted_at' => date('Y-m-d H:i:s')
        ], "attachment_id = {$document['attachment_id']}");
        Helpers::redirect('visa_documents.php', 'Document deleted successfully!');
    } else {
        $error = 'Document not found or access denied';
    }
}

// AJAX handler for checklist update
if (isset($_POST['update_checklist'])) {
    $checked = json_decode($_POST['checked'], true);
    $db->update('students', 
        ['visa_checklist' => json_encode($checked)],
        "student_id = $student_id"
    );
    echo json_encode(['success' => true]);
    exit;
}

$flash = Helpers::getFlash();
?>

<div class="page-header">
    <h1><i class="fas fa-passport"></i> Visa Documents</h1>
    <p>Manage your visa application documents and track your progress</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Main grid -->
<div class="visa-grid">
    <!-- Left Column: Upload & Documents -->
    <div class="visa-left">
        <!-- Upload Card -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <h3>Upload New Document</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="visa_id">Visa Application</label>
                            <select id="visa_id" name="visa_id" required>
                                <option value="">Select visa</option>
                                <?php foreach ($visas as $visa): ?>
                                    <option value="<?php echo $visa['visa_id']; ?>">
                                        <?php echo htmlspecialchars($visa['visa_type_name']); ?>
                                        <?php if ($visa['program_name']): ?>
                                            - <?php echo htmlspecialchars($visa['program_name']); ?>
                                        <?php endif; ?>
                                        (<?php echo ucfirst($visa['status']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="document_type">Document Type *</label>
                            <input type="text" id="document_type" name="document_type" required
                                   placeholder="e.g., Passport, Bank Statement">
                        </div>
                    </div>
                    <div class="file-input-wrapper">
                        <label for="new_document" class="btn btn-secondary"><i class="fas fa-folder-open"></i> Choose File</label>
                        <input type="file" id="new_document" name="new_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        <span class="file-name" id="file-name">No file chosen</span>
                        <small>PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Upload Document</button>
                </form>
            </div>
        </div>

        <!-- Documents List -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Your Documents (<?php echo count($documents); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open fa-3x"></i>
                        <p>No documents uploaded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="document-filters">
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchDocs" placeholder="Search documents...">
                        </div>
                        <select id="filterVisa" class="filter-select">
                            <option value="">All Visas</option>
                            <?php foreach ($visas as $visa): ?>
                                <option value="<?php echo $visa['visa_id']; ?>">
                                    <?php echo htmlspecialchars($visa['visa_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="documents-grid">
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-card" data-visa-id="<?php echo $doc['visa_id']; ?>">
                                <div class="document-icon">
                                    <i class="fas fa-file-<?php 
                                        $ext = pathinfo($doc['filename'], PATHINFO_EXTENSION);
                                        echo in_array($ext, ['pdf']) ? 'pdf' : 
                                             (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'alt');
                                    ?>"></i>
                                </div>
                                <div class="document-info">
                                    <h4 title="<?php echo htmlspecialchars($doc['filename']); ?>">
                                        <?php echo htmlspecialchars(substr($doc['filename'], 0, 25)) . (strlen($doc['filename']) > 25 ? '…' : ''); ?>
                                    </h4>
                                    <p class="document-type"><?php echo htmlspecialchars($doc['document_type']); ?></p>
                                    <p class="document-meta">
                                        <span><i class="far fa-calendar"></i> <?php echo Helpers::formatDate($doc['uploaded_at'], 'M d, Y'); ?></span>
                                        <span><i class="fas fa-weight-hanging"></i> <?php echo round($doc['file_size']/1024, 1); ?> KB</span>
                                    </p>
                                    <div class="visa-badge" style="background: #e3f2fd; color: #1976d2;">
                                        <?php echo htmlspecialchars($doc['visa_type_name']); ?> (<?php echo ucfirst($doc['visa_status']); ?>)
                                    </div>
                                </div>
                                <div class="document-actions">
                                    <a href="<?php echo $doc['file_url']; ?>" target="_blank" class="btn-icon" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $doc['file_url']; ?>" download class="btn-icon" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="?delete=<?php echo $doc['id']; ?>" class="btn-icon btn-danger" 
                                       onclick="return confirm('Delete this document?');" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Checklist -->
    <div class="visa-right">
        <div class="modern-card sticky">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #27ae60, #229954);">
                    <i class="fas fa-check-double"></i>
                </div>
                <h3>Document Checklist</h3>
            </div>
            <div class="card-body">
                <p class="checklist-hint">Check items as you complete them – your progress is saved automatically.</p>
                <div class="checklist" id="checklist">
                    <?php foreach ($checklist_items as $key => $label): ?>
                        <div class="checklist-item">
                            <label>
                                <input type="checkbox" class="checklist-checkbox" data-key="<?php echo $key; ?>" 
                                       <?php echo in_array($key, $checklist) ? 'checked' : ''; ?>>
                                <span><?php echo $label; ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="progress-section">
                    <div class="progress-bar">
                        <?php 
                        $total = count($checklist_items);
                        $completed = count($checklist);
                        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                        ?>
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                    <p class="progress-text"><?php echo $completed; ?>/<?php echo $total; ?> items completed</p>
                </div>
                <div class="checklist-actions">
                    <button class="btn btn-secondary btn-sm" onclick="printChecklist()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="clearChecklist()">
                        <i class="fas fa-undo"></i> Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File name display
    const fileInput = document.getElementById('new_document');
    const fileNameSpan = document.getElementById('file-name');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            fileNameSpan.textContent = this.files.length ? this.files[0].name : 'No file chosen';
        });
    }

    // Document filtering
    const searchInput = document.getElementById('searchDocs');
    const filterSelect = document.getElementById('filterVisa');
    const documentCards = document.querySelectorAll('.document-card');

    function filterDocuments() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const visaId = filterSelect ? filterSelect.value : '';
        
        documentCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const cardVisaId = card.dataset.visaId;
            const matchesSearch = text.includes(searchTerm);
            const matchesVisa = !visaId || cardVisaId === visaId;
            card.style.display = (matchesSearch && matchesVisa) ? 'flex' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterDocuments);
    if (filterSelect) filterSelect.addEventListener('change', filterDocuments);

    // Checklist persistence via AJAX
    const checkboxes = document.querySelectorAll('.checklist-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateChecklist();
        });
    });

    function updateChecklist() {
        const checkedKeys = [];
        document.querySelectorAll('.checklist-checkbox:checked').forEach(cb => {
            checkedKeys.push(cb.dataset.key);
        });
        
        fetch('visa_documents.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'update_checklist=1&checked=' + encodeURIComponent(JSON.stringify(checkedKeys))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update progress bar
                const total = <?php echo $total; ?>;
                const completed = checkedKeys.length;
                const percentage = Math.round((completed / total) * 100);
                const progressFill = document.querySelector('.progress-fill');
                const progressText = document.querySelector('.progress-text');
                if (progressFill) progressFill.style.width = percentage + '%';
                if (progressText) progressText.textContent = completed + '/' + total + ' items completed';
            }
        });
    }

    window.printChecklist = function() {
        const checked = [];
        document.querySelectorAll('.checklist-checkbox:checked').forEach(cb => {
            checked.push(cb.closest('.checklist-item').querySelector('span').textContent);
        });
        const items = Array.from(document.querySelectorAll('.checklist-item span')).map(span => span.textContent);
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Visa Document Checklist</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 30px; }
                        h1 { color: #2c3e50; }
                        ul { list-style: none; padding: 0; }
                        li { margin: 10px 0; font-size: 14px; }
                        .checked { color: #27ae60; font-weight: bold; }
                        .date { margin-top: 30px; color: #7f8c8d; }
                    </style>
                </head>
                <body>
                    <h1>Visa Document Checklist</h1>
                    <p><strong>Generated on:</strong> ${new Date().toLocaleDateString()}</p>
                    <ul>
                        ${items.map(item => {
                            const isChecked = checked.includes(item);
                            return `<li>${isChecked ? '✅ ' : '☐ '} ${item}</li>`;
                        }).join('')}
                    </ul>
                    <div class="date">
                        <p><em>Checked items are marked with ✅. Complete all before your visa interview.</em></p>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    };

    window.clearChecklist = function() {
        if (confirm('Clear all checklist items?')) {
            document.querySelectorAll('.checklist-checkbox').forEach(cb => cb.checked = false);
            updateChecklist();
        }
    };
});
</script>

<style>
/* Visa grid */
.visa-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-top: 1.5rem;
}

/* Left column */
.visa-left {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

/* Right column */
.visa-right .sticky {
    position: sticky;
    top: 1rem;
}

/* Modern card (reuse) */
.modern-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.modern-card:hover {
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}
.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
}
.card-icon {
    width: 45px;
    height: 45px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.4rem;
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}
.card-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
}
.card-body {
    padding: 2rem;
}

/* Form */
.form-grid {
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
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
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
.btn-block {
    width: 100%;
}

/* Document grid */
.documents-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-top: 1rem;
}
.document-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #f8fafc;
    border-radius: 16px;
    padding: 1rem;
    transition: background 0.2s;
}
.document-card:hover {
    background: #f0f4f8;
}
.document-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #3498db;
    box-shadow: 0 4px 8px rgba(0,0,0,0.02);
}
.document-info {
    flex: 1;
    min-width: 0;
}
.document-info h4 {
    margin: 0 0 0.2rem;
    font-size: 1rem;
    color: #2c3e50;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.document-type {
    margin: 0 0 0.3rem;
    font-size: 0.85rem;
    color: #666;
}
.document-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: #95a5a6;
}
.visa-badge {
    display: inline-block;
    margin-top: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.document-actions {
    display: flex;
    gap: 0.3rem;
    flex-shrink: 0;
}
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    color: #495057;
    transition: all 0.2s;
    text-decoration: none;
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

/* Document filters */
.document-filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}
.search-wrapper {
    flex: 1;
    position: relative;
}
.search-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #bdc3c7;
}
.search-wrapper input {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.9rem;
}
.filter-select {
    width: 200px;
    padding: 0.6rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.9rem;
}

/* Checklist */
.checklist-hint {
    font-size: 0.85rem;
    color: #7f8c8d;
    margin-bottom: 1rem;
}
.checklist-item {
    margin-bottom: 0.5rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    transition: background 0.2s;
}
.checklist-item:hover {
    background: #f0f4f8;
}
.checklist-item label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}
.checklist-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #27ae60;
}
.checklist-item span {
    font-size: 0.9rem;
    color: #2c3e50;
}

/* Progress */
.progress-section {
    margin: 1.5rem 0 0.5rem;
}
.progress-bar {
    height: 8px;
    background: #ecf0f1;
    border-radius: 8px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #27ae60, #2ecc71);
    border-radius: 8px;
    transition: width 0.3s;
}
.progress-text {
    text-align: right;
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-top: 0.3rem;
}

/* Checklist actions */
.checklist-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}
.btn {
    padding: 0.5rem 1rem;
    border-radius: 40px;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52,152,219,0.3);
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
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #7f8c8d;
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 0.5rem;
}

/* Responsive */
@media (max-width: 992px) {
    .visa-grid {
        grid-template-columns: 1fr;
    }
    .visa-right .sticky {
        position: static;
    }
    .document-filters {
        flex-direction: column;
    }
    .filter-select {
        width: 100%;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?><?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Visa Documents";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$error = '';

// Ensure student record has visa_checklist column (JSON)
$student = $db->fetchOne("SELECT visa_checklist FROM students WHERE student_id = ?", [$student_id]);
if (!$student) {
    $student = ['visa_checklist' => '[]'];
}

// Decode checklist
$checklist = json_decode($student['visa_checklist'] ?? '[]', true);
if (!is_array($checklist)) {
    $checklist = [];
}

// Standard checklist items
$checklist_items = [
    'passport' => 'Valid Passport (6+ months validity)',
    'acceptance' => 'Letter of Acceptance',
    'financial' => 'Proof of Financial Support',
    'photos' => 'Passport-sized Photographs',
    'application_form' => 'Completed Application Form',
    'health_insurance' => 'Health Insurance Proof',
    'transcripts' => 'Academic Transcripts',
    'accommodation' => 'Proof of Accommodation',
    'visa_fee' => 'Visa Fee Payment Receipt',
    'biometric' => 'Biometric Appointment Confirmation',
    'travel_itinerary' => 'Travel Itinerary',
    'criminal_record' => 'Police Clearance Certificate'
];

// Get all visa documents
$documents = $db->fetchAll("
    SELECT vd.*, a.filename, a.file_url, a.file_type, a.file_size, a.created_at as attachment_created_at,
           v.visa_id, vt.name as visa_type_name, v.status as visa_status,
           p.program_name, u.name as university_name
    FROM visa_documents vd
    JOIN attachments a ON vd.attachment_id = a.attachment_id
    JOIN student_visas v ON vd.visa_id = v.visa_id
    JOIN visa_types vt ON v.visa_type_id = vt.visa_type_id
    LEFT JOIN programs p ON v.program_id = p.program_id
    LEFT JOIN universities u ON p.university_id = u.university_id
    WHERE v.student_id = ? AND a.deleted_at IS NULL
    ORDER BY vd.uploaded_at DESC
", [$student_id]);

// Get visa applications
$visas = $db->fetchAll("
    SELECT v.*, vt.name as visa_type_name, p.program_name, u.name as university_name
    FROM student_visas v
    JOIN visa_types vt ON v.visa_type_id = vt.visa_type_id
    LEFT JOIN programs p ON v.program_id = p.program_id
    LEFT JOIN universities u ON p.university_id = u.university_id
    WHERE v.student_id = ?
    ORDER BY v.created_at DESC
", [$student_id]);

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['new_document'])) {
    $visa_id = intval($_POST['visa_id']);
    $document_type = Helpers::sanitize($_POST['document_type']);
    
    $visa = $db->fetchOne(
        "SELECT visa_id FROM student_visas WHERE visa_id = ? AND student_id = ?",
        [$visa_id, $student_id]
    );
    
    if ($visa) {
        $result = $upload_helper->uploadFile($_FILES['new_document'], $user['user_id'], 'visa');
        
        if (isset($result['success']) && $result['success']) {
            $db->insert('visa_documents', [
                'visa_id' => $visa_id,
                'attachment_id' => $result['attachment_id'],
                'document_type' => $document_type,
                'uploaded_by' => $user['user_id']
            ]);
            Helpers::redirect('visa_documents.php', 'Document uploaded successfully!');
        } else {
            $error = $result['error'] ?? 'Upload failed';
        }
    } else {
        $error = 'Invalid visa selection';
    }
}

// Handle document delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = intval($_GET['delete']);
    
    $document = $db->fetchOne("
        SELECT vd.* FROM visa_documents vd
        JOIN student_visas v ON vd.visa_id = v.visa_id
        WHERE vd.id = ? AND v.student_id = ?
    ", [$doc_id, $student_id]);
    
    if ($document) {
        $db->update('attachments', [
            'deleted_at' => date('Y-m-d H:i:s')
        ], "attachment_id = {$document['attachment_id']}");
        Helpers::redirect('visa_documents.php', 'Document deleted successfully!');
    } else {
        $error = 'Document not found or access denied';
    }
}

// AJAX handler for checklist update
if (isset($_POST['update_checklist'])) {
    $checked = json_decode($_POST['checked'], true);
    $db->update('students', 
        ['visa_checklist' => json_encode($checked)],
        "student_id = $student_id"
    );
    echo json_encode(['success' => true]);
    exit;
}

$flash = Helpers::getFlash();
?>

<div class="page-header">
    <h1><i class="fas fa-passport"></i> Visa Documents</h1>
    <p>Manage your visa application documents and track your progress</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Main grid -->
<div class="visa-grid">
    <!-- Left Column: Upload & Documents -->
    <div class="visa-left">
        <!-- Upload Card -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <h3>Upload New Document</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="visa_id">Visa Application</label>
                            <select id="visa_id" name="visa_id" required>
                                <option value="">Select visa</option>
                                <?php foreach ($visas as $visa): ?>
                                    <option value="<?php echo $visa['visa_id']; ?>">
                                        <?php echo htmlspecialchars($visa['visa_type_name']); ?>
                                        <?php if ($visa['program_name']): ?>
                                            - <?php echo htmlspecialchars($visa['program_name']); ?>
                                        <?php endif; ?>
                                        (<?php echo ucfirst($visa['status']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="document_type">Document Type *</label>
                            <input type="text" id="document_type" name="document_type" required
                                   placeholder="e.g., Passport, Bank Statement">
                        </div>
                    </div>
                    <div class="file-input-wrapper">
                        <label for="new_document" class="btn btn-secondary"><i class="fas fa-folder-open"></i> Choose File</label>
                        <input type="file" id="new_document" name="new_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        <span class="file-name" id="file-name">No file chosen</span>
                        <small>PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Upload Document</button>
                </form>
            </div>
        </div>

        <!-- Documents List -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Your Documents (<?php echo count($documents); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open fa-3x"></i>
                        <p>No documents uploaded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="document-filters">
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchDocs" placeholder="Search documents...">
                        </div>
                        <select id="filterVisa" class="filter-select">
                            <option value="">All Visas</option>
                            <?php foreach ($visas as $visa): ?>
                                <option value="<?php echo $visa['visa_id']; ?>">
                                    <?php echo htmlspecialchars($visa['visa_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="documents-grid">
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-card" data-visa-id="<?php echo $doc['visa_id']; ?>">
                                <div class="document-icon">
                                    <i class="fas fa-file-<?php 
                                        $ext = pathinfo($doc['filename'], PATHINFO_EXTENSION);
                                        echo in_array($ext, ['pdf']) ? 'pdf' : 
                                             (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'alt');
                                    ?>"></i>
                                </div>
                                <div class="document-info">
                                    <h4 title="<?php echo htmlspecialchars($doc['filename']); ?>">
                                        <?php echo htmlspecialchars(substr($doc['filename'], 0, 25)) . (strlen($doc['filename']) > 25 ? '…' : ''); ?>
                                    </h4>
                                    <p class="document-type"><?php echo htmlspecialchars($doc['document_type']); ?></p>
                                    <p class="document-meta">
                                        <span><i class="far fa-calendar"></i> <?php echo Helpers::formatDate($doc['uploaded_at'], 'M d, Y'); ?></span>
                                        <span><i class="fas fa-weight-hanging"></i> <?php echo round($doc['file_size']/1024, 1); ?> KB</span>
                                    </p>
                                    <div class="visa-badge" style="background: #e3f2fd; color: #1976d2;">
                                        <?php echo htmlspecialchars($doc['visa_type_name']); ?> (<?php echo ucfirst($doc['visa_status']); ?>)
                                    </div>
                                </div>
                                <div class="document-actions">
                                    <a href="<?php echo $doc['file_url']; ?>" target="_blank" class="btn-icon" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $doc['file_url']; ?>" download class="btn-icon" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="?delete=<?php echo $doc['id']; ?>" class="btn-icon btn-danger" 
                                       onclick="return confirm('Delete this document?');" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Checklist -->
    <div class="visa-right">
        <div class="modern-card sticky">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #27ae60, #229954);">
                    <i class="fas fa-check-double"></i>
                </div>
                <h3>Document Checklist</h3>
            </div>
            <div class="card-body">
                <p class="checklist-hint">Check items as you complete them – your progress is saved automatically.</p>
                <div class="checklist" id="checklist">
                    <?php foreach ($checklist_items as $key => $label): ?>
                        <div class="checklist-item">
                            <label>
                                <input type="checkbox" class="checklist-checkbox" data-key="<?php echo $key; ?>" 
                                       <?php echo in_array($key, $checklist) ? 'checked' : ''; ?>>
                                <span><?php echo $label; ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="progress-section">
                    <div class="progress-bar">
                        <?php 
                        $total = count($checklist_items);
                        $completed = count($checklist);
                        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                        ?>
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                    <p class="progress-text"><?php echo $completed; ?>/<?php echo $total; ?> items completed</p>
                </div>
                <div class="checklist-actions">
                    <button class="btn btn-secondary btn-sm" onclick="printChecklist()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="clearChecklist()">
                        <i class="fas fa-undo"></i> Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File name display
    const fileInput = document.getElementById('new_document');
    const fileNameSpan = document.getElementById('file-name');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            fileNameSpan.textContent = this.files.length ? this.files[0].name : 'No file chosen';
        });
    }

    // Document filtering
    const searchInput = document.getElementById('searchDocs');
    const filterSelect = document.getElementById('filterVisa');
    const documentCards = document.querySelectorAll('.document-card');

    function filterDocuments() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const visaId = filterSelect ? filterSelect.value : '';
        
        documentCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const cardVisaId = card.dataset.visaId;
            const matchesSearch = text.includes(searchTerm);
            const matchesVisa = !visaId || cardVisaId === visaId;
            card.style.display = (matchesSearch && matchesVisa) ? 'flex' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterDocuments);
    if (filterSelect) filterSelect.addEventListener('change', filterDocuments);

    // Checklist persistence via AJAX
    const checkboxes = document.querySelectorAll('.checklist-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateChecklist();
        });
    });

    function updateChecklist() {
        const checkedKeys = [];
        document.querySelectorAll('.checklist-checkbox:checked').forEach(cb => {
            checkedKeys.push(cb.dataset.key);
        });
        
        fetch('visa_documents.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'update_checklist=1&checked=' + encodeURIComponent(JSON.stringify(checkedKeys))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update progress bar
                const total = <?php echo $total; ?>;
                const completed = checkedKeys.length;
                const percentage = Math.round((completed / total) * 100);
                const progressFill = document.querySelector('.progress-fill');
                const progressText = document.querySelector('.progress-text');
                if (progressFill) progressFill.style.width = percentage + '%';
                if (progressText) progressText.textContent = completed + '/' + total + ' items completed';
            }
        });
    }

    window.printChecklist = function() {
        const checked = [];
        document.querySelectorAll('.checklist-checkbox:checked').forEach(cb => {
            checked.push(cb.closest('.checklist-item').querySelector('span').textContent);
        });
        const items = Array.from(document.querySelectorAll('.checklist-item span')).map(span => span.textContent);
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Visa Document Checklist</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 30px; }
                        h1 { color: #2c3e50; }
                        ul { list-style: none; padding: 0; }
                        li { margin: 10px 0; font-size: 14px; }
                        .checked { color: #27ae60; font-weight: bold; }
                        .date { margin-top: 30px; color: #7f8c8d; }
                    </style>
                </head>
                <body>
                    <h1>Visa Document Checklist</h1>
                    <p><strong>Generated on:</strong> ${new Date().toLocaleDateString()}</p>
                    <ul>
                        ${items.map(item => {
                            const isChecked = checked.includes(item);
                            return `<li>${isChecked ? '✅ ' : '☐ '} ${item}</li>`;
                        }).join('')}
                    </ul>
                    <div class="date">
                        <p><em>Checked items are marked with ✅. Complete all before your visa interview.</em></p>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    };

    window.clearChecklist = function() {
        if (confirm('Clear all checklist items?')) {
            document.querySelectorAll('.checklist-checkbox').forEach(cb => cb.checked = false);
            updateChecklist();
        }
    };
});
</script>

<style>
/* Visa grid */
.visa-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-top: 1.5rem;
}

/* Left column */
.visa-left {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

/* Right column */
.visa-right .sticky {
    position: sticky;
    top: 1rem;
}

/* Modern card (reuse) */
.modern-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.modern-card:hover {
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}
.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
}
.card-icon {
    width: 45px;
    height: 45px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.4rem;
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}
.card-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
}
.card-body {
    padding: 2rem;
}

/* Form */
.form-grid {
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
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
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
.btn-block {
    width: 100%;
}

/* Document grid */
.documents-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-top: 1rem;
}
.document-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #f8fafc;
    border-radius: 16px;
    padding: 1rem;
    transition: background 0.2s;
}
.document-card:hover {
    background: #f0f4f8;
}
.document-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #3498db;
    box-shadow: 0 4px 8px rgba(0,0,0,0.02);
}
.document-info {
    flex: 1;
    min-width: 0;
}
.document-info h4 {
    margin: 0 0 0.2rem;
    font-size: 1rem;
    color: #2c3e50;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.document-type {
    margin: 0 0 0.3rem;
    font-size: 0.85rem;
    color: #666;
}
.document-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: #95a5a6;
}
.visa-badge {
    display: inline-block;
    margin-top: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.document-actions {
    display: flex;
    gap: 0.3rem;
    flex-shrink: 0;
}
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    color: #495057;
    transition: all 0.2s;
    text-decoration: none;
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

/* Document filters */
.document-filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}
.search-wrapper {
    flex: 1;
    position: relative;
}
.search-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #bdc3c7;
}
.search-wrapper input {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.9rem;
}
.filter-select {
    width: 200px;
    padding: 0.6rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.9rem;
}

/* Checklist */
.checklist-hint {
    font-size: 0.85rem;
    color: #7f8c8d;
    margin-bottom: 1rem;
}
.checklist-item {
    margin-bottom: 0.5rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    transition: background 0.2s;
}
.checklist-item:hover {
    background: #f0f4f8;
}
.checklist-item label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}
.checklist-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #27ae60;
}
.checklist-item span {
    font-size: 0.9rem;
    color: #2c3e50;
}

/* Progress */
.progress-section {
    margin: 1.5rem 0 0.5rem;
}
.progress-bar {
    height: 8px;
    background: #ecf0f1;
    border-radius: 8px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #27ae60, #2ecc71);
    border-radius: 8px;
    transition: width 0.3s;
}
.progress-text {
    text-align: right;
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-top: 0.3rem;
}

/* Checklist actions */
.checklist-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}
.btn {
    padding: 0.5rem 1rem;
    border-radius: 40px;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52,152,219,0.3);
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
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #7f8c8d;
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 0.5rem;
}

/* Responsive */
@media (max-width: 992px) {
    .visa-grid {
        grid-template-columns: 1fr;
    }
    .visa-right .sticky {
        position: static;
    }
    .document-filters {
        flex-direction: column;
    }
    .filter-select {
        width: 100%;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>