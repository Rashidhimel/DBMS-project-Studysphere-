<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Manage Jobs";
require_once '../../app/templates/header.php';

$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_job']) || isset($_POST['edit_job'])) {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $country_code = Helpers::sanitize($_POST['country_code']);
        $title = Helpers::sanitize($_POST['title']);
        $description = Helpers::sanitize($_POST['description']);
        $company = Helpers::sanitize($_POST['company']);
        $website = Helpers::sanitize($_POST['website']);
        $job_type = Helpers::sanitize($_POST['job_type']);
        $sector = Helpers::sanitize($_POST['sector']);
        $posted_date = Helpers::sanitize($_POST['posted_date']);
        $application_deadline = !empty($_POST['application_deadline']) ? Helpers::sanitize($_POST['application_deadline']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $data = [
            'country_code' => $country_code,
            'title' => $title,
            'description' => $description,
            'company' => $company,
            'website' => $website,
            'job_type' => $job_type,
            'sector' => $sector,
            'posted_date' => $posted_date,
            'application_deadline' => $application_deadline,
            'is_active' => $is_active,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($job_id > 0 && isset($_POST['edit_job'])) {
            $db->update('jobs', $data, "job_id = $job_id");
            $success = "Job updated successfully.";
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $_SESSION['user_id'];
            $db->insert('jobs', $data);
            $success = "Job added successfully.";
        }
    } elseif (isset($_POST['delete_job'])) {
        $job_id = intval($_POST['job_id']);
        $db->query("DELETE FROM jobs WHERE job_id = ?", [$job_id]);
        $success = "Job deleted.";
    }
}

// Filters
$search = $_GET['search'] ?? '';
$country_filter = $_GET['country'] ?? '';
$type_filter = $_GET['job_type'] ?? '';
$active_filter = $_GET['active'] ?? '';

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (j.title LIKE ? OR j.company LIKE ? OR j.description LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if ($country_filter) {
    $where .= " AND j.country_code = ?";
    $params[] = $country_filter;
}
if ($type_filter) {
    $where .= " AND j.job_type = ?";
    $params[] = $type_filter;
}
if ($active_filter !== '') {
    $where .= " AND j.is_active = ?";
    $params[] = $active_filter;
}

// Get all jobs with filter
$jobs = $db->fetchAll("
    SELECT j.*, c.name as country_name 
    FROM jobs j
    JOIN countries c ON j.country_code = c.iso2
    WHERE $where
    ORDER BY j.created_at DESC
", $params);

// Get countries for dropdown
$countries = Helpers::getCountries();

// Compute stats
$total_jobs = count($jobs);
$active_jobs = count(array_filter($jobs, fn($j) => $j['is_active']));
$expired_jobs = count(array_filter($jobs, fn($j) => $j['application_deadline'] && strtotime($j['application_deadline']) < time()));
$total_applications = $db->fetchOne("SELECT COUNT(*) as count FROM job_applications")['count'] ?? 0;
?>

<div class="page-header">
    <h1><i class="fas fa-briefcase"></i> Manage Jobs</h1>
    <button class="btn btn-primary" onclick="showAddModal()">
        <i class="fas fa-plus"></i> Add New Job
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total_jobs ?></h3>
            <p>Total Jobs</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?= $active_jobs ?></h3>
            <p>Active</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #e74c3c, #c0392b);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?= $expired_jobs ?></h3>
            <p>Expired</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f39c12, #e67e22);">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total_applications ?></h3>
            <p>Applications</p>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card filter-card">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> Filter Jobs</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by title, company, description..." 
                   value="<?= htmlspecialchars($search) ?>" class="form-control">
            <select name="country" class="form-control">
                <option value="">All Countries</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= $c['iso2'] ?>" <?= $country_filter == $c['iso2'] ? 'selected' : '' ?>>
                        <?= $c['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="job_type" class="form-control">
                <option value="">All Job Types</option>
                <option value="part-time" <?= $type_filter == 'part-time' ? 'selected' : '' ?>>Part-time</option>
                <option value="full-time" <?= $type_filter == 'full-time' ? 'selected' : '' ?>>Full-time</option>
                <option value="internship" <?= $type_filter == 'internship' ? 'selected' : '' ?>>Internship</option>
                <option value="contract" <?= $type_filter == 'contract' ? 'selected' : '' ?>>Contract</option>
            </select>
            <select name="active" class="form-control">
                <option value="">All</option>
                <option value="1" <?= $active_filter === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $active_filter === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="jobs.php" class="btn btn-outline-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Jobs Table Card -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Jobs (<?= count($jobs) ?>)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Company</th>
                        <th>Country</th>
                        <th>Type</th>
                        <th>Sector</th>
                        <th>Posted</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): 
                        $is_expired = $job['application_deadline'] && strtotime($job['application_deadline']) < time();
                        $status_color = $job['is_active'] ? ($is_expired ? '#e74c3c' : '#27ae60') : '#95a5a6';
                        $status_text = $job['is_active'] ? ($is_expired ? 'Expired' : 'Active') : 'Inactive';
                    ?>
                        <tr>
                            <td><span class="badge badge-secondary">#<?= $job['job_id'] ?></span></td>
                            <td><strong><?= htmlspecialchars($job['title']) ?></strong></td>
                            <td><?= htmlspecialchars($job['company']) ?></td>
                            <td><?= $job['country_name'] ?></td>
                            <td><?= ucfirst($job['job_type']) ?></td>
                            <td><?= $job['sector'] ?: '—' ?></td>
                            <td><?= Helpers::formatDate($job['posted_date'], 'M d, Y') ?></td>
                            <td><?= $job['application_deadline'] ? Helpers::formatDate($job['application_deadline']) : 'N/A' ?></td>
                            <td>
                                <span class="status-badge" style="background: <?= $status_color ?>;">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-primary" onclick="editJob(<?= htmlspecialchars(json_encode($job)) ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this job?');">
                                        <input type="hidden" name="job_id" value="<?= $job['job_id'] ?>">
                                        <button type="submit" name="delete_job" class="btn-icon btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($jobs)): ?>
            <div class="empty-state">
                <i class="fas fa-briefcase fa-4x"></i>
                <h4>No jobs found</h4>
                <p>Try adjusting your filters or add a new job.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal (modernised) -->
<div id="jobModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add Job</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="jobForm">
            <input type="hidden" name="job_id" id="job_id" value="">
            <div class="form-group">
                <label for="country_code">Country *</label>
                <select name="country_code" id="country_code" class="form-control" required>
                    <option value="">Select Country</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= $c['iso2'] ?>"><?= $c['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="title">Job Title *</label>
                <input type="text" name="title" id="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="company">Company *</label>
                <input type="text" name="company" id="company" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="website">Website</label>
                <input type="url" name="website" id="website" class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="job_type">Job Type *</label>
                    <select name="job_type" id="job_type" class="form-control" required>
                        <option value="part-time">Part-time</option>
                        <option value="full-time">Full-time</option>
                        <option value="internship">Internship</option>
                        <option value="contract">Contract</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sector">Sector</label>
                    <input type="text" name="sector" id="sector" class="form-control" placeholder="e.g., IT, Hospitality">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="posted_date">Posted Date *</label>
                    <input type="date" name="posted_date" id="posted_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="application_deadline">Application Deadline</label>
                    <input type="date" name="application_deadline" id="application_deadline" class="form-control">
                </div>
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="is_active" id="is_active" value="1" checked> Active
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_job" id="submitBtn" class="btn btn-primary">Add Job</button>
                <button type="button" class="btn btn-outline-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Reuse modern styles from other admin pages */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.06);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}
.stat-content h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.8rem;
    font-weight: 500;
}

.card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.02);
    margin-bottom: 2rem;
    overflow: hidden;
}
.card-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-body {
    padding: 1.5rem;
}
.card-body.p-0 {
    padding: 0;
}

.filter-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-form .form-control {
    min-width: 150px;
    flex: 1;
}
.btn-outline-secondary {
    border-color: #ced4da;
    color: #6c757d;
}
.btn-outline-secondary:hover {
    background: #e2e6ea;
}

.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.table th {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
}
.table td {
    padding: 1rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}
.table tbody tr:last-child td {
    border-bottom: none;
}
.table-hover tbody tr:hover {
    background: #f9fbfd;
}

.badge-secondary {
    background: #e2e3e5;
    color: #383d41;
    padding: 0.2rem 0.5rem;
    border-radius: 30px;
    font-size: 0.8rem;
}
.status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 30px;
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
}
.action-buttons {
    display: flex;
    gap: 0.4rem;
}
.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-icon.btn-primary { background: #3498db; }
.btn-icon.btn-danger { background: #e74c3c; }
.btn-icon:hover {
    opacity: 0.8;
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #718096;
}
.empty-state i {
    color: #cbd5e0;
    margin-bottom: 1rem;
}
.empty-state h4 {
    margin-bottom: 0.5rem;
    color: #4a5568;
}

/* Modal styling */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background-color: #fff;
    max-width: 600px;
    width: 90%;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: modalSlide 0.3s ease;
    padding: 0;
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
    color: #2d3748;
    font-size: 1.4rem;
}
.close {
    background: none;
    border: none;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    color: #a0aec0;
}
.close:hover {
    color: #718096;
}
.modal-body {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.2rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 500;
    color: #4a5568;
}
.form-control {
    width: 100%;
    padding: 0.6rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
}
.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}
.form-row {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.form-row .form-group {
    flex: 1;
    min-width: 200px;
}
.checkbox label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 400;
}
.checkbox input {
    width: auto;
    margin: 0;
}
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}
.btn-primary {
    background: linear-gradient(145deg, #3498db, #2980b9);
    border: none;
    border-radius: 30px;
    padding: 0.6rem 2rem;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52,152,219,0.3);
}
.btn-outline-secondary {
    border-radius: 30px;
    padding: 0.6rem 2rem;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form .form-control,
    .filter-form .btn {
        width: 100%;
    }
}
</style>

<script>
function showAddModal() {
    document.getElementById('modalTitle').innerText = 'Add Job';
    document.getElementById('job_id').value = '';
    document.getElementById('country_code').value = '';
    document.getElementById('title').value = '';
    document.getElementById('company').value = '';
    document.getElementById('description').value = '';
    document.getElementById('website').value = '';
    document.getElementById('job_type').value = 'part-time';
    document.getElementById('sector').value = '';
    document.getElementById('posted_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('application_deadline').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('submitBtn').name = 'add_job';
    document.getElementById('jobModal').style.display = 'flex';
}

function editJob(job) {
    document.getElementById('modalTitle').innerText = 'Edit Job';
    document.getElementById('job_id').value = job.job_id;
    document.getElementById('country_code').value = job.country_code;
    document.getElementById('title').value = job.title;
    document.getElementById('company').value = job.company;
    document.getElementById('description').value = job.description;
    document.getElementById('website').value = job.website || '';
    document.getElementById('job_type').value = job.job_type;
    document.getElementById('sector').value = job.sector || '';
    document.getElementById('posted_date').value = job.posted_date;
    document.getElementById('application_deadline').value = job.application_deadline || '';
    document.getElementById('is_active').checked = job.is_active == 1;
    document.getElementById('submitBtn').name = 'edit_job';
    document.getElementById('jobModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('jobModal').style.display = 'none';
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>