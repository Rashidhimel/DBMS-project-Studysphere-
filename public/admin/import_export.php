<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Import / Export";
require_once '../../app/templates/header.php';

$db = new Database();
$message = '';
$error = '';

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $type = $_POST['import_type']; // universities, programs, scholarships
    $file = $_FILES['csv_file']['tmp_name'];
    
    if (($handle = fopen($file, 'r')) !== FALSE) {
        $header = fgetcsv($handle); // skip header
        $imported = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            try {
                if ($type == 'universities') {
                    // Expected columns: name, country_code, city, website, qs_ranking, approx_tuition, description
                    $db->insert('universities', [
                        'name' => $data[0],
                        'country_code' => $data[1] ?? null,
                        'city' => $data[2] ?? null,
                        'website' => $data[3] ?? null,
                        'qs_ranking' => intval($data[4] ?? 0),
                        'approx_tuition' => floatval($data[5] ?? 0),
                        'description' => $data[6] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $_SESSION['user_id']
                    ]);
                } elseif ($type == 'programs') {
                    // Columns: university_id, program_name, degree_level_id, department, duration_months, tuition_fee, currency, intake_months, application_deadline
                    $db->insert('programs', [
                        'university_id' => intval($data[0]),
                        'program_name' => $data[1],
                        'degree_level_id' => intval($data[2]),
                        'department' => $data[3] ?? null,
                        'duration_months' => intval($data[4] ?? 0),
                        'tuition_fee' => floatval($data[5] ?? 0),
                        'currency' => $data[6] ?? 'USD',
                        'intake_months' => $data[7] ?? null,
                        'application_deadline' => $data[8] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $_SESSION['user_id']
                    ]);
                } elseif ($type == 'scholarships') {
                    // Columns: program_id, name, amount, currency, criteria_json, deadline
                    $db->insert('scholarships', [
                        'program_id' => intval($data[0]),
                        'name' => $data[1],
                        'amount' => floatval($data[2] ?? 0),
                        'currency' => $data[3] ?? 'USD',
                        'criteria' => $data[4] ?? null,
                        'deadline' => $data[5] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $_SESSION['user_id']
                    ]);
                }
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Row " . ($imported+2) . ": " . $e->getMessage();
            }
        }
        fclose($handle);
        $message = "Imported $imported records successfully.";
        if (!empty($errors)) $error = implode('<br>', $errors);
    } else {
        $error = "Could not open file.";
    }
}

// Handle export
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $filename = $export_type . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($export_type == 'students') {
        fputcsv($output, ['Student ID', 'Username', 'Email', 'First Name', 'Last Name', 'Country', 'Created']);
        $students = $db->fetchAll("
            SELECT s.student_id, u.username, u.email, up.first_name, up.last_name, c.name as country, u.created_at
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            LEFT JOIN countries c ON up.nationality = c.iso2
            WHERE u.deleted_at IS NULL
        ");
        foreach ($students as $s) {
            fputcsv($output, [
                $s['student_id'],
                $s['username'],
                $s['email'],
                $s['first_name'],
                $s['last_name'],
                $s['country'],
                $s['created_at']
            ]);
        }
    } elseif ($export_type == 'applications') {
        fputcsv($output, ['Application ID', 'Student', 'Program', 'University', 'Status', 'Applied', 'Submitted']);
        $apps = $db->fetchAll("
            SELECT a.application_id, u.username, p.program_name, un.name, s.name as status,
                   a.applied_at, a.submitted_at
            FROM applications a
            JOIN students st ON a.student_id = st.student_id
            JOIN users u ON st.user_id = u.user_id
            JOIN programs p ON a.program_id = p.program_id
            JOIN universities un ON a.university_id = un.university_id
            JOIN application_statuses s ON a.application_status_id = s.application_status_id
            WHERE a.deleted_at IS NULL
        ");
        foreach ($apps as $a) {
            fputcsv($output, [
                $a['application_id'],
                $a['username'],
                $a['program_name'],
                $a['name'],
                $a['status'],
                $a['applied_at'],
                $a['submitted_at']
            ]);
        }
    }
    fclose($output);
    exit;
}

// Get counts for display
$university_count = $db->fetchOne("SELECT COUNT(*) as c FROM universities")['c'];
$program_count = $db->fetchOne("SELECT COUNT(*) as c FROM programs")['c'];
$scholarship_count = $db->fetchOne("SELECT COUNT(*) as c FROM scholarships")['c'];
?>

<div class="page-header">
    <h1><i class="fas fa-database"></i> Import / Export</h1>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

<div class="dashboard-grid">
    <!-- Import Card -->
    <div class="dashboard-card">
        <h3>Import from CSV</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Data Type</label>
                <select name="import_type" class="form-control" required>
                    <option value="universities">Universities (<?= $university_count ?> existing)</option>
                    <option value="programs">Programs (<?= $program_count ?> existing)</option>
                    <option value="scholarships">Scholarships (<?= $scholarship_count ?> existing)</option>
                </select>
            </div>
            <div class="form-group">
                <label>CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required>
                <small>First row must be column headers (they are ignored).</small>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button>
        </form>
        <hr>
        <h4>CSV Format Examples</h4>
        <pre>
Universities: name,country_code,city,website,qs_ranking,approx_tuition,description
Programs: university_id,program_name,degree_level_id,department,duration_months,tuition_fee,currency,intake_months,application_deadline
Scholarships: program_id,name,amount,currency,criteria_json,deadline
        </pre>
    </div>

    <!-- Export Card -->
    <div class="dashboard-card">
        <h3>Export Data</h3>
        <div class="export-options">
            <a href="?export=students" class="btn btn-secondary btn-block">
                <i class="fas fa-file-excel"></i> Export Students
            </a>
            <a href="?export=applications" class="btn btn-secondary btn-block">
                <i class="fas fa-file-excel"></i> Export Applications
            </a>
            <p class="mt-2 text-muted">CSV files will be downloaded.</p>
        </div>
    </div>
</div>

<?php require_once '../../app/templates/footer.php'; ?>