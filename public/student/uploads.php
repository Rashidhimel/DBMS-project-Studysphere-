<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Document Management";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $category = Helpers::sanitize($_POST['category'] ?? '');
    
    $result = $upload_helper->uploadFile($_FILES['file'], $user['user_id']);
    
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        if (!empty($category) && isset($result['attachment_id'])) {
            $db->update('attachments', 
                ['category' => $category],
                "attachment_id = " . $result['attachment_id']
            );
        }
        Helpers::redirect('uploads.php', 'File uploaded successfully!');
    }
}

// Handle file deletion
if (isset($_GET['delete'])) {
    $attachment_id = intval($_GET['delete']);
    
    $attachment = $db->fetchOne(
        "SELECT * FROM attachments WHERE attachment_id = ? AND uploader_user_id = ?",
        [$attachment_id, $user['user_id']]
    );
    
    if ($attachment) {
        $upload_helper->deleteAttachment($attachment_id);
        Helpers::redirect('uploads.php', 'File deleted successfully!');
    } else {
        $error = "File not found or access denied.";
    }
}

// Get attachments with categories
$attachments = $upload_helper->getUserAttachments($user['user_id']);

// Get storage statistics
$storage_stats = $upload_helper->getStorageStats($user['user_id']);

// Get file type categories for filter
$file_categories = $db->fetchAll("
    SELECT DISTINCT category FROM attachments 
    WHERE uploader_user_id = ? AND deleted_at IS NULL AND category IS NOT NULL
    ORDER BY category
", [$user['user_id']]);

$flash = Helpers::getFlash();
?>

<div class="page-header">
    <h1><i class="fas fa-file-upload"></i> Document Management</h1>
    <p>Upload, organize, and manage all your application documents</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Modern Two-Card Layout -->
<div class="cards-grid">
    <!-- Storage Overview Card -->
    <div class="modern-card">
        <div class="card-header">
            <div class="card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                <i class="fas fa-database"></i>
            </div>
            <h3>Storage Overview</h3>
        </div>
        <div class="card-content">
            <div class="stat-items">
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-file"></i></div>
                    <div class="stat-details">
                        <span class="stat-value"><?php echo $storage_stats['total_files']; ?></span>
                        <span class="stat-label">Total Files</span>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-database"></i></div>
                    <div class="stat-details">
                        <span class="stat-value"><?php echo round($storage_stats['total_size'] / 1024 / 1024, 2); ?> MB</span>
                        <span class="stat-label">Used</span>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-hdd"></i></div>
                    <div class="stat-details">
                        <span class="stat-value"><?php echo round($storage_stats['remaining_space'] / 1024 / 1024, 2); ?> MB</span>
                        <span class="stat-label">Free</span>
                    </div>
                </div>
            </div>
            <div class="progress-section">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $storage_stats['used_percentage']; ?>%;"></div>
                </div>
                <p class="progress-text"><?php echo $storage_stats['used_percentage']; ?>% of 50MB used</p>
            </div>
        </div>
    </div>
    
    <!-- Quick Upload Card -->
    <div class="modern-card">
        <div class="card-header">
            <div class="card-icon" style="background: linear-gradient(135deg, #27ae60, #229954);">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h3>Quick Upload</h3>
        </div>
        <div class="card-content">
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <div class="file-input-wrapper">
                    <input type="file" id="file" name="file" required>
                    <label for="file" class="btn btn-secondary"><i class="fas fa-folder-open"></i> Choose File</label>
                    <span class="file-name" id="file-name">No file chosen</span>
                </div>
                <small class="file-hint">Max 5MB • PDF, DOC, JPG, PNG</small>
                
                <div class="form-group">
                    <label for="category">Category (Optional)</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">Select Category</option>
                        <option value="transcript">📄 Transcript</option>
                        <option value="test_score">📊 Test Score Report</option>
                        <option value="passport">🆔 Passport/ID</option>
                        <option value="financial">💰 Financial Document</option>
                        <option value="recommendation">✉️ Recommendation Letter</option>
                        <option value="certificate">🏅 Certificate</option>
                        <option value="other">📁 Other</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" 
                        <?php echo $storage_stats['remaining_space'] < 1024 ? 'disabled' : ''; ?>>
                    <i class="fas fa-upload"></i> Upload File
                </button>
                
                <?php if ($storage_stats['remaining_space'] < 1024): ?>
                    <p class="text-danger">Storage full! Please delete some files to upload more.</p>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Filters Bar (unchanged from refined version) -->
<div class="filters-bar">
    <div class="search-wrapper">
        <i class="fas fa-search"></i>
        <input type="text" id="searchFiles" placeholder="Search files..." class="form-control">
    </div>
    <div class="filter-tabs">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="my">My Categories</button>
        <button class="filter-btn" data-filter="usage">Usage Categories</button>
    </div>
    <div class="category-filters" id="myCategoryFilters" style="display: none;">
        <select id="filterMyCategory" class="form-control">
            <option value="">All My Categories</option>
            <?php foreach ($file_categories as $cat): ?>
                <option value="<?php echo $cat['category']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $cat['category'])); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="category-filters" id="usageCategoryFilters" style="display: none;">
        <select id="filterUsageCategory" class="form-control">
            <option value="">All Usage Categories</option>
            <option value="Education">Education</option>
            <option value="Test Score">Test Scores</option>
            <option value="Achievement">Achievements</option>
            <option value="Application">Applications</option>
            <option value="Recommendation">Recommendations</option>
            <option value="Visa">Visa</option>
        </select>
    </div>
</div>

<!-- Documents Grid (unchanged from refined version) -->
<?php if (empty($attachments)): ?>
    <div class="empty-state">
        <i class="fas fa-file-upload fa-4x"></i>
        <h4>No documents uploaded yet</h4>
        <p>Upload your first document to get started with your applications.</p>
    </div>
<?php else: ?>
    <div class="documents-grid" id="documentsGrid">
        <?php foreach ($attachments as $file): 
            $file_icon = getFileIcon($file['file_type']);
            $selected_category = $file['selected_category'] ?? '';
            $category_icon = getCategoryIcon($selected_category);
        ?>
            <div class="document-card" 
                 data-selected-category="<?php echo $selected_category; ?>"
                 data-usage-categories="<?php echo $file['categories']; ?>"
                 data-filename="<?php echo strtolower($file['filename']); ?>">
                <div class="card-header">
                    <i class="fas fa-file-<?php echo $file_icon; ?> file-icon"></i>
                    <span class="file-type"><?php echo strtoupper(pathinfo($file['filename'], PATHINFO_EXTENSION)); ?></span>
                </div>
                <div class="card-body">
                    <h4 title="<?php echo htmlspecialchars($file['filename']); ?>">
                        <?php echo htmlspecialchars(substr($file['filename'], 0, 30)) . (strlen($file['filename']) > 30 ? '…' : ''); ?>
                    </h4>
                    <div class="file-meta">
                        <span><i class="fas fa-weight-hanging"></i> <?php echo formatFileSize($file['file_size']); ?></span>
                        <span><i class="far fa-calendar"></i> <?php echo Helpers::formatDate($file['created_at'], 'M d, Y'); ?></span>
                    </div>
                    <div class="categories">
                        <?php if ($selected_category): ?>
                            <span class="category-tag category-selected" title="Your category">
                                <i class="fas <?php echo $category_icon; ?>"></i> <?php echo ucfirst(str_replace('_', ' ', $selected_category)); ?>
                            </span>
                        <?php endif; ?>
                        <?php 
                        $usage_cats = explode(',', $file['categories']);
                        foreach ($usage_cats as $cat):
                            if (trim($cat) && trim($cat) != 'Uncategorized'): ?>
                                <span class="category-tag usage-tag" title="Used in <?php echo trim($cat); ?>">
                                    <i class="fas <?php echo getUsageIcon(trim($cat)); ?>"></i> <?php echo trim($cat); ?>
                                </span>
                            <?php endif;
                        endforeach; 
                        if (!$selected_category && (count($usage_cats) == 1 && trim($usage_cats[0]) == 'Uncategorized')): ?>
                            <span class="category-tag">Uncategorized</span>
                        <?php endif; ?>
                    </div>
                    <div class="status">
                        <span class="status-badge status-<?php echo $file['verified_at'] ? 'verified' : 'pending'; ?>">
                            <?php echo $file['verified_at'] ? 'Verified' : 'Pending'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo $file['file_url']; ?>" target="_blank" class="btn-icon" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="/studysphere/public/download_file.php?id=<?php echo $file['attachment_id']; ?>" class="btn-icon" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <a href="uploads.php?delete=<?php echo $file['attachment_id']; ?>" 
                       class="btn-icon btn-danger" 
                       title="Delete"
                       onclick="return confirm('Are you sure you want to delete this file? This action cannot be undone.')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File name display
    const fileInput = document.getElementById('file');
    const fileNameSpan = document.getElementById('file-name');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            fileNameSpan.textContent = this.files.length ? this.files[0].name : 'No file chosen';
        });
    }
    
    // Filter logic (unchanged)
    const searchInput = document.getElementById('searchFiles');
    const filterTabs = document.querySelectorAll('.filter-btn');
    const myCatDiv = document.getElementById('myCategoryFilters');
    const usageCatDiv = document.getElementById('usageCategoryFilters');
    const myCatSelect = document.getElementById('filterMyCategory');
    const usageCatSelect = document.getElementById('filterUsageCategory');
    const cards = document.querySelectorAll('.document-card');
    
    let currentFilterMode = 'all';
    let currentMyCat = '';
    let currentUsageCat = '';
    
    filterTabs.forEach(btn => {
        btn.addEventListener('click', function() {
            filterTabs.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilterMode = this.dataset.filter;
            
            myCatDiv.style.display = currentFilterMode === 'my' ? 'block' : 'none';
            usageCatDiv.style.display = currentFilterMode === 'usage' ? 'block' : 'none';
            
            if (currentFilterMode !== 'my') {
                myCatSelect.value = '';
                currentMyCat = '';
            }
            if (currentFilterMode !== 'usage') {
                usageCatSelect.value = '';
                currentUsageCat = '';
            }
            
            filterCards();
        });
    });
    
    myCatSelect.addEventListener('change', function() {
        currentMyCat = this.value;
        filterCards();
    });
    
    usageCatSelect.addEventListener('change', function() {
        currentUsageCat = this.value;
        filterCards();
    });
    
    searchInput.addEventListener('input', filterCards);
    
    function filterCards() {
        const searchTerm = searchInput.value.toLowerCase();
        
        cards.forEach(card => {
            const filename = card.dataset.filename;
            const selectedCat = card.dataset.selectedCategory || '';
            const usageCats = card.dataset.usageCategories.toLowerCase();
            
            const matchesSearch = filename.includes(searchTerm);
            
            let matchesCategory = true;
            if (currentFilterMode === 'my') {
                matchesCategory = !currentMyCat || selectedCat === currentMyCat;
            } else if (currentFilterMode === 'usage') {
                matchesCategory = !currentUsageCat || usageCats.includes(currentUsageCat.toLowerCase());
            }
            
            card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
        });
    }
});
</script>

<?php
function getFileIcon($fileType) {
    if (strpos($fileType, 'pdf') !== false) return 'pdf';
    if (strpos($fileType, 'word') !== false || strpos($fileType, 'doc') !== false) return 'word';
    if (strpos($fileType, 'image') !== false) return 'image';
    if (strpos($fileType, 'text') !== false) return 'alt';
    return 'file';
}

function getCategoryIcon($category) {
    switch ($category) {
        case 'transcript': return 'fa-file-alt';
        case 'test_score': return 'fa-chart-line';
        case 'passport': return 'fa-id-card';
        case 'financial': return 'fa-dollar-sign';
        case 'recommendation': return 'fa-envelope';
        case 'certificate': return 'fa-certificate';
        default: return 'fa-tag';
    }
}

function getUsageIcon($usage) {
    switch (strtolower($usage)) {
        case 'education': return 'fa-graduation-cap';
        case 'test score': return 'fa-chart-line';
        case 'achievement': return 'fa-trophy';
        case 'application': return 'fa-file-alt';
        case 'recommendation': return 'fa-envelope';
        case 'visa': return 'fa-passport';
        default: return 'fa-tag';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<style>
/* Modern two-card layout */
.cards-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.modern-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.05);
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
    padding: 1.5rem 1.8rem 0.5rem 1.8rem;
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
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
}

.modern-card .card-content {
    padding: 0 1.8rem 1.8rem 1.8rem;
}

/* Storage stats */
.stat-items {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin: 1.5rem 0;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    background: #f8fafc;
    padding: 1rem;
    border-radius: 16px;
    transition: background 0.2s;
}

.stat-item:hover {
    background: #f0f4f8;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3498db;
    font-size: 1.2rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.02);
}

.stat-details {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2c3e50;
}

.stat-label {
    font-size: 0.8rem;
    color: #7f8c8d;
}

.progress-section {
    margin-top: 1rem;
}

.progress-bar {
    height: 8px;
    background: #ecf0f1;
    border-radius: 8px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2ecc71);
    border-radius: 8px;
    transition: width 0.3s;
}

.progress-text {
    margin: 0.5rem 0 0;
    text-align: right;
    font-size: 0.85rem;
    color: #7f8c8d;
}

/* Upload form */
.upload-form .file-input-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1rem 0 0.5rem;
    flex-wrap: wrap;
}
.file-input-wrapper input[type="file"] {
    display: none;
}
.file-input-wrapper .btn {
    margin: 0;
    white-space: nowrap;
}
.file-name {
    color: #7f8c8d;
    font-size: 0.9rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}
.file-hint {
    display: block;
    color: #95a5a6;
    margin-bottom: 1rem;
}
.upload-form .form-group {
    margin-bottom: 1rem;
}
.upload-form .form-group label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.9rem;
}
.upload-form .form-group select {
    width: 100%;
    padding: 0.6rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.95rem;
}
.upload-form .btn-block {
    width: 100%;
}
.text-danger {
    color: #e74c3c;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

/* Filters bar (unchanged from refined) */
.filters-bar {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin: 2rem 0;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
}
.search-wrapper {
    flex: 1;
    position: relative;
    min-width: 200px;
}
.search-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #bdc3c7;
}
.search-wrapper input {
    padding-left: 2.5rem;
    width: 100%;
}
.filter-tabs {
    display: flex;
    gap: 0.5rem;
}
.filter-btn {
    padding: 0.4rem 1rem;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 30px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.filter-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}
.filter-btn:hover {
    background: #f8f9fa;
}
.category-filters select {
    min-width: 180px;
}

/* Documents grid (unchanged from refined) */
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}
.document-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.document-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.1);
}
.document-card .card-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.file-icon {
    font-size: 1.5rem;
}
.file-type {
    font-size: 0.8rem;
    background: rgba(255,255,255,0.2);
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
}
.document-card .card-body {
    padding: 1.5rem;
}
.document-card h4 {
    margin: 0 0 0.5rem;
    font-size: 1rem;
    color: #2c3e50;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.8rem;
    color: #7f8c8d;
    font-size: 0.85rem;
}
.categories {
    margin-bottom: 0.8rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.category-tag {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    background: #e3f2fd;
    color: #1976d2;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}
.category-selected {
    background: #4CAF50;
    color: white;
}
.usage-tag {
    background: #fff3e0;
    color: #f57c00;
}
.status {
    margin-top: 0.5rem;
}
.status-badge {
    display: inline-block;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-pending {
    background: #ffeeba;
    color: #856404;
}
.status-verified {
    background: #d4edda;
    color: #155724;
}
.document-card .card-footer {
    padding: 0.8rem 1.5rem 1.5rem;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    border-top: 1px solid #f0f0f0;
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
.btn {
    padding: 0.6rem 1.2rem;
    border-radius: 40px;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.9rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(52,152,219,0.3);
}
.btn-secondary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
}
.btn-secondary:hover {
    background: #e9ecef;
}
.btn-block {
    width: 100%;
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
</style>

<?php require_once '../../app/templates/footer.php'; ?>