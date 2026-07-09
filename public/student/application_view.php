<?php
require_once '../../app/includes/init.php';
requireStudent();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: applications.php');
    exit();
}

$application_id = intval($_GET['id']);
$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();
$upload_helper = new UploadHelper();

$application = $db->fetchOne(
    "SELECT a.*, p.program_name, p.department, p.tuition_fee, p.currency,
            p.application_deadline, p.duration_months, p.intake_months,
            u.name as university_name, u.country_code as uni_country,
            u.city as uni_city, u.website as uni_website,
            s.name as status_name, st.name as stage_name
     FROM applications a
     JOIN programs p ON a.program_id = p.program_id
     JOIN universities u ON a.university_id = u.university_id
     JOIN application_statuses s ON a.application_status_id = s.application_status_id
     LEFT JOIN application_stages st ON a.application_stage_id = st.application_stage_id
     WHERE a.application_id = ? AND a.student_id = ? AND a.deleted_at IS NULL",
    [$application_id, $student_id]
);

if (!$application) {
    header('Location: applications.php');
    exit();
}

$page_title = "Application: " . $application['program_name'];
require_once '../../app/templates/header.php';

// DEBUG: Check what program we're looking at
// echo "DEBUG: Program ID: " . $application['program_id'] . "<br>";

// Get program requirements from the correct tables - FIXED QUERY
$requirements = $db->fetchAll(
    "SELECT prd.document_type, prd.description, prd.is_mandatory, pr.created_at
     FROM program_requirements pr
     JOIN program_requirement_docs prd ON pr.req_id = prd.req_id
     WHERE pr.program_id = ? AND pr.requirement_type = 'document'
     ORDER BY prd.is_mandatory DESC, prd.document_type",
    [$application['program_id']]
);

// DEBUG: Check what requirements were found
// echo "DEBUG: Found " . count($requirements) . " requirements<br>";
// foreach ($requirements as $req) {
//     echo "DEBUG: Requirement: " . $req['document_type'] . " (Mandatory: " . $req['is_mandatory'] . ")<br>";
// }

// Also get test score requirements
$test_requirements = $db->fetchAll(
    "SELECT prt.*, tt.name as test_name
     FROM program_requirement_tests prt
     JOIN program_requirements pr ON prt.req_id = pr.req_id
     JOIN test_types tt ON prt.test_type_id = tt.test_type_id
     WHERE pr.program_id = ?
     ORDER BY prt.is_mandatory DESC, tt.name",
    [$application['program_id']]
);

$uploaded_docs = $db->fetchAll(
    "SELECT aa.*, a.filename, a.file_url, a.file_type, a.file_size
     FROM application_attachments aa
     JOIN attachments a ON aa.attachment_id = a.attachment_id
     WHERE aa.application_id = ? AND a.deleted_at IS NULL",
    [$application_id]
);

$events = $db->fetchAll(
    "SELECT * FROM application_events 
     WHERE application_id = ? 
     ORDER BY created_at DESC",
    [$application_id]
);

$app_recommendations = $db->fetchAll(
    "SELECT r.*, rec.name as recommender_name, rec.email
     FROM recommendations r
     JOIN recommenders rec ON r.recommender_id = rec.recommender_id
     WHERE r.application_id = ? AND rec.student_id = ?
     ORDER BY r.submitted DESC",
    [$application_id, $student_id]
);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['application_doc']) && $_FILES['application_doc']['error'] == 0) {
        $required_for = Helpers::sanitize($_POST['required_for']);
        
        $result = $upload_helper->uploadFile($_FILES['application_doc'], $user['user_id'], 'application');
        
        if (isset($result['success'])) {
            $db->insert('application_attachments', [
                'application_id' => $application_id,
                'attachment_id' => $result['attachment_id'],
                'required_for' => $required_for,
                'submitted' => 1,
                'uploaded_at' => date('Y-m-d H:i:s'),
                'created_by' => $user['user_id']
            ]);
            
            $success = 'Document uploaded successfully!';
            
            $uploaded_docs = $db->fetchAll(
                "SELECT aa.*, a.filename, a.file_url, a.file_type, a.file_size
                 FROM application_attachments aa
                 JOIN attachments a ON aa.attachment_id = a.attachment_id
                 WHERE aa.application_id = ? AND a.deleted_at IS NULL",
                [$application_id]
            );
        } else {
            $error = $result['error'];
        }
    }
    
    if (isset($_POST['submit_application'])) {
        $missing_mandatory = false;
        $missing_mandatory_docs = [];
        
        foreach ($requirements as $req) {
            if ($req['is_mandatory']) {
                $uploaded = false;
                foreach ($uploaded_docs as $doc) {
                    if ($doc['required_for'] == $req['document_type']) {
                        $uploaded = true;
                        break;
                    }
                }
                if (!$uploaded) {
                    $missing_mandatory = true;
                    $missing_mandatory_docs[] = $req['document_type'];
                }
            }
        }
        
        if ($missing_mandatory) {
            $error = 'Please upload all mandatory documents before submitting. Missing: ' . 
                     implode(', ', $missing_mandatory_docs);
        } else {
            $submitted_status = $db->fetchOne(
                "SELECT application_status_id FROM application_statuses WHERE code = 'submitted'"
            );
            
            if ($submitted_status) {
                $db->update('applications', [
                    'application_status_id' => $submitted_status['application_status_id'],
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ], "application_id = $application_id");
                
                $db->insert('application_events', [
                    'application_id' => $application_id,
                    'actor_user_id' => $user['user_id'],
                    'event_type' => 'submitted',
                    'event_data' => json_encode(['submitted_at' => date('Y-m-d H:i:s')]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $success = 'Application submitted successfully!';
                // After status update to 'submitted'
                $student = $db->fetchOne("SELECT u.email, u.username, up.first_name, up.last_name FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id WHERE u.user_id = ?", [$user['user_id']]);
                $student_name = $student['first_name'] ?: $student['username'];
                $mailer = new MailHelper();
                $mailer->sendTemplate('application_submitted', $student['email'], $student_name, [
                    'student_name' => $student_name,
                    'program_name' => $application['program_name'],
                    'university_name' => $application['university_name'],
                    'application_id' => $application_id
                ]);
                 Helpers::awardMilestone($student_id, 'application_submitted');              
                // Refresh application data
                $application = $db->fetchOne(
                    "SELECT a.*, p.program_name, p.department, p.tuition_fee, p.currency,
                            p.application_deadline, p.duration_months, p.intake_months,
                            u.name as university_name, u.country_code as uni_country,
                            u.city as uni_city, u.website as uni_website,
                            s.name as status_name, st.name as stage_name
                     FROM applications a
                     JOIN programs p ON a.program_id = p.program_id
                     JOIN universities u ON a.university_id = u.university_id
                     JOIN application_statuses s ON a.application_status_id = s.application_status_id
                     LEFT JOIN application_stages st ON a.application_stage_id = st.application_stage_id
                     WHERE a.application_id = ? AND a.student_id = ? AND a.deleted_at IS NULL",
                    [$application_id, $student_id]
                );
            }
        }
    }
}
?>

<div class="page-header">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1><?php echo $application['program_name']; ?></h1>
            <p>
                <i class="fas fa-university"></i> <?php echo $application['university_name']; ?>
                <?php if ($application['uni_city']): ?>
                    , <?php echo $application['uni_city']; ?>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <span class="status-badge status-<?php echo strtolower($application['status_name']); ?>" style="font-size:1.2rem;">
                <?php echo $application['status_name']; ?>
            </span>
        </div>
    </div>
    <div class="header-actions" style="margin-top: 10px;">
        <a href="generate_pdf.php?id=<?= $application['application_id'] ?>" class="btn btn-secondary" target="_blank">
            <i class="fas fa-file-pdf"></i> Download as PDF
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php
// Get scholarships for this program
$program_scholarships = $db->fetchAll("
    SELECT s.*, 
           (SELECT COUNT(*) FROM student_scholarships ss 
            WHERE ss.scholarship_id = s.scholarship_id 
            AND ss.student_id = ? AND ss.status != 'withdrawn') as has_applied
    FROM scholarships s
    WHERE s.program_id = ? AND (s.deadline IS NULL OR s.deadline >= CURDATE())
    ORDER BY s.amount DESC
    LIMIT 5
", [$student_id, $application['program_id']]);
?>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>Application Details</h3>
        <table class="table">
            <tr>
                <td><strong>Program:</strong></td>
                <td><?php echo $application['program_name']; ?></td>
            </tr>
            <tr>
                <td><strong>University:</strong></td>
                <td><?php echo $application['university_name']; ?></td>
            </tr>
            <tr>
                <td><strong>Department:</strong></td>
                <td><?php echo $application['department'] ?: 'N/A'; ?></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <span class="status-badge status-<?php echo strtolower($application['status_name']); ?>">
                        <?php echo $application['status_name']; ?>
                    </span>
                    <?php if ($application['stage_name']): ?>
                        <br><small>Stage: <?php echo $application['stage_name']; ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Applied Date:</strong></td>
                <td><?php echo Helpers::formatDate($application['applied_at']); ?></td>
            </tr>
            <tr>
                <td><strong>Submitted Date:</strong></td>
                <td>
                    <?php echo $application['submitted_at'] ? Helpers::formatDate($application['submitted_at']) : 'Not submitted'; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Application Deadline:</strong></td>
                <td>
                    <?php if ($application['application_deadline']): ?>
                        <?php echo Helpers::formatDate($application['application_deadline']); ?>
                        <?php if (strtotime($application['application_deadline']) < time()): ?>
                            <span class="badge badge-warning">Passed</span>
                        <?php endif; ?>
                    <?php else: ?>
                        Rolling Admission
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Tuition Fee:</strong></td>
                <td>
                    <?php if ($application['tuition_fee']): ?>
                        <?php echo number_format($application['tuition_fee'], 2); ?> 
                        <?php echo $application['currency']; ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Duration:</strong></td>
                <td>
                    <?php if ($application['duration_months']): ?>
                        <?php echo $application['duration_months']; ?> months
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Intake:</strong></td>
                <td><?php echo $application['intake_months'] ?: 'N/A'; ?></td>
            </tr>
        </table>
        
        <?php if ($application['status_name'] == 'Draft'): ?>
            <div style="margin-top:1rem;">
                <form method="POST" action="" onsubmit="return confirm('Submit this application? You cannot edit after submission.');">
                    <button type="submit" name="submit_application" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="dashboard-card">
    <h3>Required Documents & Tests</h3>

    <!-- Fee Payment Section -->
<?php
$fee = Helpers::getApplicationFee($application['program_id']);
$is_paid = Helpers::isFeePaid($application_id);
$payment = Helpers::getApplicationPayment($application_id);
?>

<div class="dashboard-card">
    <h3><i class="fas fa-credit-card"></i> Application Fee</h3>
    <?php if ($fee): ?>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Fee Amount:</strong> 
                <?= number_format($fee['amount'], 2) ?> <?= $fee['currency'] ?>
                <?php if ($fee['waiver_criteria']): ?>
                    <br><small class="text-muted">Waiver criteria available</small>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($is_paid): ?>
                    <span class="badge badge-success" style="background:#28a745;">
                        <i class="fas fa-check-circle"></i> Paid
                    </span>
                    <?php if ($payment): ?>
                        <br><small>Paid on <?= Helpers::formatDate($payment['paid_at']) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge badge-warning" style="background:#ffc107;">
                        <i class="fas fa-exclamation-triangle"></i> Not Paid
                    </span>
                    <?php if ($application['status_name'] == 'Draft' || $application['status_name'] == 'Submitted'): ?>
                        <a href="pay_fee.php?application_id=<?= $application_id ?>" class="btn btn-primary btn-small mt-2">
                            <i class="fas fa-credit-card"></i> Pay Now
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$is_paid && $fee['waiver_criteria']): ?>
            <div class="mt-2 p-2 bg-light">
                <strong>Fee Waiver Criteria:</strong>
                <?php
                $criteria = json_decode($fee['waiver_criteria'], true);
                if (is_array($criteria)) {
                    echo implode(', ', $criteria);
                } else {
                    echo htmlspecialchars($fee['waiver_criteria']);
                }
                ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p>No application fee required for this program.</p>
    <?php endif; ?>
</div>
    
    <?php 
    // Get university required documents
    $university_requirements = [];
    if (!empty($application['required_documents'])) {
        $lines = explode("\n", $application['required_documents']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && strpos($line, '- ') === 0) {
                $university_requirements[] = [
                    'document_type' => substr($line, 2),
                    'description' => '',
                    'is_mandatory' => 1
                ];
            }
        }
    }
    
    // Get scholarship requirements if student applied for scholarships
    $scholarship_requirements = [];
    $applied_scholarships = $db->fetchAll("
        SELECT ss.*, s.required_documents 
        FROM student_scholarships ss
        JOIN scholarships s ON ss.scholarship_id = s.scholarship_id
        WHERE ss.student_id = ? AND ss.status != 'withdrawn'
    ", [$student_id]);
    
    foreach ($applied_scholarships as $scholarship) {
        if (!empty($scholarship['required_documents'])) {
            $lines = explode("\n", $scholarship['required_documents']);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '- ') === 0) {
                    $scholarship_requirements[] = [
                        'document_type' => substr($line, 2),
                        'description' => 'For: ' . $scholarship['name'],
                        'is_mandatory' => 1
                    ];
                }
            }
        }
    }
    
    $all_requirements = array_merge($university_requirements, $requirements, $scholarship_requirements);
    ?>
    
    <?php if (empty($all_requirements) && empty($test_requirements)): ?>
        <div class="alert alert-info">
            <p>No specific document or test requirements have been set for this program.</p>
            <p><small>Contact the admissions office or check the university website for requirements.</small></p>
        </div>
    <?php else: ?>
        <div class="documents-checklist">
            <!-- University Requirements -->
            <?php if (!empty($university_requirements)): ?>
                <h4><i class="fas fa-university"></i> University Requirements</h4>
                <ul class="documents-list" style="list-style:none; padding-left:0;">
                    <?php foreach ($university_requirements as $req): ?>
                        <li style="margin-bottom:10px; padding:12px; background:#e8f4fc; border-radius:6px; border-left:4px solid #3498db;">
                            <label style="display:flex; align-items:flex-start; cursor:pointer;">
                                <input type="checkbox" disabled 
                                    <?php 
                                    $uploaded = false;
                                    foreach ($uploaded_docs as $doc) {
                                        if ($doc['required_for'] == $req['document_type']) {
                                            $uploaded = true;
                                            break;
                                        }
                                    }
                                    echo $uploaded ? 'checked' : '';
                                    ?>
                                    style="margin-right:12px; margin-top:3px;"
                                >
                                <div style="flex:1;">
                                    <strong style="color:#2c3e50;"><?php echo htmlspecialchars($req['document_type']); ?></strong>
                                    <?php if ($req['description']): ?>
                                        <br><small style="color:#7f8c8d;"><?php echo htmlspecialchars($req['description']); ?></small>
                                    <?php endif; ?>
                                    <div style="margin-top:8px;">
                                        <span class="badge badge-info" style="background:#3498db;">
                                            <i class="fas fa-university"></i> University
                                        </span>
                                        <?php if ($uploaded): ?>
                                            <span class="badge badge-success" style="background:#27ae60; margin-left:8px;">
                                                <i class="fas fa-check"></i> Uploaded
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="background:#95a5a6; margin-left:8px;">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <!-- Program Requirements -->
            <?php if (!empty($requirements)): ?>
                <h4><i class="fas fa-file-alt"></i> Program Requirements</h4>
                <ul class="documents-list" style="list-style:none; padding-left:0;">
                    <?php foreach ($requirements as $req): ?>
                        <li style="margin-bottom:10px; padding:12px; background:#f8f9fa; border-radius:6px; border-left:4px solid <?php echo $req['is_mandatory'] ? '#e74c3c' : '#f39c12'; ?>;">
                            <label style="display:flex; align-items:flex-start; cursor:pointer;">
                                <input type="checkbox" disabled 
                                    <?php 
                                    $uploaded = false;
                                    foreach ($uploaded_docs as $doc) {
                                        if ($doc['required_for'] == $req['document_type']) {
                                            $uploaded = true;
                                            break;
                                        }
                                    }
                                    echo $uploaded ? 'checked' : '';
                                    ?>
                                    style="margin-right:12px; margin-top:3px;"
                                >
                                <div style="flex:1;">
                                    <strong style="color:#2c3e50;"><?php echo htmlspecialchars($req['document_type']); ?></strong>
                                    <?php if ($req['description']): ?>
                                        <br><small style="color:#7f8c8d;"><?php echo htmlspecialchars($req['description']); ?></small>
                                    <?php endif; ?>
                                    <div style="margin-top:8px;">
                                        <?php if ($req['is_mandatory']): ?>
                                            <span class="badge badge-danger" style="background:#e74c3c;">
                                                <i class="fas fa-exclamation-circle"></i> Required
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning" style="background:#f39c12;">
                                                <i class="fas fa-question-circle"></i> Optional
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($uploaded): ?>
                                            <span class="badge badge-success" style="background:#27ae60; margin-left:8px;">
                                                <i class="fas fa-check"></i> Uploaded
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="background:#95a5a6; margin-left:8px;">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <!-- Scholarship Requirements -->
            <?php if (!empty($scholarship_requirements)): ?>
                <h4 style="margin-top:25px;"><i class="fas fa-award"></i> Scholarship Requirements</h4>
                <ul class="documents-list" style="list-style:none; padding-left:0;">
                    <?php foreach ($scholarship_requirements as $req): ?>
                        <li style="margin-bottom:10px; padding:12px; background:#fef9e7; border-radius:6px; border-left:4px solid #f1c40f;">
                            <label style="display:flex; align-items:flex-start; cursor:pointer;">
                                <input type="checkbox" disabled 
                                    <?php 
                                    $uploaded = false;
                                    foreach ($uploaded_docs as $doc) {
                                        if ($doc['required_for'] == $req['document_type'] || 
                                            strpos($doc['required_for'], 'Scholarship') !== false) {
                                            $uploaded = true;
                                            break;
                                        }
                                    }
                                    echo $uploaded ? 'checked' : '';
                                    ?>
                                    style="margin-right:12px; margin-top:3px;"
                                >
                                <div style="flex:1;">
                                    <strong style="color:#2c3e50;"><?php echo htmlspecialchars($req['document_type']); ?></strong>
                                    <?php if ($req['description']): ?>
                                        <br><small style="color:#7f8c8d;"><?php echo htmlspecialchars($req['description']); ?></small>
                                    <?php endif; ?>
                                    <div style="margin-top:8px;">
                                        <span class="badge badge-warning" style="background:#f1c40f;">
                                            <i class="fas fa-award"></i> Scholarship
                                        </span>
                                        <?php if ($uploaded): ?>
                                            <span class="badge badge-success" style="background:#27ae60; margin-left:8px;">
                                                <i class="fas fa-check"></i> Uploaded
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="background:#95a5a6; margin-left:8px;">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <!-- Test Score Requirements (existing) -->
            <?php if (!empty($test_requirements)): ?>
                <h4 style="margin-top:25px;"><i class="fas fa-chart-line"></i> Test Score Requirements</h4>
                <ul class="documents-list" style="list-style:none; padding-left:0;">
                    <?php foreach ($test_requirements as $test): ?>
                        <li style="margin-bottom:10px; padding:12px; background:#f8f9fa; border-radius:6px; border-left:4px solid <?php echo $test['is_mandatory'] ? '#e74c3c' : '#f39c12'; ?>;">
                            <div>
                                <strong style="color:#2c3e50;"><?php echo htmlspecialchars($test['test_name']); ?></strong>
                                <?php if ($test['minimum_score']): ?>
                                    <br><small style="color:#7f8c8d;">Minimum Score: <?php echo htmlspecialchars($test['minimum_score']); ?></small>
                                <?php endif; ?>
                                <div style="margin-top:8px;">
                                    <?php if ($test['is_mandatory']): ?>
                                        <span class="badge badge-danger" style="background:#e74c3c;">
                                            <i class="fas fa-exclamation-circle"></i> Required
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="background:#f39c12;">
                                            <i class="fas fa-question-circle"></i> Optional
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <!-- Upload Document Section -->
        <?php if ($application['status_name'] == 'Draft'): ?>
            <div style="margin-top:1.5rem; padding:1.2rem; background:#e8f4f8; border-radius:6px; border:1px solid #b3e0f2;">
                <h4 style="color:#2c3e50; margin-top:0;"><i class="fas fa-upload"></i> Upload Document</h4>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:6px; font-weight:500;">Document Type *</label>
                        <select name="required_for" required style="width:100%; padding:10px; border-radius:4px; border:1px solid #bdc3c7; font-size:14px; background:white;">
                            <option value="">-- Select Document Type --</option>
                            <?php foreach ($all_requirements as $req): ?>
                                <option value="<?php echo htmlspecialchars($req['document_type']); ?>">
                                    <?php echo htmlspecialchars($req['document_type']); ?>
                                    (<?php 
                                    if (in_array($req, $university_requirements)) echo 'University';
                                    elseif (in_array($req, $scholarship_requirements)) echo 'Scholarship';
                                    else echo 'Program';
                                    ?>)
                                </option>
                            <?php endforeach; ?>
                            <option value="Other">Other Document</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-top:15px;">
                        <label style="display:block; margin-bottom:6px; font-weight:500;">Select File *</label>
                        <input type="file" name="application_doc" required
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.txt"
                               style="width:100%; padding:10px; border:1px solid #bdc3c7; border-radius:4px; background:white;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; margin-top:15px;">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>

<!-- Rest of the file remains the same... -->
<div class="card">
    <h3><i class="fas fa-award"></i> Scholarships for this Program</h3>
    
    <?php if (empty($program_scholarships)): ?>
        <div class="alert alert-info">
            <p>No specific scholarships listed for this program.</p>
            <a href="scholarships.php" class="btn btn-secondary">Browse All Scholarships</a>
        </div>
    <?php else: ?>
        <div class="scholarships-mini-list">
            <?php foreach ($program_scholarships as $scholarship): ?>
                <div class="scholarship-mini-item">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong><?php echo htmlspecialchars($scholarship['name']); ?></strong><br>
                            <?php if ($scholarship['amount']): ?>
                                <span class="text-success">
                                    <i class="fas fa-money-bill-wave"></i> 
                                    <?php echo $scholarship['currency'] . ' ' . number_format($scholarship['amount'], 0); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-info">Variable Amount</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($scholarship['has_applied']): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Applied
                                </span>
                            <?php else: ?>
                                <form method="POST" action="scholarships.php" style="display:inline;">
                                    <input type="hidden" name="scholarship_id" value="<?php echo $scholarship['scholarship_id']; ?>">
                                    <input type="hidden" name="from_application" value="<?php echo $application_id; ?>">
                                    <button type="submit" name="apply_scholarship" 
                                            class="btn btn-small btn-primary" 
                                            onclick="return confirm('Apply for this scholarship?')">
                                        <i class="fas fa-paper-plane"></i> Apply
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($scholarship['deadline']): ?>
                        <div style="margin-top:5px; font-size:0.9em; color:#666;">
                            <i class="far fa-calendar"></i> 
                            Deadline: <?php echo Helpers::formatDate($scholarship['deadline']); ?>
                            <?php 
                            $days_left = floor((strtotime($scholarship['deadline']) - time()) / (60 * 60 * 24));
                            if ($days_left >= 0 && $days_left <= 7): ?>
                                <span class="text-danger">(<?php echo $days_left; ?> days left)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($scholarship['description']): ?>
                        <div style="margin-top:5px; font-size:0.9em; color:#666; font-style:italic;">
                            <?php echo substr($scholarship['description'], 0, 100); ?>...
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top:1rem; text-align:center;">
            <a href="scholarships.php?program_id=<?php echo $application['program_id']; ?>" 
               class="btn btn-secondary">
                <i class="fas fa-external-link-alt"></i> View All Scholarships
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Recommendations for this Application</h3>
    
    <?php if (empty($app_recommendations)): ?>
        <p>No recommendations linked to this application.</p>
        <a href="../student/recommendations.php" class="btn btn-secondary">Manage Recommendations</a>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Recommender</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Submitted</th>
                    <th>Letter</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($app_recommendations as $rec): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($rec['recommender_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($rec['email']); ?></small>
                        </td>
                        <td>
                            <?php if ($rec['submitted'] == 1): ?>
                                <span class="badge badge-success">Submitted</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo Helpers::formatDate($rec['created_at']); ?></td>
                        <td>
                            <?php if ($rec['submitted_at']): ?>
                                <?php echo Helpers::formatDate($rec['submitted_at']); ?>
                            <?php else: ?>
                                Not submitted
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rec['letter_attachment_id']): ?>
                                <?php 
                                $letter = $db->fetchOne(
                                    "SELECT * FROM attachments WHERE attachment_id = ?",
                                    [$rec['letter_attachment_id']]
                                );
                                
                                if ($letter): ?>
                                    <a href="<?php echo $letter['file_url']; ?>" target="_blank" class="btn btn-small">
                                        <i class="fas fa-download"></i> View
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($events)): ?>
<div class="card">
    <h3>Application History</h3>
    <div style="border-left:2px solid #3498db; padding-left:20px; margin-left:10px;">
        <?php foreach ($events as $event): ?>
            <div style="margin-bottom:1.5rem; position:relative;">
                <div style="position:absolute; left:-27px; top:0; width:12px; height:12px; 
                          border-radius:50%; background:#3498db;"></div>
                <strong><?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?></strong><br>
                <small><?php echo Helpers::formatDate($event['created_at'], 'F j, Y g:i A'); ?></small>
                <?php if ($event['event_data']): 
                    $data = json_decode($event['event_data'], true);
                    if ($data): ?>
                        <div style="margin-top:5px; font-size:0.9em; color:#666;">
                            <?php 
                            foreach ($data as $key => $value) {
                                echo ucfirst(str_replace('_', ' ', $key)) . ': ' . $value . '<br>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
</div>
<?php endif; ?>

<div class="card">
    <h3>Uploaded Documents</h3>
    
    <?php if (empty($uploaded_docs)): ?>
        <p>No documents uploaded yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Document Type</th>
                    <th>Filename</th>
                    <th>Uploaded</th>
                    <th>Size</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uploaded_docs as $doc): ?>
                    <tr>
                        <td><?php echo $doc['required_for'] ?: 'Other'; ?></td>
                        <td><?php echo htmlspecialchars($doc['filename']); ?></td>
                        <td><?php echo Helpers::formatDate($doc['uploaded_at']); ?></td>
                        <td><?php echo round($doc['file_size']/1024, 1); ?> KB</td>
                        <td>
                            <a href="<?php echo $doc['file_url']; ?>" target="_blank" class="btn btn-small">View</a>
                            <a href="<?php echo $doc['file_url']; ?>" download class="btn btn-small btn-secondary">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

.dashboard-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 500;
    display: inline-block;
}

.status-draft { background: #ffc107; color: #333; }
.status-submitted { background: #007bff; color: white; }
.status-under_review { background: #17a2b8; color: white; }
.status-accepted { background: #28a745; color: white; }
.status-rejected { background: #dc3545; color: white; }

.badge {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 500;
}

.badge-success { background: #28a745; color: white; }
.badge-warning { background: #ffc107; color: #333; }
.badge-danger { background: #dc3545; color: white; }
.badge-secondary { background: #6c757d; color: white; }

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error, .alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-weight: 500;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-small {
    padding: 4px 8px;
    font-size: 0.875rem;
}

.btn-block {
    display: block;
    width: 100%;
}

.scholarships-mini-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 10px;
    background: #f8f9fa;
}

.scholarship-mini-item {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 0.5rem;
    background: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.scholarship-mini-item:last-child {
    border-bottom: none;
}

.text-success {
    color: #28a745;
    font-weight: bold;
}

.text-danger {
    color: #dc3545;
    font-weight: bold;
}

.text-info {
    color: #17a2b8;
    font-weight: bold;
}

.text-muted {
    color: #6c757d;
}

.mt-3 {
    margin-top: 1rem;
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>