<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Visa Details";
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

$visa_id = $_GET['id'] ?? 0;


$visa = $db->fetchOne("
    SELECT v.*, vt.name as visa_type_name, vt.country_code, vt.requirements,
           p.program_name, u.name as university_name,
           e.name as embassy_name, e.city as embassy_city, e.address as embassy_address,
           e.contact_phone as embassy_phone, e.contact_email as embassy_email, e.website as embassy_website
    FROM student_visas v
    JOIN visa_types vt ON v.visa_type_id = vt.visa_type_id
    LEFT JOIN programs p ON v.program_id = p.program_id
    LEFT JOIN universities u ON p.university_id = u.university_id
    LEFT JOIN embassy_consulate_records e ON v.embassy_id = e.embassy_id
    WHERE v.visa_id = ? AND v.student_id = ?
", [$visa_id, $student_id]);

if (!$visa) {
    echo "<div class='alert alert-error'>Visa not found or access denied.</div>";
    require_once '../../app/templates/footer.php';
    exit;
}


$documents = $db->fetchAll("
    SELECT vd.*, a.filename, a.file_url, a.file_type, a.file_size, a.created_at as uploaded_at
    FROM visa_documents vd
    JOIN attachments a ON vd.attachment_id = a.attachment_id
    WHERE vd.visa_id = ?
", [$visa_id]);


$history = $db->fetchAll("
    SELECT h.*, u.username as changed_by_name
    FROM visa_status_history h
    LEFT JOIN users u ON h.changed_by = u.user_id
    WHERE h.visa_id = ?
    ORDER BY h.changed_at DESC
", [$visa_id]);
?>


<div class="page-header">
    <h1><i class="fas fa-passport"></i> Visa Details</h1>
    <a href="visa.php" class="btn btn-secondary">Back to Visa Tracker</a>
</div>

<div class="card">
    <div class="visa-header">
        <div>
            <h2 style="margin: 0 0 10px 0;"><?php echo $visa['visa_type_name']; ?></h2>
            <div class="badge-group">
                <span class="badge badge-country"><?php echo $visa['country_code']; ?></span>
                <span class="status-badge status-<?php echo $visa['status']; ?>">
                    <?php echo ucfirst($visa['status']); ?>
                </span>
            </div>
        </div>
        
        <div class="visa-dates">
            <?php if ($visa['application_date']): ?>
                <p><strong>Applied:</strong> <?php echo Helpers::formatDate($visa['application_date']); ?></p>
            <?php endif; ?>
            
            <?php if ($visa['approval_date']): ?>
                <p><strong>Approved:</strong> <?php echo Helpers::formatDate($visa['approval_date']); ?></p>
            <?php endif; ?>
            
            <?php if ($visa['expiry_date']): ?>
                <p><strong>Expires:</strong> <?php echo Helpers::formatDate($visa['expiry_date']); ?></p>
                <?php if (strtotime($visa['expiry_date']) < time()): ?>
                    <span class="badge badge-warning">Expired</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="visa-info-grid">
        <div class="info-section">
            <h4><i class="fas fa-university"></i> Program Details</h4>
            <p><strong>Program:</strong> <?php echo $visa['program_name'] ?: 'Not specified'; ?></p>
            <p><strong>University:</strong> <?php echo $visa['university_name'] ?: 'Not specified'; ?></p>
        </div>
        
        <div class="info-section">
            <h4><i class="fas fa-building"></i> Embassy/Consulate</h4>
            <?php if ($visa['embassy_name']): ?>
                <p><strong>Name:</strong> <?php echo $visa['embassy_name']; ?></p>
                <p><strong>City:</strong> <?php echo $visa['embassy_city']; ?></p>
                <p><strong>Address:</strong> <?php echo $visa['embassy_address']; ?></p>
                <?php if ($visa['embassy_phone']): ?>
                    <p><strong>Phone:</strong> <?php echo $visa['embassy_phone']; ?></p>
                <?php endif; ?>
                <?php if ($visa['embassy_website']): ?>
                    <p><strong>Website:</strong> <a href="<?php echo $visa['embassy_website']; ?>" target="_blank">Visit</a></p>
                <?php endif; ?>
            <?php else: ?>
                <p>No embassy information recorded.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($visa['notes']): ?>
        <div class="notes-section">
            <h4><i class="fas fa-sticky-note"></i> Notes</h4>
            <p><?php echo nl2br(htmlspecialchars($visa['notes'])); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php if ($visa['requirements']): ?>
<div class="card">
    <h4><i class="fas fa-list-check"></i> Visa Requirements</h4>
    <div class="requirements">
        <?php echo nl2br(htmlspecialchars($visa['requirements'])); ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h4><i class="fas fa-paperclip"></i> Documents (<?php echo count($documents); ?>)</h4>
    
    <?php if (empty($documents)): ?>
        <p>No documents uploaded yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Document Type</th>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?php echo $doc['document_type']; ?></td>
                    <td><?php echo $doc['filename']; ?></td>
                    <td><?php echo $doc['file_type']; ?></td>
                    <td><?php echo round($doc['file_size']/1024, 1); ?> KB</td>
                    <td><?php echo Helpers::formatDate($doc['uploaded_at']); ?></td>
                    <td>
                        <a href="<?php echo $doc['file_url']; ?>" target="_blank" class="btn btn-small">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="<?php echo $doc['file_url']; ?>" download class="btn btn-small">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h4><i class="fas fa-history"></i> Status History</h4>
    
    <?php if (empty($history)): ?>
        <p>No status history available.</p>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($history as $event): ?>
            <div class="timeline-event">
                <div class="timeline-date">
                    <?php echo Helpers::formatDate($event['changed_at'], 'M j, Y g:i A'); ?>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <?php if ($event['old_status']): ?>
                            Status changed from 
                            <span class="status-label"><?php echo ucfirst($event['old_status']); ?></span>
                            to 
                            <span class="status-label"><?php echo ucfirst($event['new_status']); ?></span>
                        <?php else: ?>
                            Status set to 
                            <span class="status-label"><?php echo ucfirst($event['new_status']); ?></span>
                        <?php endif; ?>
                        <?php if ($event['changed_by_name']): ?>
                            <span class="timeline-by">by <?php echo $event['changed_by_name']; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($event['note']): ?>
                        <div class="timeline-note">
                            <i class="fas fa-comment"></i> <?php echo htmlspecialchars($event['note']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.visa-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.badge-group {
    display: flex;
    gap: 10px;
    margin-top: 5px;
}

.badge-country {
    background: #3498db;
    color: white;
}

.visa-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.info-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #3498db;
}

.info-section h4 {
    margin-bottom: 1rem;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.notes-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #fff8e1;
    border-radius: 8px;
}

.requirements {
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 4px;
    white-space: pre-wrap;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #3498db;
}

.timeline-event {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-event:before {
    content: '';
    position: absolute;
    left: -25px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #3498db;
    border: 3px solid white;
}

.timeline-date {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
}

.timeline-header {
    font-weight: 500;
}

.status-label {
    display: inline-block;
    padding: 2px 8px;
    background: #e3f2fd;
    border-radius: 12px;
    font-size: 0.9rem;
}

.timeline-by {
    color: #666;
    font-size: 0.9rem;
    margin-left: 10px;
}

.timeline-note {
    margin-top: 8px;
    padding: 8px;
    background: #e8f4fd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.status-approved { background: #d4edda; color: #155724; }
.status-applied { background: #fff3cd; color: #856404; }
.status-processing { background: #cce5ff; color: #004085; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-not_started { background: #e2e3e5; color: #383d41; }

@media (max-width: 768px) {
    .visa-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .visa-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>