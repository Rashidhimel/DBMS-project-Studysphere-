<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Advanced Analytics";
require_once '../../app/templates/header.php';

$db = new Database();

// Use existing views for funnel and popularity
$funnel = $db->fetchAll("SELECT * FROM application_funnel ORDER BY order_index");
$popular_programs = $db->fetchAll("SELECT * FROM program_popularity ORDER BY total_applications DESC LIMIT 10");

// Additional stats
$monthly_applications = $db->fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM applications
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month
");

$status_distribution = $db->fetchAll("
    SELECT s.name, COUNT(a.application_id) as count
    FROM application_statuses s
    LEFT JOIN applications a ON s.application_status_id = a.application_status_id AND a.deleted_at IS NULL
    GROUP BY s.name
");

$top_universities = $db->fetchAll("
    SELECT u.name, COUNT(a.application_id) as app_count,
           SUM(CASE WHEN as2.code = 'accepted' THEN 1 ELSE 0 END) as accepted
    FROM universities u
    LEFT JOIN applications a ON u.university_id = a.university_id AND a.deleted_at IS NULL
    LEFT JOIN application_statuses as2 ON a.application_status_id = as2.application_status_id
    GROUP BY u.university_id
    ORDER BY app_count DESC
    LIMIT 10
");

$counselor_stats = $db->fetchAll("
    SELECT u.username, COUNT(DISTINCT ca.student_id) as student_count,
           COUNT(DISTINCT cs.session_id) as session_count
    FROM users u
    LEFT JOIN counselor_assignments ca ON u.user_id = ca.counselor_id AND ca.is_active = 1
    LEFT JOIN counseling_sessions cs ON ca.assignment_id = cs.assignment_id
    WHERE u.default_role_id = (SELECT role_id FROM roles WHERE name = 'counselor')
    GROUP BY u.user_id
    ORDER BY student_count DESC
");
?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> Advanced Analytics</h1>
    <p>Comprehensive insights into applications, programs, and counselor performance</p>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>Application Funnel</h3>
        <canvas id="funnelChart" height="200"></canvas>
        <script>
            new Chart(document.getElementById('funnelChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($funnel, 'stage_name')) ?>,
                    datasets: [{
                        label: 'Applications',
                        data: <?= json_encode(array_column($funnel, 'applications_count')) ?>,
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });
        </script>
    </div>

    <div class="dashboard-card">
        <h3>Monthly Applications (Last 12 Months)</h3>
        <canvas id="monthlyChart" height="200"></canvas>
        <script>
            new Chart(document.getElementById('monthlyChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($monthly_applications, 'month')) ?>,
                    datasets: [{
                        label: 'Applications',
                        data: <?= json_encode(array_column($monthly_applications, 'count')) ?>,
                        borderColor: '#2ecc71',
                        fill: false
                    }]
                }
            });
        </script>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h3>Status Distribution</h3>
        <canvas id="statusPie" height="200"></canvas>
        <script>
            new Chart(document.getElementById('statusPie'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_column($status_distribution, 'name')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($status_distribution, 'count')) ?>,
                        backgroundColor: ['#3498db','#2ecc71','#f39c12','#e74c3c','#9b59b6']
                    }]
                }
            });
        </script>
    </div>

    <div class="dashboard-card">
        <h3>Top Programs by Applications</h3>
        <table class="table">
            <thead><tr><th>Program</th><th>University</th><th>Apps</th><th>Accepted</th></tr></thead>
            <tbody>
            <?php foreach ($popular_programs as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['program_name']) ?></td>
                    <td><?= htmlspecialchars($p['university_name']) ?></td>
                    <td><?= $p['total_applications'] ?></td>
                    <td><?= $p['total_accepted'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-card">
    <h3>Counselor Performance</h3>
    <table class="table">
        <thead><tr><th>Counselor</th><th>Active Students</th><th>Sessions Held</th></tr></thead>
        <tbody>
        <?php foreach ($counselor_stats as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['username']) ?></td>
                <td><?= $c['student_count'] ?></td>
                <td><?= $c['session_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../app/templates/footer.php'; ?>