<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Document Moderation";
require_once '../../app/templates/header.php';

$db = new Database();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Handle actions
switch($action) {
    case 'verify':
        verifyDocument($id);
        break;
    case 'reject':
        rejectDocument($id);
        break;
    case 'view':
        viewDocument($id);
        break;
    case 'bulk':
        handleBulkAction();
        break;
    default:
        listDocuments();
}

function listDocuments() {
    global $db;
    
    $status = $_GET['status'] ?? 'pending';
    $type = $_GET['type'] ?? '';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    $where = "a.deleted_at IS NULL";
    $params = [];
    
    if ($status == 'pending') {
        $where .= " AND a.verified_at IS NULL";
    } elseif ($status == 'verified') {
        $where .= " AND a.verified_at IS NOT NULL";
    } elseif ($status == 'rejected') {
        $where .= " AND a.deleted_at IS NOT NULL";
    }
    
    if ($type) {
        if ($type == 'image') {
            $where .= " AND a.file_type LIKE 'image%'";
        } elseif ($type == 'pdf') {
            $where .= " AND a.file_type = 'application/pdf'";
        } elseif ($type == 'word') {
            $where .= " AND (a.file_type LIKE 'application/msword%' OR a.file_type LIKE 'application/vnd.openxmlformats-officedocument.wordprocessingml.document%')";
        }
    }
    
    if ($search) {
        $where .= " AND (a.filename LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if ($date_from) {
        $where .= " AND a.created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to) {
        $where .= " AND a.created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    // Counts for stats
    $pending_count = $db->fetchOne("SELECT COUNT(*) as count FROM attachments WHERE verified_at IS NULL AND deleted_at IS NULL")['count'];
    $verified_count = $db->fetchOne("SELECT COUNT(*) as count FROM attachments WHERE verified_at IS NOT NULL AND deleted_at IS NULL")['count'];
    $rejected_count = $db->fetchOne("SELECT COUNT(*) as count FROM attachments WHERE deleted_at IS NOT NULL")['count'];
    
    $documents = $db->fetchAll("
        SELECT a.*, u.username, u.email,
               DATEDIFF(NOW(), a.created_at) as days_old,
               (SELECT COUNT(*) FROM application_attachments aa WHERE aa.attachment_id = a.attachment_id) as app_usage
        FROM attachments a
        JOIN users u ON a.uploader_user_id = u.user_id
        WHERE $where
        ORDER BY a.created_at DESC
        LIMIT 50
    ", $params);
    
    ?>
    <div class="page-header">
        <h1><i class="fas fa-file-check"></i> Document Moderation</h1>
        <div class="stats-badges">
            <span class="stat-badge pending"><i class="fas fa-clock"></i> <?= $pending_count ?> Pending</span>
            <span class="stat-badge verified"><i class="fas fa-check-circle"></i> <?= $verified_count ?> Verified</span>
            <span class="stat-badge rejected"><i class="fas fa-times-circle"></i> <?= $rejected_count ?> Rejected</span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filter Documents</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <input type="hidden" name="action" value="list">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Filename, username, email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="verified" <?= $status == 'verified' ? 'selected' : '' ?>>Verified</option>
                            <option value="rejected" <?= $status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>File Type</label>
                        <select name="type">
                            <option value="">All Types</option>
                            <option value="pdf" <?= $type == 'pdf' ? 'selected' : '' ?>>PDF</option>
                            <option value="image" <?= $type == 'image' ? 'selected' : '' ?>>Image</option>
                            <option value="word" <?= $type == 'word' ? 'selected' : '' ?>>Word</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                        <a href="attachments_moderation.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Documents (<?= count($documents) ?>)</h3>
            <?php if ($status == 'pending' && count($documents) > 0): ?>
                <div id="singleModeButtons">
                    <button class="btn btn-outline-primary btn-sm" id="bulkSelectToggle"><i class="fas fa-check-double"></i> Bulk Select</button>
                </div>
                <div id="bulkModeButtons" style="display:none;">
                    <button class="btn btn-success btn-sm" id="bulkVerifyBtn"><i class="fas fa-check"></i> Verify Selected</button>
                    <button class="btn btn-danger btn-sm" id="bulkRejectBtn"><i class="fas fa-times"></i> Reject Selected</button>
                    <button class="btn btn-secondary btn-sm" id="cancelBulkBtn"><i class="fas fa-undo"></i> Cancel</button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body document-list">
            <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-upload fa-4x"></i>
                    <h4>No documents found</h4>
                    <p>Try adjusting your filters or upload some documents first.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table modern-table" id="documentsTable">
                        <thead>
                            <tr>
                                <th width="30" class="checkbox-col" style="display:none;"><input type="checkbox" id="selectAll"></th>
                                <th>File</th>
                                <th>Uploader</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($documents as $doc): 
                                $icon = getFileIcon($doc['file_type']);
                                $file_ext = strtoupper(pathinfo($doc['filename'], PATHINFO_EXTENSION));
                            ?>
                            <tr data-id="<?= $doc['attachment_id'] ?>">
                                <td class="checkbox-col" style="display:none;"><input type="checkbox" class="doc-check" value="<?= $doc['attachment_id'] ?>"></td>
                                <td>
                                    <div class="file-info">
                                        <i class="fas fa-file-<?= $icon ?> file-icon"></i>
                                        <div>
                                            <a href="attachments_moderation.php?action=view&id=<?= $doc['attachment_id'] ?>" class="file-name">
                                                <?= htmlspecialchars(substr($doc['filename'], 0, 40)) ?><?= strlen($doc['filename']) > 40 ? '…' : '' ?>
                                            </a>
                                            <small class="text-muted"><?= $file_ext ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <strong><?= htmlspecialchars($doc['username']) ?></strong>
                                        <small><?= htmlspecialchars($doc['email']) ?></small>
                                    </div>
                                </td>
                                <td><span class="badge type-badge"><?= $doc['file_type'] ?></span></td>
                                <td><?= formatFileSize($doc['file_size']) ?></td>
                                <td>
                                    <div class="date-info">
                                        <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                                        <small><?= $doc['days_old'] ?> days ago</small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($doc['app_usage'] > 0): ?>
                                        <span class="badge usage-badge"><?= $doc['app_usage'] ?> app(s)</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($doc['verified_at']): ?>
                                        <span class="status-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                                    <?php elseif($doc['deleted_at']): ?>
                                        <span class="status-badge rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                                    <?php else: ?>
                                        <span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= $doc['file_url'] ?>" target="_blank" class="btn-icon" title="Download"><i class="fas fa-download"></i></a>
                                        <?php if (!$doc['verified_at'] && !$doc['deleted_at']): ?>
                                            <a href="attachments_moderation.php?action=verify&id=<?= $doc['attachment_id'] ?>" class="btn-icon success" title="Verify" onclick="return confirm('Verify this document?')"><i class="fas fa-check"></i></a>
                                            <a href="#" class="btn-icon danger" title="Reject" onclick="showRejectModal(<?= $doc['attachment_id'] ?>, '<?= htmlspecialchars(addslashes($doc['filename'])) ?>'); return false;"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                        <a href="attachments_moderation.php?action=view&id=<?= $doc['attachment_id'] ?>" class="btn-icon info" title="View Details"><i class="fas fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Document</h3>
                <button class="close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <form method="POST" action="attachments_moderation.php?action=reject" id="rejectForm">
                <input type="hidden" name="id" id="rejectDocId">
                <div class="modal-body">
                    <p>Rejecting: <strong id="rejectDocName"></strong></p>
                    <div class="form-group">
                        <label for="reason">Reason for rejection *</label>
                        <textarea name="reason" id="reason" rows="3" class="form-control" required placeholder="Explain why this document is being rejected..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_student" value="1" checked> Notify student via email
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Reject Document</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const bulkToggle = document.getElementById('bulkSelectToggle');
        const singleButtons = document.getElementById('singleModeButtons');
        const bulkButtons = document.getElementById('bulkModeButtons');
        const checkboxCols = document.querySelectorAll('.checkbox-col');
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.doc-check');
        const cancelBtn = document.getElementById('cancelBulkBtn');
        const bulkVerify = document.getElementById('bulkVerifyBtn');
        const bulkReject = document.getElementById('bulkRejectBtn');

        if (bulkToggle) {
            bulkToggle.addEventListener('click', function() {
                // Show bulk mode
                singleButtons.style.display = 'none';
                bulkButtons.style.display = 'block';
                checkboxCols.forEach(col => col.style.display = 'table-cell');
                // Uncheck all
                if (selectAll) selectAll.checked = false;
                checkboxes.forEach(cb => cb.checked = false);
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                // Back to single mode
                singleButtons.style.display = 'block';
                bulkButtons.style.display = 'none';
                checkboxCols.forEach(col => col.style.display = 'none');
                // Uncheck all
                if (selectAll) selectAll.checked = false;
                checkboxes.forEach(cb => cb.checked = false);
            });
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        // Bulk verify
        if (bulkVerify) {
            bulkVerify.addEventListener('click', function() {
                const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
                if (selected.length === 0) {
                    alert('No documents selected.');
                    return;
                }
                if (confirm('Verify ' + selected.length + ' selected document(s)?')) {
                    // Create a form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'attachments_moderation.php?action=bulk';
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'bulk_action';
                    actionInput.value = 'verify';
                    form.appendChild(actionInput);
                    selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'doc_ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Bulk reject
        if (bulkReject) {
            bulkReject.addEventListener('click', function() {
                const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
                if (selected.length === 0) {
                    alert('No documents selected.');
                    return;
                }
                // Optionally ask for a reason – for simplicity, we just confirm.
                if (confirm('Reject ' + selected.length + ' selected document(s)? This action cannot be undone.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'attachments_moderation.php?action=bulk';
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'bulk_action';
                    actionInput.value = 'reject';
                    form.appendChild(actionInput);
                    selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'doc_ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    });

    function showRejectModal(id, name) {
        document.getElementById('rejectDocId').value = id;
        document.getElementById('rejectDocName').innerText = name;
        document.getElementById('rejectModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    </script>
    <?php
}

function handleBulkAction() {
    global $db;
    
    if (!isset($_POST['doc_ids']) || !is_array($_POST['doc_ids'])) {
        echo "<script>alert('No documents selected.'); location.href='attachments_moderation.php';</script>";
        return;
    }
    
    $action = $_POST['bulk_action'] ?? '';
    $ids = array_map('intval', $_POST['doc_ids']);
    
    if ($action == 'verify') {
        $db->query("UPDATE attachments SET verified_at = NOW(), verified_by_admin_id = ?, updated_at = NOW() WHERE attachment_id IN (" . implode(',', $ids) . ")", [$_SESSION['user_id']]);
        $message = "Selected documents verified.";
    } elseif ($action == 'reject') {
        $db->query("UPDATE attachments SET deleted_at = NOW(), updated_at = NOW(), updated_by = ? WHERE attachment_id IN (" . implode(',', $ids) . ")", [$_SESSION['user_id']]);
        $message = "Selected documents rejected.";
    } else {
        $message = "No action taken.";
    }
    
    echo "<script>alert('$message'); location.href='attachments_moderation.php';</script>";
}

function verifyDocument($id) {
    global $db;
    
    $db->update('attachments', [
        'verified_at' => date('Y-m-d H:i:s'),
        'verified_by_admin_id' => $_SESSION['user_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ], "attachment_id = $id");
    
    // Notify student
    $doc = $db->fetchOne("SELECT u.user_id, a.filename FROM attachments a JOIN users u ON a.uploader_user_id = u.user_id WHERE a.attachment_id = ?", [$id]);
    if ($doc) {
        $db->insert('notifications', [
            'user_id' => $doc['user_id'],
            'notification_type_id' => 2,
            'title' => 'Document Verified',
            'message' => "Your document '{$doc['filename']}' has been verified.",
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo "<script>alert('Document verified!'); location.href='attachments_moderation.php';</script>";
}

function rejectDocument($id) {
    global $db;
    
    $id = intval($_POST['id'] ?? $id);
    $reason = $_POST['reason'] ?? 'Document rejected by administrator';
    $notify = isset($_POST['notify_student']);
    
    $db->update('attachments', [
        'deleted_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['user_id']
    ], "attachment_id = $id");
    
    if ($notify) {
        $doc = $db->fetchOne("SELECT u.user_id, a.filename FROM attachments a JOIN users u ON a.uploader_user_id = u.user_id WHERE a.attachment_id = ?", [$id]);
        if ($doc) {
            $db->insert('notifications', [
                'user_id' => $doc['user_id'],
                'notification_type_id' => 2,
                'title' => 'Document Rejected',
                'message' => "Your document '{$doc['filename']}' was rejected: $reason",
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    echo "<script>alert('Document rejected!'); location.href='attachments_moderation.php';</script>";
}

function viewDocument($id) {
    global $db;
    
    $doc = $db->fetchOne("
        SELECT a.*, u.username, u.email, u.user_id,
               (SELECT COUNT(*) FROM application_attachments aa WHERE aa.attachment_id = a.attachment_id) as application_count,
               (SELECT COUNT(*) FROM education_documents ed WHERE ed.attachment_id = a.attachment_id) as education_count,
               (SELECT COUNT(*) FROM test_score_documents tsd WHERE tsd.attachment_id = a.attachment_id) as test_count,
               (SELECT COUNT(*) FROM achievement_documents ad WHERE ad.attachment_id = a.attachment_id) as achievement_count
        FROM attachments a
        JOIN users u ON a.uploader_user_id = u.user_id
        WHERE a.attachment_id = ?
    ", [$id]);
    
    if (!$doc) {
        echo "<div class='alert alert-error'>Document not found.</div>";
        echo "<a href='attachments_moderation.php' class='btn btn-secondary'>Back</a>";
        require_once '../../app/templates/footer.php';
        exit;
    }
    
    $applications = $db->fetchAll("
        SELECT aa.application_id, p.program_name, u.name as university_name, 
               aa.required_for, aa.uploaded_at
        FROM application_attachments aa
        JOIN applications a ON aa.application_id = a.application_id
        JOIN programs p ON a.program_id = p.program_id
        JOIN universities u ON a.university_id = u.university_id
        WHERE aa.attachment_id = ?
        ORDER BY aa.uploaded_at DESC
    ", [$id]);
    
    $education_links = $db->fetchAll("
        SELECT ed.*, er.institution_name, er.degree_name
        FROM education_documents ed
        JOIN education_records er ON ed.education_record_id = er.record_id
        WHERE ed.attachment_id = ?
    ", [$id]);
    
    $test_links = $db->fetchAll("
        SELECT tsd.*, ts.test_date, tt.name as test_name
        FROM test_score_documents tsd
        JOIN test_scores ts ON tsd.score_id = ts.score_id
        JOIN test_types tt ON ts.test_type_id = tt.test_type_id
        WHERE tsd.attachment_id = ?
    ", [$id]);
    
    ?>
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Document Details</h1>
        <div>
            <a href="<?= $doc['file_url'] ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-download"></i> Download</a>
            <a href="attachments_moderation.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Document Information</h3>
            <table class="info-table">
                <tr><td><strong>Filename:</strong></td><td><?= htmlspecialchars($doc['filename']) ?></td></tr>
                <tr><td><strong>Type:</strong></td><td><?= $doc['file_type'] ?></td></tr>
                <tr><td><strong>Size:</strong></td><td><?= formatFileSize($doc['file_size']) ?></td></tr>
                <tr><td><strong>Uploaded by:</strong></td><td><a href="user_edit.php?id=<?= $doc['user_id'] ?>"><?= htmlspecialchars($doc['username']) ?></a> (<?= htmlspecialchars($doc['email']) ?>)</td></tr>
                <tr><td><strong>Uploaded at:</strong></td><td><?= date('M d, Y H:i', strtotime($doc['created_at'])) ?></td></tr>
                <tr><td><strong>Checksum:</strong></td><td><code><?= $doc['checksum'] ?></code></td></tr>
            </table>
        </div>
        <div class="dashboard-card">
            <h3>Verification Status</h3>
            <table class="info-table">
                <tr><td><strong>Status:</strong></td>
                    <td>
                        <?php if($doc['verified_at']): ?>
                            <span class="status-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                        <?php elseif($doc['deleted_at']): ?>
                            <span class="status-badge rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                        <?php else: ?>
                            <span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if($doc['verified_at']): ?>
                <tr><td><strong>Verified at:</strong></td><td><?= date('M d, Y H:i', strtotime($doc['verified_at'])) ?></td></tr>
                <tr><td><strong>Verified by:</strong></td><td>Admin ID <?= $doc['verified_by_admin_id'] ?></td></tr>
                <?php endif; ?>
                <?php if($doc['deleted_at']): ?>
                <tr><td><strong>Rejected at:</strong></td><td><?= date('M d, Y H:i', strtotime($doc['deleted_at'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="dashboard-card">
        <h3>Usage in System</h3>
        <div class="stats-grid small">
            <div class="stat-card">
                <div class="stat-icon" style="background: #3498db;"><i class="fas fa-file-alt"></i></div>
                <div class="stat-content">
                    <h4><?= $doc['application_count'] ?></h4>
                    <p>Applications</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #2ecc71;"><i class="fas fa-graduation-cap"></i></div>
                <div class="stat-content">
                    <h4><?= $doc['education_count'] ?></h4>
                    <p>Education Records</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #f39c12;"><i class="fas fa-chart-line"></i></div>
                <div class="stat-content">
                    <h4><?= $doc['test_count'] ?></h4>
                    <p>Test Scores</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #9b59b6;"><i class="fas fa-trophy"></i></div>
                <div class="stat-content">
                    <h4><?= $doc['achievement_count'] ?></h4>
                    <p>Achievements</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($applications)): ?>
    <div class="dashboard-card">
        <h3>Linked Applications</h3>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <th>Program</th>
                        <th>University</th>
                        <th>Required For</th>
                        <th>Uploaded</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($applications as $app): ?>
                        <tr>
                            <td>#<?= $app['application_id'] ?></td>
                            <td><?= htmlspecialchars($app['program_name']) ?></td>
                            <td><?= htmlspecialchars($app['university_name']) ?></td>
                            <td><?= htmlspecialchars($app['required_for']) ?></td>
                            <td><?= date('M d, Y', strtotime($app['uploaded_at'])) ?></td>
                            <td><a href="application_view.php?id=<?= $app['application_id'] ?>" class="btn btn-small">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($education_links) || !empty($test_links)): ?>
    <div class="dashboard-grid">
        <?php if (!empty($education_links)): ?>
        <div class="dashboard-card">
            <h3>Education Records</h3>
            <ul>
                <?php foreach($education_links as $e): ?>
                    <li><a href="education_view.php?id=<?= $e['education_record_id'] ?>"><?= htmlspecialchars($e['institution_name']) ?> – <?= htmlspecialchars($e['degree_name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if (!empty($test_links)): ?>
        <div class="dashboard-card">
            <h3>Test Scores</h3>
            <ul>
                <?php foreach($test_links as $t): ?>
                    <li><a href="test_view.php?id=<?= $t['score_id'] ?>"><?= htmlspecialchars($t['test_name']) ?> (<?= date('M Y', strtotime($t['test_date'])) ?>)</a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-card">
        <h3>Document Preview</h3>
        <div class="preview-container">
            <?php if (strpos($doc['file_type'], 'image') !== false): ?>
                <img src="<?= $doc['file_url'] ?>" alt="Preview" class="img-preview">
            <?php elseif (strpos($doc['file_type'], 'pdf') !== false): ?>
                <iframe src="<?= $doc['file_url'] ?>" width="100%" height="600px" class="pdf-preview"></iframe>
            <?php else: ?>
                <p class="text-muted">Preview not available for this file type. <a href="<?= $doc['file_url'] ?>" target="_blank">Download to view</a>.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$doc['verified_at'] && !$doc['deleted_at']): ?>
    <div class="dashboard-card actions">
        <h3>Moderation Actions</h3>
        <div class="button-group">
            <a href="attachments_moderation.php?action=verify&id=<?= $id ?>" class="btn btn-success btn-large" onclick="return confirm('Verify this document?')"><i class="fas fa-check"></i> Verify Document</a>
            <button class="btn btn-danger btn-large" onclick="showRejectModal(<?= $id ?>, '<?= htmlspecialchars(addslashes($doc['filename'])) ?>')"><i class="fas fa-times"></i> Reject Document</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function showRejectModal(id, name) {
        document.getElementById('rejectDocId').value = id;
        document.getElementById('rejectDocName').innerText = name;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    </script>

    <!-- Reject Modal (reused) -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Document</h3>
                <button class="close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <form method="POST" action="attachments_moderation.php?action=reject" id="rejectForm">
                <input type="hidden" name="id" id="rejectDocId">
                <div class="modal-body">
                    <p>Rejecting: <strong id="rejectDocName"></strong></p>
                    <div class="form-group">
                        <label for="reason">Reason for rejection *</label>
                        <textarea name="reason" id="reason" rows="3" class="form-control" required placeholder="Explain why this document is being rejected..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_student" value="1" checked> Notify student via email
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Reject Document</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function getFileIcon($type) {
    if (strpos($type, 'pdf') !== false) return 'pdf';
    if (strpos($type, 'word') !== false || strpos($type, 'doc') !== false) return 'word';
    if (strpos($type, 'image') !== false) return 'image';
    if (strpos($type, 'text') !== false) return 'alt';
    return 'file';
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
/* Modern styling for moderation page */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}

.stats-badges {
    display: flex;
    gap: 0.75rem;
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 1rem;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 500;
    background: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

.stat-badge.pending { background: #fff3cd; color: #856404; }
.stat-badge.verified { background: #d4edda; color: #155724; }
.stat-badge.rejected { background: #f8d7da; color: #721c24; }

.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-header {
    padding: 1.5rem 1.5rem 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
}
.card-body {
    padding: 1.5rem;
}

.filter-form {
    width: 100%;
}
.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
}
.filter-group {
    flex: 1 1 140px;
    min-width: 130px;
}
.filter-group label {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: #4a5568;
}
.filter-group input, .filter-group select {
    width: 100%;
    padding: 0.4rem 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
}
.filter-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

/* Document list with scroll */
.document-list {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.modern-table {
    min-width: 1000px;
    width: 100%;
    border-collapse: collapse;
}
.modern-table th {
    text-align: left;
    padding: 0.75rem 0.5rem;
    color: #718096;
    font-weight: 500;
    border-bottom: 2px solid #e2e8f0;
}
.modern-table td {
    padding: 0.6rem 0.5rem;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.file-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.file-icon {
    font-size: 1.5rem;
    color: #3498db;
}
.file-name {
    color: #2c3e50;
    text-decoration: none;
    font-weight: 500;
}
.file-name:hover {
    text-decoration: underline;
}

.user-info {
    display: flex;
    flex-direction: column;
}
.user-info small {
    color: #95a5a6;
    font-size: 0.7rem;
}

.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.type-badge {
    background: #e9ecef;
    color: #2c3e50;
}
.usage-badge {
    background: #e3f2fd;
    color: #1976d2;
}

.date-info {
    display: flex;
    flex-direction: column;
}
.date-info small {
    color: #95a5a6;
    font-size: 0.7rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-badge.verified { background: #d4edda; color: #155724; }
.status-badge.rejected { background: #f8d7da; color: #721c24; }
.status-badge.pending { background: #fff3cd; color: #856404; }

.action-buttons {
    display: flex;
    gap: 0.3rem;
}
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 1px solid #e2e8f0;
    color: #4a5568;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-icon:hover {
    background: #f8fafc;
    border-color: #b0c4de;
}
.btn-icon.success:hover { background: #d4edda; color: #155724; border-color: #a3d0a3; }
.btn-icon.danger:hover { background: #f8d7da; color: #721c24; border-color: #f5baba; }
.btn-icon.info:hover { background: #d1ecf1; color: #0c5460; border-color: #a6d5e8; }

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #95a5a6;
}
.empty-state i {
    margin-bottom: 1rem;
    color: #cbd5e0;
}
.empty-state h4 {
    margin-bottom: 0.5rem;
    color: #2c3e50;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: white;
    max-width: 500px;
    width: 90%;
    border-radius: 24px;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
    animation: modalSlide 0.3s ease;
}
@keyframes modalSlide {
    from { transform: translateY(-40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 {
    margin: 0;
}
.close {
    background: none;
    border: none;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    color: #a0aec0;
}
.close:hover { color: #718096; }
.modal-body {
    padding: 2rem;
}
.modal-footer {
    padding: 1rem 2rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* Detail view specific */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}
.dashboard-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    padding: 1.5rem;
}
.info-table {
    width: 100%;
    border-collapse: collapse;
}
.info-table td {
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}
.info-table tr:last-child td { border-bottom: none; }

.stats-grid.small {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}
.stat-card {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
}
.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}
.stat-content h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-content p {
    margin: 0;
    font-size: 0.7rem;
    color: #7f8c8d;
}

.preview-container {
    text-align: center;
    margin-top: 1rem;
}
.img-preview {
    max-width: 100%;
    max-height: 400px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.pdf-preview {
    border: none;
    border-radius: 12px;
    background: #f8fafc;
}
.button-group {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1rem;
}
.btn-large {
    padding: 0.75rem 2rem;
    border-radius: 40px;
    font-size: 1rem;
}
.btn-success {
    background: linear-gradient(145deg, #27ae60, #2ecc71);
    color: white;
    border: none;
}
.btn-danger {
    background: linear-gradient(145deg, #e74c3c, #c0392b);
    color: white;
    border: none;
}

@media (max-width: 768px) {
    .filter-row { flex-direction: column; align-items: stretch; }
    .filter-group { width: 100%; }
    .stats-grid.small { grid-template-columns: repeat(2, 1fr); }
    .dashboard-grid { grid-template-columns: 1fr; }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>