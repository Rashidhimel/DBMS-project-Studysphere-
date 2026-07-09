<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = __('milestones_title');
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

// Get all milestone definitions
$all_milestones = $db->fetchAll("SELECT * FROM milestone_definitions ORDER BY points DESC");

// Get student's earned milestones (with definitions)
$earned = $db->fetchAll("
    SELECT sm.*, md.name, md.description, md.badge_icon, md.points, md.code
    FROM student_milestones sm
    JOIN milestone_definitions md ON sm.milestone_def_id = md.milestone_id
    WHERE sm.student_id = ?
    ORDER BY sm.achieved_at DESC
", [$student_id]);

// Build lookup of earned milestone IDs
$earned_ids = array_column($earned, 'milestone_def_id');

// Calculate total points
$total_points = array_sum(array_column($earned, 'points'));

// Next milestones (not earned yet)
$next_milestones = array_filter($all_milestones, function($m) use ($earned_ids) {
    return !in_array($m['milestone_id'], $earned_ids);
});
?>

<div class="page-header">
    <h1><i class="fas fa-trophy"></i> <?= __('milestones_title') ?></h1>
    <p class="text-muted"><?= __('milestones_subtitle') ?></p>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #f1c40f, #e67e22);">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-content">
            <h3><?= count($earned) ?></h3>
            <p><?= __('milestones_earned') ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #3498db, #2980b9);">
            <i class="fas fa-coins"></i>
        </div>
        <div class="stat-content">
            <h3><?= $total_points ?></h3>
            <p><?= __('milestones_points') ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(145deg, #2ecc71, #27ae60);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?= count($next_milestones) ?></h3>
            <p><?= __('milestones_remaining') ?></p>
        </div>
    </div>
</div>

<!-- Main Two‑Column Layout -->
<div class="dashboard-grid">
    <!-- Earned Milestones Column -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-check-circle" style="color: #f1c40f;"></i> <?= __('milestones_earned') ?></h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($earned)): ?>
                <p class="empty-message"><?= __('milestones_no_earned') ?></p>
            <?php else: ?>
                <div class="scrollable-list">
                    <?php foreach ($earned as $m): ?>
                        <div class="milestone-item earned">
                            <div class="milestone-icon">
                                <?php if ($m['badge_icon']): ?>
                                    <i class="fas <?= htmlspecialchars($m['badge_icon']) ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-award"></i>
                                <?php endif; ?>
                            </div>
                            <div class="milestone-content">
                                <h4><?= htmlspecialchars($m['name']) ?></h4>
                                <p><?= htmlspecialchars($m['description']) ?></p>
                                <small><i class="far fa-calendar-alt"></i> <?= __('milestones_achieved_on') ?> <?= Helpers::formatDate($m['achieved_at']) ?></small>
                            </div>
                            <div class="milestone-points">+<?= $m['points'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Next Milestones Column -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hourglass-half" style="color: #3498db;"></i> <?= __('milestones_next') ?></h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($next_milestones)): ?>
                <p class="empty-message"><?= __('milestones_all_earned') ?></p>
            <?php else: ?>
                <div class="scrollable-list">
                    <?php foreach ($next_milestones as $m): ?>
                        <div class="milestone-item next">
                            <div class="milestone-icon next-icon">
                                <?php if ($m['badge_icon']): ?>
                                    <i class="fas <?= htmlspecialchars($m['badge_icon']) ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-award"></i>
                                <?php endif; ?>
                            </div>
                            <div class="milestone-content">
                                <h4><?= htmlspecialchars($m['name']) ?></h4>
                                <p><?= htmlspecialchars($m['description']) ?></p>
                            </div>
                            <div class="milestone-points next-points"><?= $m['points'] ?> pts</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Page header */
.page-header {
    margin-bottom: 2rem;
}
.page-header h1 {
    margin-bottom: 0.25rem;
}

/* Stats grid (3 cards) */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 18px;
    padding: 1rem 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.stat-content h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Two‑column grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Cards */
.card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
}
.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-body.p-0 {
    padding: 0;
    flex: 1;
}

/* Scrollable list */
.scrollable-list {
    max-height: 450px;
    overflow-y: auto;
    padding: 0.5rem;
}

/* Milestone items */
.milestone-item {
    display: flex;
    align-items: center;
    padding: 0.8rem 1rem;
    margin-bottom: 0.5rem;
    background: white;
    border-radius: 14px;
    border: 1px solid #eef2f6;
    transition: all 0.2s;
}
.milestone-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}
.milestone-item.earned {
    background: #fef9e7;
    border-left: 4px solid #f1c40f;
}
.milestone-item.next {
    opacity: 0.9;
}
.milestone-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #f1c40f;
    margin-right: 1rem;
    flex-shrink: 0;
}
.milestone-icon.next-icon {
    opacity: 0.6;
    color: #95a5a6;
}
.milestone-content {
    flex: 1;
    min-width: 0;
}
.milestone-content h4 {
    margin: 0 0 0.2rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
}
.milestone-content p {
    margin: 0 0 0.2rem 0;
    font-size: 0.85rem;
    color: #7f8c8d;
    line-height: 1.3;
}
.milestone-content small {
    font-size: 0.75rem;
    color: #95a5a6;
    display: flex;
    align-items: center;
    gap: 0.2rem;
}
.milestone-points {
    font-weight: 700;
    color: #f1c40f;
    background: #fff3cd;
    padding: 0.25rem 0.8rem;
    border-radius: 30px;
    font-size: 0.85rem;
    white-space: nowrap;
    margin-left: 0.5rem;
}
.milestone-points.next-points {
    background: #e9ecef;
    color: #7f8c8d;
}

/* Empty message */
.empty-message {
    padding: 2rem;
    text-align: center;
    color: #7f8c8d;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>