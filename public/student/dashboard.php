<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Student Dashboard";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

// Application statistics
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN s.code = 'accepted' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN s.code = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN s.code = 'under_review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN s.code = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN s.code = 'draft' THEN 1 ELSE 0 END) as draft
    FROM applications a
    JOIN application_statuses s ON a.application_status_id = s.application_status_id
    WHERE a.student_id = ? AND a.deleted_at IS NULL
", [$student_id]);

// Combined deadlines: applications + scholarships + tasks + visas
$deadlines = $db->fetchAll("
    (SELECT 
        'application' as type,
        a.application_id as item_id,
        p.program_name as name,
        u.name as institution,
        a.deadline as deadline_date,
        s.name as status_name,
        s.code as status_code,
        DATEDIFF(a.deadline, CURDATE()) as days_left,
        NULL as priority
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    JOIN application_statuses s ON a.application_status_id = s.application_status_id
    WHERE a.student_id = ? AND a.deleted_at IS NULL 
      AND a.deadline IS NOT NULL AND a.deadline >= CURDATE())
UNION ALL
    (SELECT 
        'scholarship' as type,
        ss.id as item_id,
        sch.name as name,
        p.program_name as institution,
        sch.deadline as deadline_date,
        ss.status as status_name,
        ss.status as status_code,
        DATEDIFF(sch.deadline, CURDATE()) as days_left,
        NULL as priority
    FROM student_scholarships ss
    JOIN scholarships sch ON ss.scholarship_id = sch.scholarship_id
    JOIN programs p ON sch.program_id = p.program_id
    WHERE ss.student_id = ? AND sch.deadline IS NOT NULL AND sch.deadline >= CURDATE())
UNION ALL
    (SELECT 
        'task' as type,
        st.todo_id as item_id,
        st.title as name,
        '' as institution,
        st.due_date as deadline_date,
        st.priority as status_name,
        st.priority as status_code,
        DATEDIFF(st.due_date, CURDATE()) as days_left,
        st.priority as priority
    FROM student_todos st
    WHERE st.student_id = ? AND st.completed = 0 
      AND st.due_date IS NOT NULL AND st.due_date >= CURDATE())
UNION ALL
    (SELECT 
        'visa' as type,
        sv.visa_id as item_id,
        vt.name as name,
        '' as institution,
        CASE 
            WHEN sv.application_date IS NOT NULL AND sv.application_date >= CURDATE() THEN sv.application_date
            WHEN sv.expiry_date IS NOT NULL AND sv.expiry_date >= CURDATE() THEN sv.expiry_date
            ELSE NULL
        END as deadline_date,
        sv.status as status_name,
        sv.status as status_code,
        DATEDIFF(
            CASE 
                WHEN sv.application_date IS NOT NULL AND sv.application_date >= CURDATE() THEN sv.application_date
                WHEN sv.expiry_date IS NOT NULL AND sv.expiry_date >= CURDATE() THEN sv.expiry_date
                ELSE NULL
            END, CURDATE()) as days_left,
        NULL as priority
    FROM student_visas sv
    JOIN visa_types vt ON sv.visa_type_id = vt.visa_type_id
    WHERE sv.student_id = ? 
      AND sv.status NOT IN ('expired', 'rejected')
      AND (
          (sv.application_date IS NOT NULL AND sv.application_date >= CURDATE()) OR
          (sv.expiry_date IS NOT NULL AND sv.expiry_date >= CURDATE())
      ))
ORDER BY deadline_date ASC
LIMIT 5
", [$student_id, $student_id, $student_id, $student_id]);

// Recent applications
$applications = $db->fetchAll("
    SELECT a.*, p.program_name, u.name as university_name, s.name as status_name, s.code as status_code
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    JOIN application_statuses s ON a.application_status_id = s.application_status_id
    WHERE a.student_id = ? AND a.deleted_at IS NULL
    ORDER BY a.applied_at DESC
    LIMIT 5
", [$student_id]);

// Unread messages
$unread_messages = $db->fetchOne("
    SELECT COUNT(*) as count FROM messages 
    WHERE receiver_id = ? AND is_read = 0
", [$user['user_id']])['count'] ?? 0;

// Upcoming counselling sessions
$upcoming_sessions = $db->fetchAll("
    SELECT cs.*, u.username as counselor_name, up.first_name, up.last_name
    FROM counseling_sessions cs
    JOIN counselor_assignments ca ON cs.assignment_id = ca.assignment_id
    JOIN users u ON ca.counselor_id = u.user_id
    LEFT JOIN user_profiles up ON u.user_id = up.user_id
    WHERE ca.student_id = ? AND cs.is_booked = 1 AND cs.scheduled_at > NOW()
    ORDER BY cs.scheduled_at ASC
    LIMIT 3
", [$student_id]);

// Pending tasks
$pending_tasks = $db->fetchAll("
    SELECT * FROM student_todos 
    WHERE student_id = ? AND completed = 0
    ORDER BY due_date ASC
    LIMIT 5
", [$student_id]);

// Profile completion
$profile = $db->fetchOne("
    SELECT up.first_name, up.last_name, up.dob, up.nationality,
           (SELECT COUNT(*) FROM education_records WHERE student_id = ?) as edu_count,
           (SELECT COUNT(*) FROM test_scores WHERE student_id = ?) as test_count
    FROM user_profiles up
    WHERE up.user_id = ?
", [$student_id, $student_id, $user['user_id']]);

$profile_completion = 0;
$show_profile_bar = true;

if ($profile && is_array($profile)) {
    if (!empty($profile['first_name'] ?? '')) $profile_completion += 20;
    if (!empty($profile['last_name'] ?? '')) $profile_completion += 20;
    if (!empty($profile['dob'] ?? '')) $profile_completion += 15;
    if (!empty($profile['nationality'] ?? '')) $profile_completion += 15;
    if (($profile['edu_count'] ?? 0) > 0) $profile_completion += 15;
    if (($profile['test_count'] ?? 0) > 0) $profile_completion += 15;
    
    if ($profile_completion >= 100) {
        $show_profile_bar = false;
    }
}

$display_name = ($profile && !empty($profile['first_name'] ?? '')) ? $profile['first_name'] : ($user['username'] ?? 'Student');
?>

<div class="dashboard-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 2.2rem; margin: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                Welcome back, <?php echo htmlspecialchars($display_name); ?>! 👋
            </h1>
            <p style="color: #666; margin-top: 0.5rem;">Track your applications and manage your study abroad journey</p>
        </div>
        <div class="header-actions" style="display: flex; gap: 0.75rem;">
            <a href="messages.php" class="btn-action" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 50px; border: 2px solid #3498db; background: transparent; color: #3498db; font-weight: 600; transition: all 0.3s; text-decoration: none;">
                <i class="fas fa-envelope"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="badge" style="background: #dc3545; color: white; margin-left: 5px; padding: 0.2rem 0.6rem; border-radius: 50px;"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="btn-action" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 50px; border: 2px solid #3498db; background: transparent; color: #3498db; font-weight: 600; transition: all 0.3s; text-decoration: none;">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
        </div>
    </div>
</div>

<!-- Profile completion bar -->
<?php if ($show_profile_bar): ?>
<div class="profile-completion" style="background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); padding: 1.5rem; border-radius: 16px; margin: 2rem 0; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <span style="font-weight: 600; font-size: 1.1rem; color: #2c3e50;">
            <i class="fas fa-user-check" style="color: #27ae60; margin-right: 0.5rem;"></i>Profile Completion
        </span>
        <span style="font-size: 1.5rem; font-weight: 700; color: <?php 
            if ($profile_completion >= 80) echo '#27ae60';
            elseif ($profile_completion >= 50) echo '#f39c12';
            else echo '#e74c3c';
        ?>;"><?php echo $profile_completion; ?>%</span>
    </div>
    <div style="height: 12px; background: rgba(255,255,255,0.5); border-radius: 6px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
        <div style="height: 100%; width: <?php echo $profile_completion; ?>%; background: linear-gradient(90deg, #27ae60, #2ecc71); border-radius: 6px; transition: width 0.5s ease;"></div>
    </div>
    <small class="text-muted" style="display: block; margin-top: 0.75rem; color: #7f8c8d;">
        <i class="fas fa-info-circle"></i> Complete your profile to get better program recommendations
    </small>
</div>
<?php endif; ?>

<!-- Statistics Cards (text below numbers) -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    <div class="stat-card" style="background: white; padding: 1.8rem 1.5rem; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.03); text-align: center; transition: transform 0.3s, box-shadow 0.3s;">
        <div style="font-size: 2.8rem; color: #4CAF50; margin-bottom: 0.5rem;">
            <i class="fas fa-file-alt"></i>
        </div>
        <h3 style="margin: 0; font-size: 2.2rem; font-weight: 700; line-height: 1.2;"><?php echo $stats['total_applications'] ?: 0; ?></h3>
        <p style="margin: 0.3rem 0 0; color: #666; font-weight: 500;">Total Applications</p>
    </div>
    <div class="stat-card" style="background: white; padding: 1.8rem 1.5rem; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.03); text-align: center; transition: transform 0.3s, box-shadow 0.3s;">
        <div style="font-size: 2.8rem; color: #2196F3; margin-bottom: 0.5rem;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 style="margin: 0; font-size: 2.2rem; font-weight: 700;"><?php echo $stats['accepted'] ?: 0; ?></h3>
        <p style="margin: 0.3rem 0 0; color: #666; font-weight: 500;">Accepted</p>
    </div>
    <div class="stat-card" style="background: white; padding: 1.8rem 1.5rem; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.03); text-align: center; transition: transform 0.3s, box-shadow 0.3s;">
        <div style="font-size: 2.8rem; color: #FF9800; margin-bottom: 0.5rem;">
            <i class="fas fa-clock"></i>
        </div>
        <h3 style="margin: 0; font-size: 2.2rem; font-weight: 700;"><?php echo ($stats['submitted'] + $stats['under_review']) ?: 0; ?></h3>
        <p style="margin: 0.3rem 0 0; color: #666; font-weight: 500;">In Progress</p>
    </div>
    <div class="stat-card" style="background: white; padding: 1.8rem 1.5rem; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.03); text-align: center; transition: transform 0.3s, box-shadow 0.3s;">
        <div style="font-size: 2.8rem; color: #9C27B0; margin-bottom: 0.5rem;">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <h3 style="margin: 0; font-size: 2.2rem; font-weight: 700;"><?php echo count($deadlines); ?></h3>
        <p style="margin: 0.3rem 0 0; color: #666; font-weight: 500;">Upcoming Deadlines</p>
    </div>
</div>

<!-- Main Dashboard Grid -->
<div class="dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.8rem; margin-bottom: 2rem;">
    <!-- Recent Applications -->
    <div class="dashboard-card" style="background: white; border-radius: 20px; padding: 1.8rem; box-shadow: 0 8px 25px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.3rem; font-weight: 600;">
                <i class="fas fa-file-alt" style="color: #4CAF50; margin-right: 0.5rem;"></i> Recent Applications
            </h3>
            <a href="applications.php" class="btn-small" style="background: #f8f9fa; padding: 0.4rem 1rem; border-radius: 50px; color: #3498db; font-weight: 500; text-decoration: none; transition: all 0.2s;">View All →</a>
        </div>
        <div class="scrollable-list" style="max-height: 350px; overflow-y: auto; padding-right: 0.5rem;">
            <?php if (empty($applications)): ?>
                <div class="empty-state" style="text-align: center; padding: 3rem 1rem;">
                    <i class="fas fa-file-alt fa-4x" style="color: #ddd;"></i>
                    <p style="color: #666; margin: 1rem 0;">No applications yet.</p>
                    <a href="applications.php" class="btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.8rem 2rem; border-radius: 50px; text-decoration: none; font-weight: 600; display: inline-block;">Start Your First Application</a>
                </div>
            <?php else: ?>
                <div class="applications-list">
                    <?php foreach ($applications as $app): ?>
                        <div style="border-bottom: 1px solid #f0f0f0; padding: 1.2rem 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 2;">
                                <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($app['program_name']); ?></strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($app['university_name']); ?></span><br>
                                <small style="color: #999;"><i class="far fa-calendar"></i> <?php echo Helpers::formatDate($app['applied_at']); ?></small>
                            </div>
                            <div style="text-align: right; flex: 1;">
                                <span class="status-badge status-<?php echo $app['status_code']; ?>"><?php echo $app['status_name']; ?></span><br>
                                <a href="application_view.php?id=<?php echo $app['application_id']; ?>" class="view-link" style="display: inline-block; margin-top: 0.7rem; color: #3498db; text-decoration: none; font-weight: 500;">View Details →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Unified Deadlines -->
    <div class="dashboard-card" style="background: white; border-radius: 20px; padding: 1.8rem; box-shadow: 0 8px 25px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03);">
        <h3 style="margin: 0 0 1.5rem 0; font-size: 1.3rem; font-weight: 600;">
            <i class="fas fa-calendar-alt" style="color: #9C27B0; margin-right: 0.5rem;"></i> Upcoming Deadlines
        </h3>
        <div class="scrollable-list" style="max-height: 350px; overflow-y: auto; padding-right: 0.5rem;">
            <?php if (empty($deadlines)): ?>
                <div class="empty-state" style="text-align: center; padding: 3rem 1rem;">
                    <i class="fas fa-calendar-check fa-4x" style="color: #ddd;"></i>
                    <p style="color: #666; margin-top: 1rem;">No upcoming deadlines.</p>
                    <p style="color: #999; font-size: 0.9rem;">Deadlines from your applications, scholarships, tasks, and visas will appear here.</p>
                </div>
            <?php else: ?>
                <div class="deadlines-list">
                    <?php foreach ($deadlines as $dl): ?>
                        <div style="border-bottom: 1px solid #f0f0f0; padding: 1.2rem 0;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <strong><?php echo htmlspecialchars($dl['name']); ?></strong>
                                    <?php
                                    $type_color = '#6c757d';
                                    $type_icon = '';
                                    switch($dl['type']) {
                                        case 'application': $type_color = '#3498db'; $type_icon = 'fa-file-alt'; break;
                                        case 'scholarship': $type_color = '#f1c40f'; $type_icon = 'fa-award'; break;
                                        case 'task': $type_color = '#e67e22'; $type_icon = 'fa-tasks'; break;
                                        case 'visa': $type_color = '#2c3e50'; $type_icon = 'fa-passport'; break;
                                    }
                                    ?>
                                    <span class="type-badge" style="display: inline-block; margin-left: 0.5rem; padding: 0.2rem 0.5rem; background: <?php echo $type_color; ?>; color: white; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                        <i class="fas <?php echo $type_icon; ?>"></i> <?php echo ucfirst($dl['type']); ?>
                                    </span>
                                    <?php if (!empty($dl['institution'])): ?>
                                        <br><span style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($dl['institution']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="deadline-badge" style="
                                    padding: 0.3rem 0.8rem; 
                                    border-radius: 50px; 
                                    background: <?php 
                                        if ($dl['days_left'] <= 7) echo '#dc3545';
                                        elseif ($dl['days_left'] <= 30) echo '#ffc107';
                                        else echo '#28a745';
                                    ?>; 
                                    color: <?php echo ($dl['days_left'] <= 7 || $dl['days_left'] > 30) ? 'white' : '#333'; ?>;
                                    font-weight: 600;
                                    font-size: 0.9rem;
                                ">
                                    <?php echo $dl['days_left']; ?> days
                                </span>
                            </div>
                            <div style="margin-top: 0.8rem; display: flex; justify-content: space-between; align-items: center;">
                                <span class="status-badge" style="background: <?php 
                                    if ($dl['type'] == 'task') {
                                        if ($dl['status_code'] == 'high') echo '#dc3545';
                                        elseif ($dl['status_code'] == 'medium') echo '#ffc107';
                                        else echo '#28a745';
                                    } else {
                                        echo '#6c757d';
                                    }
                                ?>; color: white;"><?php echo ucfirst($dl['status_name']); ?></span>
                                <span style="color: #666;"><i class="far fa-calendar"></i> <?php echo Helpers::formatDate($dl['deadline_date'], 'M d, Y'); ?></span>
                            </div>
                            <?php 
                            $link = '#';
                            if ($dl['type'] == 'application') $link = 'application_view.php?id=' . $dl['item_id'];
                            elseif ($dl['type'] == 'scholarship') $link = 'scholarships.php';
                            elseif ($dl['type'] == 'task') $link = 'todos.php';
                            elseif ($dl['type'] == 'visa') $link = 'visa.php';
                            ?>
                            <a href="<?php echo $link; ?>" class="view-link" style="display: inline-block; margin-top: 0.8rem; color: #3498db; text-decoration: none; font-weight: 500;">View Details →</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Second Row – Tasks and Sessions -->
<div class="dashboard-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.8rem; margin-bottom: 2rem;">
    <!-- Pending Tasks -->
    <div class="dashboard-card" style="background: white; border-radius: 20px; padding: 1.8rem; box-shadow: 0 8px 25px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.3rem; font-weight: 600;">
                <i class="fas fa-tasks" style="color: #FF9800; margin-right: 0.5rem;"></i> Pending Tasks
            </h3>
            <a href="todos.php" class="btn-small" style="background: #f8f9fa; padding: 0.4rem 1rem; border-radius: 50px; color: #3498db; font-weight: 500; text-decoration: none;">Manage →</a>
        </div>
        <div class="scrollable-list" style="max-height: 250px; overflow-y: auto; padding-right: 0.5rem;">
            <?php if (empty($pending_tasks)): ?>
                <div class="empty-state" style="text-align: center; padding: 2rem 1rem;">
                    <i class="fas fa-check-circle fa-3x" style="color: #27ae60;"></i>
                    <p style="color: #666; margin-top: 0.5rem;">No pending tasks. Great job! 🎉</p>
                </div>
            <?php else: ?>
                <div class="tasks-list">
                    <?php foreach ($pending_tasks as $task): ?>
                        <div style="border-bottom: 1px solid #f0f0f0; padding: 1rem 0; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 1rem;"><?php echo htmlspecialchars($task['title']); ?></strong>
                                <?php if ($task['due_date']): ?>
                                    <br><small style="color: #999;"><i class="far fa-clock"></i> Due: <?php echo Helpers::formatDate($task['due_date']); ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="priority-badge priority-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Upcoming Counselling Sessions -->
    <div class="dashboard-card" style="background: white; border-radius: 20px; padding: 1.8rem; box-shadow: 0 8px 25px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.3rem; font-weight: 600;">
                <i class="fas fa-calendar-check" style="color: #2196F3; margin-right: 0.5rem;"></i> Counselling Sessions
            </h3>
            <a href="sessions.php" class="btn-small" style="background: #f8f9fa; padding: 0.4rem 1rem; border-radius: 50px; color: #3498db; font-weight: 500; text-decoration: none;">Book →</a>
        </div>
        <div class="scrollable-list" style="max-height: 250px; overflow-y: auto; padding-right: 0.5rem;">
            <?php if (empty($upcoming_sessions)): ?>
                <div class="empty-state" style="text-align: center; padding: 2rem 1rem;">
                    <i class="fas fa-calendar-plus fa-3x" style="color: #ddd;"></i>
                    <p style="color: #666; margin-top: 0.5rem;">No upcoming sessions.</p>
                    <a href="sessions.php" class="btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.6rem 1.5rem; border-radius: 50px; text-decoration: none; font-weight: 500; display: inline-block; margin-top: 0.5rem;">Book one now</a>
                </div>
            <?php else: ?>
                <div class="sessions-list">
                    <?php foreach ($upcoming_sessions as $session): 
                        $counselor_name = $session['first_name'] ? $session['first_name'] . ' ' . $session['last_name'] : $session['counselor_name'];
                    ?>
                        <div style="border-bottom: 1px solid #f0f0f0; padding: 1rem 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="font-size: 1rem;"><?php echo Helpers::formatDate($session['scheduled_at'], 'M d, Y g:i A'); ?></strong>
                                <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.3rem 1rem; border-radius: 50px; font-size: 0.8rem;"><?php echo ucfirst($session['session_type']); ?></span>
                            </div>
                            <small style="color: #666;">with <?php echo htmlspecialchars($counselor_name); ?></small>
                            <?php if ($session['meeting_link']): ?>
                                <br><a href="<?php echo $session['meeting_link']; ?>" target="_blank" style="color: #3498db; text-decoration: none; font-size: 0.9rem;">Join Meeting →</a>
                            <?php endif; ?>
                            <a href="/studysphere/public/download_ics.php?id=<?php echo $session['session_id']; ?>" class="btn-small" style="display: inline-block; margin-top: 0.5rem; color: #27ae60; text-decoration: none;">
                                <i class="fas fa-calendar-plus"></i> Add to Calendar
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions Grid - 4 per row, 5 rows = 20 buttons -->
<div class="dashboard-card" style="background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 8px 25px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03); margin-bottom: 2rem;">
    <h3 style="margin: 0 0 1.5rem 0; font-size: 1.3rem; font-weight: 600;">
        <i class="fas fa-bolt" style="color: #f39c12; margin-right: 0.5rem;"></i> Quick Actions
    </h3>
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
        <!-- Row 1 -->
        <a href="applications.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-plus-circle" style="font-size: 1.8rem; color: #4CAF50;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">New Application</strong>
                <small style="color: #666;">Start now</small>
            </div>
        </a>
        <a href="profile.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-user-edit" style="font-size: 1.8rem; color: #2196F3;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Edit Profile</strong>
                <small style="color: #666;">Update info</small>
            </div>
        </a>
        <a href="education.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-graduation-cap" style="font-size: 1.8rem; color: #9C27B0;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Add Education</strong>
                <small style="color: #666;">History</small>
            </div>
        </a>
        <a href="uploads.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-upload" style="font-size: 1.8rem; color: #FF9800;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Upload Docs</strong>
                <small style="color: #666;">Transcripts</small>
            </div>
        </a>
        
        <!-- Row 2 -->
        <a href="tests.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-file-alt" style="font-size: 1.8rem; color: #3498db;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Test Scores</strong>
                <small style="color: #666;">Add scores</small>
            </div>
        </a>
        <a href="achievements.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-trophy" style="font-size: 1.8rem; color: #f39c12;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Achievements</strong>
                <small style="color: #666;">Extracurriculars</small>
            </div>
        </a>
        <a href="finances.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-coins" style="font-size: 1.8rem; color: #27ae60;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Finances</strong>
                <small style="color: #666;">Budget & expenses</small>
            </div>
        </a>
        <a href="visa_documents.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-folder-open" style="font-size: 1.8rem; color: #8e44ad;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Visa Docs</strong>
                <small style="color: #666;">Upload</small>
            </div>
        </a>
        
        <!-- Row 3 -->
        <a href="scholarships.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-award" style="font-size: 1.8rem; color: #f1c40f;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Scholarships</strong>
                <small style="color: #666;">Funding</small>
            </div>
        </a>
        <a href="jobs.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-briefcase" style="font-size: 1.8rem; color: #e67e22;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Part-Time Jobs</strong>
                <small style="color: #666;">Work</small>
            </div>
        </a>
        <a href="program_recommendations.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-star" style="font-size: 1.8rem; color: #f39c12;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Recommendations</strong>
                <small style="color: #666;">Matches</small>
            </div>
        </a>
        <a href="visa.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-passport" style="font-size: 1.8rem; color: #2c3e50;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Visa Tracker</strong>
                <small style="color: #666;">Status</small>
            </div>
        </a>
        
        <!-- Row 4 -->
        <a href="exam_resources.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-book-open" style="font-size: 1.8rem; color: #27ae60;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Exam Prep</strong>
                <small style="color: #666;">Materials</small>
            </div>
        </a>
        <a href="essays.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-pen" style="font-size: 1.8rem; color: #8e44ad;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Essay Review</strong>
                <small style="color: #666;">Feedback</small>
            </div>
        </a>
        <a href="budget_planning.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-chart-pie" style="font-size: 1.8rem; color: #16a085;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Budget</strong>
                <small style="color: #666;">Planning</small>
            </div>
        </a>
        <a href="milestones.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-trophy" style="font-size: 1.8rem; color: #f1c40f;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Milestones</strong>
                <small style="color: #666;">Achievements</small>
            </div>
        </a>
        
        <!-- Row 5 -->
        <a href="sessions.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-calendar-alt" style="font-size: 1.8rem; color: #e67e22;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Counselling</strong>
                <small style="color: #666;">Book session</small>
            </div>
        </a>
        <a href="recommenders.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-user-plus" style="font-size: 1.8rem; color: #16a085;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Recommenders</strong>
                <small style="color: #666;">Add</small>
            </div>
        </a>
        <a href="todos.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-check-square" style="font-size: 1.8rem; color: #e74c3c;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">To-Do List</strong>
                <small style="color: #666;">Tasks</small>
            </div>
        </a>
        <a href="messages.php" class="action-btn" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; text-decoration: none; color: #2c3e50; border: 2px solid transparent; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
            <i class="fas fa-envelope" style="font-size: 1.8rem; color: #3498db;"></i>
            <div>
                <strong style="font-size: 1rem; display: block;">Messages</strong>
                <small style="color: #666;">Inbox</small>
            </div>
        </a>
    </div>
</div>

<!-- Application Status Summary (only if there are applications) -->
<?php if (($stats['total_applications'] ?? 0) > 0): ?>
<div class="dashboard-card" style="background: white; border-radius: 20px; padding: 1.8rem; box-shadow: 0 8px 25px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03);">
    <h3 style="margin: 0 0 1.5rem 0; font-size: 1.3rem; font-weight: 600;">
        <i class="fas fa-chart-pie" style="color: #3498db; margin-right: 0.5rem;"></i> Application Status Summary
    </h3>
    <div style="display: flex; flex-wrap: wrap; gap: 2rem; align-items: center;">
        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="status-badge status-draft" style="display: inline-block; width: 16px; height: 16px; padding: 0; border-radius: 4px;"></span>
                <span><strong>Draft:</strong> <?php echo $stats['draft'] ?? 0; ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="status-badge status-submitted" style="display: inline-block; width: 16px; height: 16px; padding: 0; border-radius: 4px;"></span>
                <span><strong>Submitted:</strong> <?php echo $stats['submitted'] ?? 0; ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="status-badge status-under_review" style="display: inline-block; width: 16px; height: 16px; padding: 0; border-radius: 4px;"></span>
                <span><strong>Under Review:</strong> <?php echo $stats['under_review'] ?? 0; ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="status-badge status-accepted" style="display: inline-block; width: 16px; height: 16px; padding: 0; border-radius: 4px;"></span>
                <span><strong>Accepted:</strong> <?php echo $stats['accepted'] ?? 0; ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="status-badge status-rejected" style="display: inline-block; width: 16px; height: 16px; padding: 0; border-radius: 4px;"></span>
                <span><strong>Rejected:</strong> <?php echo $stats['rejected'] ?? 0; ?></span>
            </div>
        </div>
        <a href="applications.php" class="btn-secondary" style="background: #f8f9fa; padding: 0.6rem 1.5rem; border-radius: 50px; color: #2c3e50; text-decoration: none; font-weight: 500; margin-left: auto;">View All Applications</a>
    </div>
</div>
<?php endif; ?>

<style>
/* Base styles */
.stat-card:hover, .action-btn:hover, .btn-small:hover, .view-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

/* Header buttons hover effect */
.btn-action:hover {
    background: #f39c12 !important;
    border-color: #f39c12 !important;
    color: white !important;
}

/* Status badges */
.status-badge {
    display: inline-block;
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}
.status-draft { background: #6c757d; color: white; }
.status-submitted { background: #007bff; color: white; }
.status-under_review { background: #ffc107; color: #333; }
.status-accepted { background: #28a745; color: white; }
.status-rejected { background: #dc3545; color: white; }

/* Priority badges */
.priority-badge {
    display: inline-block;
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}
.priority-high { background: #dc3545; color: white; }
.priority-medium { background: #ffc107; color: #333; }
.priority-low { background: #28a745; color: white; }

/* Scrollable lists - ensure consistent height */
.scrollable-list {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

/* Custom scrollbar for WebKit browsers */
.scrollable-list::-webkit-scrollbar {
    width: 6px;
}
.scrollable-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.scrollable-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}
.scrollable-list::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .quick-actions-grid {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}

@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 1fr !important;
    }
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    .quick-actions-grid {
        grid-template-columns: 1fr !important;
    }
    .action-btn {
        padding: 0.8rem !important;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>