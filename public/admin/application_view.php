<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Application Details";
require_once '../../app/templates/header.php';

$db = new Database();
$auth = new Auth();
$current_user = $auth->getCurrentUser();

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$application = $db->fetchOne("
    SELECT a.*, 
           p.program_name, p.department, p.tuition_fee, p.currency,
           p.application_deadline, p.duration_months, p.intake_months,
           u.name as university_name, u.country_code as uni_country,
           u.city as uni_city, u.website as uni_website,
           s.name as status_name, st.name as stage_name,
           us.username as student_username, us.email as student_email,
           up.first_name, up.last_name, up.profile_photo_url,
           st2.student_id,
           admin.username as decision_by_name
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    JOIN application_statuses s ON a.application_status_id = s.application_status_id
    LEFT JOIN application_stages st ON a.application_stage_id = st.application_stage_id
    JOIN students st2 ON a.student_id = st2.student_id
    JOIN users us ON st2.user_id = us.user_id
    LEFT JOIN user_profiles up ON us.user_id = up.user_id
    LEFT JOIN users admin ON a.decision_by = admin.user_id
    WHERE a.application_id = ? AND a.deleted_at IS NULL
", [$application_id]);

if (!$application) {
    echo "<div class='alert alert-error'>Application not found.</div>";
    echo "<a href='applications.php' class='btn btn-secondary'>Back to Applications</a>";
    require_once '../../app/templates/footer.php';
    exit();
}
$requirements = $db->fetchAll("
    SELECT prd.document_type, prd.description, prd.is_mandatory, pr.created_at
    FROM program_requirements pr
    JOIN program_requirement_docs prd ON pr.req_id = prd.req_id
    WHERE pr.program_id = ? AND pr.requirement_type = 'document'
    ORDER BY prd.is_mandatory DESC, prd.document_type
", [$application['program_id']]);
$scholarship_requirements = [];
$scholarships = $db->fetchAll("
    SELECT s.*, ss.status as student_status
    FROM student_scholarships ss
    JOIN scholarships s ON ss.scholarship_id = s.scholarship_id
    WHERE ss.student_id = ? AND ss.scholarship_id IN (
        SELECT scholarship_id FROM scholarships WHERE program_id = ?
    )
    ORDER BY s.name
", [$application['student_id'], $application['program_id']]);

foreach ($scholarships as $scholarship) {
    $schol_reqs = $db->fetchAll("
        SELECT * FROM scholarship_document_requirements 
        WHERE scholarship_id = ?
        ORDER BY document_type
    ", [$scholarship['scholarship_id']]);
    
    $scholarship_requirements = array_merge($scholarship_requirements, $schol_reqs);
}
$all_requirements = array_merge($requirements, $scholarship_requirements);
$uploaded_docs = $db->fetchAll("
    SELECT aa.*, a.filename, a.file_url, a.file_type, a.file_size,
           a.verified_at, a.verified_by_admin_id,
           av.status as verification_status, av.admin_comment,
           verifier.username as verified_by_name
    FROM application_attachments aa
    JOIN attachments a ON aa.attachment_id = a.attachment_id
    LEFT JOIN attachment_verifications av ON a.attachment_id = av.attachment_id
    LEFT JOIN users verifier ON a.verified_by_admin_id = verifier.user_id
    WHERE aa.application_id = ? AND a.deleted_at IS NULL
    ORDER BY aa.required_for
", [$application_id]);
$requirements_status = $db->fetchAll("
    SELECT * FROM application_requirements_status 
    WHERE application_id = ?
    ORDER BY requirement_type
", [$application_id]);
$reviews = $db->fetchAll("
    SELECT ar.*, u.username as admin_name
    FROM application_reviews ar
    LEFT JOIN users u ON ar.admin_id = u.user_id
    WHERE ar.application_id = ?
    ORDER BY ar.reviewed_at DESC
", [$application_id]);
$events = $db->fetchAll("
    SELECT ae.*, u.username as actor_name
    FROM application_events ae
    LEFT JOIN users u ON ae.actor_user_id = u.user_id
    WHERE ae.application_id = ?
    ORDER BY ae.created_at DESC
", [$application_id]);
$test_scores = $db->fetchAll("
    SELECT ts.*, tt.name as test_name,
           (SELECT COUNT(*) FROM test_score_documents tsd 
            WHERE tsd.score_id = ts.score_id) as document_count
    FROM test_scores ts
    JOIN test_types tt ON ts.test_type_id = tt.test_type_id
    WHERE ts.student_id = ?
    ORDER BY ts.test_date DESC
", [$application['student_id']]);
$education = $db->fetchAll("
    SELECT er.*, c.name as country_name,
           (SELECT COUNT(*) FROM education_documents ed 
            WHERE ed.education_record_id = er.record_id) as document_count
    FROM education_records er
    LEFT JOIN countries c ON er.country_code = c.iso2
    WHERE er.student_id = ? AND er.deleted_at IS NULL
    ORDER BY er.end_date DESC
", [$application['student_id']]);
$recommendations = $db->fetchAll("
    SELECT r.*, rec.name as recommender_name, rec.email,
           rec.organization, rec.relationship
    FROM recommendations r
    JOIN recommenders rec ON r.recommender_id = rec.recommender_id
    WHERE r.application_id = ? OR (r.application_id IS NULL AND rec.student_id = ?)
    ORDER BY r.submitted DESC, r.created_at DESC
", [$application_id, $application['student_id']]);

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status_id = intval($_POST['application_status_id']);
        $new_stage_id = $_POST['application_stage_id'] ? intval($_POST['application_stage_id']) : null;
        $status_reason = Helpers::sanitize($_POST['status_reason']);
        $admin_notes = Helpers::sanitize($_POST['admin_notes']);
        $decision_date = $_POST['decision_date'] ?: null;
        
        $update_data = [
            'application_status_id' => $new_status_id,
            'application_stage_id' => $new_stage_id,
            'status_reason' => $status_reason,
            'admin_notes' => $admin_notes,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $current_user['user_id']
        ];
        
        if ($_POST['decision_by']) {
            $update_data['decision_by'] = intval($_POST['decision_by']);
        }
        
        if ($decision_date) {
            $update_data['decision_date'] = $decision_date;
        }
        
        if ($new_status_id == 5) { 
            $update_data['decision_date'] = date('Y-m-d H:i:s');
            if (!$update_data['decision_by']) {
                $update_data['decision_by'] = $current_user['user_id'];
            }
        }
        
        $db->update('applications', $update_data, "application_id = $application_id");
        $db->insert('application_events', [
            'application_id' => $application_id,
            'actor_user_id' => $current_user['user_id'],
            'event_type' => 'status_changed',
            'event_data' => json_encode([
                'old_status' => $application['application_status_id'],
                'new_status' => $new_status_id,
                'reason' => $status_reason,
                'admin_notes' => $admin_notes
            ]),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        // Get student email via student_id -> user_id
        $student_info = $db->fetchOne("SELECT user_id FROM students WHERE student_id = ?", [$application['student_id']]);
        if ($student_info) {
            $student_user = $db->fetchOne("
                SELECT u.email, u.username, up.first_name, up.last_name 
                FROM users u 
                LEFT JOIN user_profiles up ON u.user_id = up.user_id 
                WHERE u.user_id = ?", [$student_info['user_id']]);
            if ($student_user) {
                $student_name = $student_user['first_name'] ?: $student_user['username'];
                $mailer = new MailHelper();
                $status_name = $db->fetchOne("SELECT name FROM application_statuses WHERE application_status_id = ?", [$new_status_id])['name'];
                $mailer->sendTemplate('application_status_changed', $student_user['email'], $student_name, [
                    'student_name' => $student_name,
                    'program_name' => $application['program_name'],
                    'university_name' => $application['university_name'],
                    'application_id' => $application_id,
                    'status' => $status_name,
                    'status_reason' => $status_reason ?: 'No additional information'
                ]);
            }
        }
        
        $success = 'Application status updated successfully!';
        $application = $db->fetchOne("
            SELECT a.*, 
                   p.program_name, p.department, p.tuition_fee, p.currency,
                   p.application_deadline, p.duration_months, p.intake_months,
                   u.name as university_name, u.country_code as uni_country,
                   u.city as uni_city, u.website as uni_website,
                   s.name as status_name, st.name as stage_name,
                   us.username as student_username, us.email as student_email,
                   up.first_name, up.last_name, up.profile_photo_url,
                   st2.student_id,
                   admin.username as decision_by_name
            FROM applications a
            JOIN programs p ON a.program_id = p.program_id
            JOIN universities u ON a.university_id = u.university_id
            JOIN application_statuses s ON a.application_status_id = s.application_status_id
            LEFT JOIN application_stages st ON a.application_stage_id = st.application_stage_id
            JOIN students st2 ON a.student_id = st2.student_id
            JOIN users us ON st2.user_id = us.user_id
            LEFT JOIN user_profiles up ON us.user_id = up.user_id
            LEFT JOIN users admin ON a.decision_by = admin.user_id
            WHERE a.application_id = ? AND a.deleted_at IS NULL
        ", [$application_id]);
    }
    
    if (isset($_POST['add_review'])) {
        $review_status = Helpers::sanitize($_POST['review_status']);
        $review_comment = Helpers::sanitize($_POST['review_comment']);
        
        $db->insert('application_reviews', [
            'application_id' => $application_id,
            'admin_id' => $current_user['user_id'],
            'review_status' => $review_status,
            'review_comment' => $review_comment,
            'reviewed_at' => date('Y-m-d H:i:s')
        ]);
        
        $success = 'Review added successfully!';
        $reviews = $db->fetchAll("
            SELECT ar.*, u.username as admin_name
            FROM application_reviews ar
            LEFT JOIN users u ON ar.admin_id = u.user_id
            WHERE ar.application_id = ?
            ORDER BY ar.reviewed_at DESC
        ", [$application_id]);
    }
    
    if (isset($_POST['update_requirement_status'])) {
        $requirement_type = Helpers::sanitize($_POST['requirement_type']);
        $requirement_id = $_POST['requirement_id'] ? intval($_POST['requirement_id']) : null;
        $status = Helpers::sanitize($_POST['status']);
        $admin_comment = Helpers::sanitize($_POST['admin_comment']);
        $existing = $db->fetchOne("
            SELECT * FROM application_requirements_status 
            WHERE application_id = ? AND requirement_type = ? 
            AND (requirement_id = ? OR (? IS NULL AND requirement_id IS NULL))
        ", [$application_id, $requirement_type, $requirement_id, $requirement_id]);
        
        if ($existing) {
            $db->update('application_requirements_status', [
                'status' => $status,
                'admin_comment' => $admin_comment,
                'admin_id' => $current_user['user_id'],
                'verified_at' => date('Y-m-d H:i:s')
            ], "status_id = " . $existing['status_id']);
        } else {
            $db->insert('application_requirements_status', [
                'application_id' => $application_id,
                'requirement_type' => $requirement_type,
                'requirement_id' => $requirement_id,
                'admin_id' => $current_user['user_id'],
                'status' => $status,
                'admin_comment' => $admin_comment,
                'verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        $success = 'Requirement status updated!';
        $requirements_status = $db->fetchAll("
            SELECT * FROM application_requirements_status 
            WHERE application_id = ?
            ORDER BY requirement_type
        ", [$application_id]);
    }
}
$statuses = $db->fetchAll("SELECT * FROM application_statuses ORDER BY name");
$stages = $db->fetchAll("SELECT * FROM application_stages ORDER BY order_index");
$admins = $db->fetchAll("SELECT user_id, username FROM users WHERE default_role_id IN (2,4) ORDER BY username");
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <h1>Application #<?= $application['application_id'] ?></h1>
            <p class="lead">
                <i class="fas fa-user-graduate"></i> 
                <?= htmlspecialchars($application['first_name'] ?? '') ?> 
                <?= htmlspecialchars($application['last_name'] ?? '') ?>
                (<?= $application['student_username'] ?>)
            </p>
            <p>
                <i class="fas fa-university"></i> 
                <strong><?= $application['program_name'] ?></strong> at 
                <?= $application['university_name'] ?>
            </p>
        </div>
        <div class="text-right">
            <span class="status-badge status-<?= strtolower($application['status_name']) ?>">
                <?= $application['status_name'] ?>
            </span>
            <?php if ($application['stage_name']): ?>
                <br><small class="stage-label">Stage: <?= $application['stage_name'] ?></small>
            <?php endif; ?>
        </div>
    </div>
    <div class="header-actions">
        <a href="applications.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Applications
        </a>
        <a href="mailto:<?= $application['student_email'] ?>" class="btn btn-outline-primary">
            <i class="fas fa-envelope"></i> Email Student
        </a>
        <button class="btn btn-primary" data-toggle="modal" data-target="#updateStatusModal">
            <i class="fas fa-edit"></i> Update Status
        </button>
    </div>
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
    <div class="col-md-8">
        <!-- Required Documents card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt"></i> Required Documents & Status
                </h3>
                <small>Click the edit button to update verification status</small>
            </div>
            <div class="card-body">
                <?php if (empty($all_requirements)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No specific document requirements have been set for this program.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th>Required</th>
                                    <th>Uploaded</th>
                                    <th>Verification</th>
                                    <th>Admin Comment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_requirements as $req): 
                                    $uploaded = null;
                                    $verification_status = 'pending';
                                    $admin_comment = '';
                                    foreach ($uploaded_docs as $doc) {
                                        if ($doc['required_for'] == $req['document_type']) {
                                            $uploaded = $doc;
                                            $verification_status = $doc['verification_status'] ?? 'pending';
                                            $admin_comment = $doc['admin_comment'] ?? '';
                                            break;
                                        }
                                    }
                                    $req_status = null;
                                    foreach ($requirements_status as $rs) {
                                        if ($rs['requirement_type'] == 'document' && 
                                            strpos($rs['admin_comment'] ?? '', $req['document_type']) !== false) {
                                            $req_status = $rs;
                                            break;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($req['document_type']) ?></strong>
                                        <?php if (!empty($req['description'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($req['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($req['is_mandatory']): ?>
                                            <span class="badge badge-danger">Required</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Optional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($uploaded): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> Yes
                                            </span>
                                            <br>
                                            <small>
                                                <a href="<?= $uploaded['file_url'] ?>" target="_blank">
                                                    <?= htmlspecialchars(substr($uploaded['filename'], 0, 20)) ?>…
                                                </a>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-times"></i> No
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'secondary';
                                        $status_text = 'Pending';
                                        if ($verification_status == 'verified') {
                                            $badge_class = 'success';
                                            $status_text = 'Verified';
                                        } elseif ($verification_status == 'rejected') {
                                            $badge_class = 'danger';
                                            $status_text = 'Rejected';
                                        } elseif ($verification_status == 'needs_correction') {
                                            $badge_class = 'warning';
                                            $status_text = 'Needs Correction';
                                        }
                                        ?>
                                        <span class="badge badge-<?= $badge_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <?= htmlspecialchars(substr($admin_comment, 0, 50)) ?>
                                        <?php if (strlen($admin_comment) > 50): ?>…<?php endif; ?>
                                        <?php if ($req_status && $req_status['admin_comment']): ?>
                                            <br><small class="text-info"><?= substr($req_status['admin_comment'], 0, 30) ?>…</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($uploaded): ?>
                                                <a href="attachments_moderation.php?action=view&id=<?= $uploaded['attachment_id'] ?>" 
                                                   class="btn btn-outline-primary" target="_blank" title="View file">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    data-toggle="modal" data-target="#updateRequirementModal"
                                                    data-document-type="<?= htmlspecialchars($req['document_type']) ?>"
                                                    data-current-status="<?= $verification_status ?>"
                                                    data-current-comment="<?= htmlspecialchars($admin_comment) ?>"
                                                    title="Update status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Upload progress summary -->
                <div class="mt-4">
                    <h5>Document Upload Summary</h5>
                    <?php
                    $total_docs = count($all_requirements);
                    $uploaded_count = 0;
                    $verified_count = 0;
                    foreach ($all_requirements as $req) {
                        foreach ($uploaded_docs as $doc) {
                            if ($doc['required_for'] == $req['document_type']) {
                                $uploaded_count++;
                                if (($doc['verification_status'] ?? '') == 'verified') {
                                    $verified_count++;
                                }
                                break;
                            }
                        }
                    }
                    $upload_pct = $total_docs > 0 ? round(($uploaded_count / $total_docs) * 100) : 0;
                    $verify_pct = $uploaded_count > 0 ? round(($verified_count / $uploaded_count) * 100) : 0;
                    ?>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?= $upload_pct ?>%;" 
                             aria-valuenow="<?= $upload_pct ?>" aria-valuemin="0" aria-valuemax="100">
                            <?= $uploaded_count ?>/<?= $total_docs ?> Uploaded
                        </div>
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: <?= $verify_pct ?>%;" 
                             aria-valuenow="<?= $verify_pct ?>" aria-valuemin="0" aria-valuemax="100">
                            <?= $verified_count ?>/<?= $uploaded_count ?> Verified
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <span class="badge badge-success">■</span> Uploaded | 
                            <span class="badge badge-info">■</span> Verified
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application Reviews -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-comments"></i> Application Reviews
                </h3>
                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addReviewModal">
                    <i class="fas fa-plus"></i> Add Review
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($reviews)): ?>
                    <p class="text-muted">No reviews yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($reviews as $review): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker" style="background: <?php
                                    if ($review['review_status'] == 'approved') echo '#28a745';
                                    elseif ($review['review_status'] == 'rejected') echo '#dc3545';
                                    else echo '#ffc107';
                                ?>;"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= $review['admin_name'] ?: 'System' ?></strong>
                                        <small class="text-muted"><?= Helpers::formatDate($review['reviewed_at']) ?></small>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge badge-<?= 
                                            $review['review_status'] == 'approved' ? 'success' : 
                                            ($review['review_status'] == 'rejected' ? 'danger' : 'warning') 
                                        ?>">
                                            <?= ucfirst($review['review_status']) ?>
                                        </span>
                                    </div>
                                    <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($review['review_comment'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Application History -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Application History</h3>
            </div>
            <div class="card-body">
                <?php if (empty($events)): ?>
                    <p class="text-muted">No history recorded.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($events as $event): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= ucfirst(str_replace('_', ' ', $event['event_type'])) ?></strong>
                                        <small class="text-muted"><?= Helpers::formatDate($event['created_at']) ?></small>
                                    </div>
                                    <div class="mt-1">
                                        <small class="text-muted">
                                            By: <?= $event['actor_name'] ?: 'System' ?>
                                        </small>
                                    </div>
                                    <?php if ($event['event_data']): 
                                        $data = json_decode($event['event_data'], true);
                                        if ($data && is_array($data)): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <?php foreach ($data as $key => $value): ?>
                                                    <div><small><strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong> <?= htmlspecialchars($value) ?></small></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Application Details card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle"></i> Application Details</h3>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><td><strong>Application ID:</strong></td><td>#<?= $application['application_id'] ?></td></tr>
                    <tr><td><strong>Student:</strong></td><td><?= $application['first_name'] ?> <?= $application['last_name'] ?></td></tr>
                    <tr><td><strong>Email:</strong></td><td><?= $application['student_email'] ?></td></tr>
                    <tr><td><strong>Program:</strong></td><td><?= $application['program_name'] ?></td></tr>
                    <tr><td><strong>University:</strong></td><td><?= $application['university_name'] ?></td></tr>
                    <tr><td><strong>Department:</strong></td><td><?= $application['department'] ?: 'N/A' ?></td></tr>
                    <tr><td><strong>Applied Date:</strong></td><td><?= Helpers::formatDate($application['applied_at']) ?></td></tr>
                    <tr><td><strong>Submitted Date:</strong></td><td><?= $application['submitted_at'] ? Helpers::formatDate($application['submitted_at']) : 'Not submitted' ?></td></tr>
                    <tr><td><strong>Deadline:</strong></td><td><?= $application['deadline'] ? Helpers::formatDate($application['deadline']) : 'N/A' ?></td></tr>
                    <tr><td><strong>Tuition:</strong></td><td><?= $application['tuition_fee'] ? number_format($application['tuition_fee'], 2) . ' ' . $application['currency'] : 'N/A' ?></td></tr>
                    <tr><td><strong>Decision Date:</strong></td><td><?= $application['decision_date'] ? Helpers::formatDate($application['decision_date']) : 'Pending' ?></td></tr>
                    <tr><td><strong>Decision By:</strong></td><td><?= $application['decision_by_name'] ?: 'Not assigned' ?></td></tr>
                </table>
                
                <?php if ($application['admin_notes']): ?>
                    <div class="mt-3">
                        <h6>Admin Notes:</h6>
                        <div class="p-3 bg-light rounded">
                            <?= nl2br(htmlspecialchars($application['admin_notes'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($application['status_reason']): ?>
                    <div class="mt-3">
                        <h6>Status Reason:</h6>
                        <div class="p-3 bg-light rounded">
                            <?= htmlspecialchars($application['status_reason']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Student Information card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-graduate"></i> Student Information</h3>
                <a href="../admin/user_edit.php?id=<?= $application['student_id'] ?>" 
                   class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if ($application['profile_photo_url']): ?>
                        <img src="<?= $application['profile_photo_url'] ?>" 
                             class="rounded-circle" width="80" height="80" 
                             alt="Profile Photo">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-user text-white fa-2x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h5 class="text-center">
                    <?= $application['first_name'] ?> <?= $application['last_name'] ?>
                </h5>
                <p class="text-center text-muted"><?= $application['student_username'] ?></p>
                
                <div class="student-stats mt-3">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="stat-box">
                                <h4><?= count($test_scores) ?></h4>
                                <small>Test Scores</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <h4><?= count($education) ?></h4>
                                <small>Education Records</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="?id=<?= $application_id ?>&tab=documents" class="btn btn-sm btn-outline-secondary btn-block">
                        <i class="fas fa-folder-open"></i> View All Documents
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="mailto:<?= $application['student_email'] ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-envelope text-primary"></i> Send Email to Student
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#updateStatusModal">
                        <i class="fas fa-edit text-warning"></i> Update Application Status
                    </a>
                    <a href="../admin/attachments_moderation.php?search=<?= urlencode($application['student_username']) ?>" 
                       class="list-group-item list-group-item-action" target="_blank">
                        <i class="fas fa-file-check text-success"></i> Moderate Student's Documents
                    </a>
                    <a href="../admin/user_edit.php?id=<?= $application['student_id'] ?>" 
                       class="list-group-item list-group-item-action" target="_blank">
                        <i class="fas fa-user-edit text-info"></i> Edit Student Profile
                    </a>
                    <?php if ($application['status_name'] == 'Draft'): ?>
                        <button class="list-group-item list-group-item-action text-danger" 
                                onclick="if(confirm('Delete this application?')) window.location='applications.php?action=delete&id=<?= $application_id ?>'">
                            <i class="fas fa-trash"></i> Delete Application
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Update Application Status</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="application_status_id">Status</label>
                        <select id="application_status_id" name="application_status_id" class="form-control" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status['application_status_id'] ?>" 
                                    <?= $application['application_status_id'] == $status['application_status_id'] ? 'selected' : '' ?>>
                                    <?= $status['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="application_stage_id">Stage</label>
                        <select id="application_stage_id" name="application_stage_id" class="form-control">
                            <option value="">Select Stage</option>
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?= $stage['application_stage_id'] ?>" 
                                    <?= $application['application_stage_id'] == $stage['application_stage_id'] ? 'selected' : '' ?>>
                                    <?= $stage['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="decision_by">Decision By</label>
                        <select id="decision_by" name="decision_by" class="form-control">
                            <option value="">Select Admin</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?= $admin['user_id'] ?>" 
                                    <?= $application['decision_by'] == $admin['user_id'] ? 'selected' : '' ?>>
                                    <?= $admin['username'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="decision_date">Decision Date</label>
                        <input type="date" id="decision_date" name="decision_date" class="form-control"
                               value="<?= $application['decision_date'] ? date('Y-m-d', strtotime($application['decision_date'])) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status_reason">Status Reason</label>
                        <input type="text" id="status_reason" name="status_reason" class="form-control"
                               placeholder="Reason for status change"
                               value="<?= htmlspecialchars($application['status_reason'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea id="admin_notes" name="admin_notes" class="form-control" rows="3"
                                  placeholder="Internal notes about this application"><?= htmlspecialchars($application['admin_notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addReviewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Application Review</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="review_status">Review Status</label>
                        <select id="review_status" name="review_status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="needs_correction">Needs Correction</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="review_comment">Review Comment</label>
                        <textarea id="review_comment" name="review_comment" class="form-control" rows="4" required
                                  placeholder="Enter your review comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_review" class="btn btn-primary">Add Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="updateRequirementModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" id="modal_requirement_type" name="requirement_type">
                <input type="hidden" id="modal_requirement_id" name="requirement_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Document Requirement Status</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_document_type">Document Type</label>
                        <input type="text" id="modal_document_type" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_status">Status</label>
                        <select id="modal_status" name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                            <option value="needs_correction">Needs Correction</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_admin_comment">Admin Comment</label>
                        <textarea id="modal_admin_comment" name="admin_comment" class="form-control" rows="4"
                                  placeholder="Enter comments about this document requirement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_requirement_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Modern card styling */
.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s;
}
.card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.card-header {
    background: white;
    border-bottom: 1px solid #eef2f6;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-radius: 16px 16px 0 0 !important;
}
.card-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
}
.card-body {
    padding: 1.25rem;
}

/* Status badge */
.status-badge {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 1rem;
}
.status-draft { background: #ffc107; color: #333; }
.status-submitted { background: #007bff; color: white; }
.status-under_review { background: #17a2b8; color: white; }
.status-accepted { background: #28a745; color: white; }
.status-rejected { background: #dc3545; color: white; }
.status-withdrawn { background: #6c757d; color: white; }
.status-enrolled { background: #6610f2; color: white; }
.stage-label {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Header actions */
.header-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}
.btn-outline-secondary, .btn-outline-primary {
    border-radius: 30px;
    padding: 0.4rem 1.2rem;
}
.btn-primary {
    border-radius: 30px;
    padding: 0.4rem 1.2rem;
}

/* Table */
.table {
    width: 100%;
    margin-bottom: 0;
}
.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}
.table td {
    vertical-align: middle;
}
.badge {
    padding: 0.4rem 0.6rem;
    border-radius: 30px;
    font-weight: 500;
}
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-success { background: #d4edda; color: #155724; }
.badge-secondary { background: #e2e3e5; color: #383d41; }
.badge-info { background: #d1ecf1; color: #0c5460; }

/* Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    top: 5px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.timeline-content {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    border: 1px solid #e9ecef;
}

/* Progress bar */
.progress {
    display: flex;
    height: 25px;
    border-radius: 30px;
    overflow: hidden;
    background: #e9ecef;
}
.progress-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.85rem;
    font-weight: 500;
}
.progress-bar.bg-success { background: #28a745; }
.progress-bar.bg-info { background: #17a2b8; }

/* Student stats */
.stat-box {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 12px;
}
.stat-box h4 {
    margin: 0;
    color: #007bff;
    font-weight: bold;
}
.stat-box small {
    color: #6c757d;
}

/* List group */
.list-group-item {
    border-left: none;
    border-right: none;
    padding: 0.75rem 1.25rem;
}
.list-group-item:first-child {
    border-top: none;
}
.list-group-item:last-child {
    border-bottom: none;
}
.list-group-item-action:hover {
    background: #f8f9fa;
}

/* Alerts */
.alert {
    border-radius: 12px;
    border: none;
}
.alert-success {
    background: #d4edda;
    color: #155724;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
}

/* Responsive */
@media (max-width: 768px) {
    .col-md-8, .col-md-4 {
        width: 100%;
    }
}
</style>

<script>
$(document).ready(function() {
    $('#updateRequirementModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var documentType = button.data('document-type');
        var currentStatus = button.data('current-status');
        var currentComment = button.data('current-comment');
        
        var modal = $(this);
        modal.find('#modal_document_type').val(documentType);
        modal.find('#modal_requirement_type').val('document');
        modal.find('#modal_status').val(currentStatus || 'pending');
        modal.find('#modal_admin_comment').val(currentComment || '');
    });
    
    $('#application_status_id').change(function() {
        if ($(this).val() == '5') { 
            var today = new Date().toISOString().split('T')[0];
            $('#decision_date').val(today);
            if (!$('#decision_by').val()) {
                $('#decision_by').val('<?= $current_user["user_id"] ?>');
            }
        }
    });
});
</script>

<?php require_once '../../app/templates/footer.php'; ?>