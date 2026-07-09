<?php
require_once '../../app/includes/init.php';
requireStudent();

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$application_id) {
    die('Invalid request');
}

$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

// Fetch application data with all details
$application = $db->fetchOne("
    SELECT a.*, p.program_name, p.department, p.duration_months,
           u.name as university_name, u.city, u.country_code,
           s.name as status_name,
           us.username, up.first_name, up.last_name, up.dob, up.nationality,
           (SELECT name FROM countries WHERE iso2 = up.nationality) as country_name
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    JOIN application_statuses s ON a.application_status_id = s.application_status_id
    JOIN students st ON a.student_id = st.student_id
    JOIN users us ON st.user_id = us.user_id
    LEFT JOIN user_profiles up ON us.user_id = up.user_id
    WHERE a.application_id = ? AND a.student_id = ? AND a.deleted_at IS NULL
", [$application_id, $student_id]);

if (!$application) {
    die('Application not found.');
}

// Fetch uploaded documents
$docs = $db->fetchAll("
    SELECT aa.required_for, a.filename, a.file_url, a.uploaded_at
    FROM application_attachments aa
    JOIN attachments a ON aa.attachment_id = a.attachment_id
    WHERE aa.application_id = ? AND a.deleted_at IS NULL
", [$application_id]);

// Fetch education
$education = $db->fetchAll("
    SELECT er.*, c.name as country_name
    FROM education_records er
    LEFT JOIN countries c ON er.country_code = c.iso2
    WHERE er.student_id = ? AND er.deleted_at IS NULL
    ORDER BY er.end_date DESC
", [$application['student_id']]);

// Fetch test scores with sections
$test_scores = $db->fetchAll("
    SELECT ts.*, tt.name as test_name
    FROM test_scores ts
    JOIN test_types tt ON ts.test_type_id = tt.test_type_id
    WHERE ts.student_id = ?
    ORDER BY ts.test_date DESC
", [$application['student_id']]);

// Build HTML
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Application Summary</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; margin: 2cm; }
        h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; border-bottom: 1px solid #bdc3c7; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #ecf0f1; text-align: left; padding: 8px; border: 1px solid #bdc3c7; }
        td { padding: 8px; border: 1px solid #bdc3c7; }
        .header { text-align: center; margin-bottom: 30px; }
        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #7f8c8d; }
        .status { background: #3498db; color: white; padding: 5px 10px; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Application Summary</h1>
        <p>Generated on <?= date('F j, Y') ?></p>
    </div>

    <h2>Application Information</h2>
    <table>
        <tr><th>Application ID</th><td><?= $application['application_id'] ?></td></tr>
        <tr><th>Program</th><td><?= htmlspecialchars($application['program_name']) ?></td></tr>
        <tr><th>University</th><td><?= htmlspecialchars($application['university_name']) ?></td></tr>
        <tr><th>Status</th><td><span class="status"><?= $application['status_name'] ?></span></td></tr>
        <tr><th>Applied Date</th><td><?= Helpers::formatDate($application['applied_at']) ?></td></tr>
        <tr><th>Submitted Date</th><td><?= $application['submitted_at'] ? Helpers::formatDate($application['submitted_at']) : 'Not submitted' ?></td></tr>
        <tr><th>Decision Date</th><td><?= $application['decision_date'] ? Helpers::formatDate($application['decision_date']) : 'Pending' ?></td></tr>
    </table>

    <h2>Student Information</h2>
    <table>
        <tr><th>Name</th><td><?= htmlspecialchars($application['first_name'] . ' ' . $application['last_name']) ?></td></tr>
        <tr><th>Username</th><td><?= htmlspecialchars($application['username']) ?></td></tr>
        <tr><th>Date of Birth</th><td><?= $application['dob'] ? Helpers::formatDate($application['dob']) : 'N/A' ?></td></tr>
        <tr><th>Nationality</th><td><?= htmlspecialchars($application['country_name'] ?: $application['nationality']) ?></td></tr>
    </table>

    <h2>Education History</h2>
    <?php if (empty($education)): ?>
        <p>No education records provided.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Institution</th><th>Degree</th><th>Major</th><th>GPA</th><th>Dates</th></tr></thead>
            <tbody>
            <?php foreach ($education as $edu): ?>
                <tr>
                    <td><?= htmlspecialchars($edu['institution_name']) ?><br><small><?= $edu['country_name'] ?></small></td>
                    <td><?= htmlspecialchars($edu['degree_name']) ?></td>
                    <td><?= htmlspecialchars($edu['major'] ?? 'N/A') ?></td>
                    <td><?= $edu['gpa'] ?: 'N/A' ?></td>
                    <td><?= $edu['start_date'] ? date('Y', strtotime($edu['start_date'])) : '' ?> - <?= $edu['end_date'] ? date('Y', strtotime($edu['end_date'])) : 'Present' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Test Scores</h2>
    <?php if (empty($test_scores)): ?>
        <p>No test scores provided.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Test</th><th>Date</th><th>Scores</th></tr></thead>
            <tbody>
            <?php foreach ($test_scores as $ts): 
                $sections = $db->fetchAll("SELECT * FROM test_score_sections WHERE score_id = ?", [$ts['score_id']]);
            ?>
                <tr>
                    <td><?= htmlspecialchars($ts['test_name']) ?></td>
                    <td><?= Helpers::formatDate($ts['test_date']) ?></td>
                    <td>
                        <?php foreach ($sections as $sec): ?>
                            <?= $sec['section_name'] . ': ' . $sec['score_value'] . '/' . $sec['max_score'] ?><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Uploaded Documents</h2>
    <?php if (empty($docs)): ?>
        <p>No documents uploaded.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Document Type</th><th>Filename</th><th>Uploaded</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $doc): ?>
                <tr>
                    <td><?= htmlspecialchars($doc['required_for'] ?: 'Other') ?></td>
                    <td><?= htmlspecialchars($doc['filename']) ?></td>
                    <td><?= Helpers::formatDate($doc['uploaded_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        <p>This is an official summary of your application. For any inquiries, please contact the admissions office.</p>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Generate PDF
Helpers::generatePDF($html, 'application_' . $application_id . '.pdf', 'A4', 'portrait');