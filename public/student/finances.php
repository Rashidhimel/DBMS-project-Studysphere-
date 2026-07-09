<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Financial Management";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

// Get student's budget range from students table
$student = $db->fetchOne("SELECT budget_min, budget_max FROM students WHERE student_id = ?", [$student_id]);

// Application fees paid total
$total_fees_paid = $db->fetchOne("
    SELECT COALESCE(SUM(fp.amount_paid), 0) as total
    FROM fee_payments fp
    JOIN applications a ON fp.application_id = a.application_id
    WHERE a.student_id = ? AND fp.status = 'completed'
", [$student_id])['total'];

// Scholarships awarded total (using scholarship amount if awarded_amount not set)
$total_scholarships = $db->fetchOne("
    SELECT COALESCE(SUM(COALESCE(ss.awarded_amount, s.amount)), 0) as total
    FROM student_scholarships ss
    JOIN scholarships s ON ss.scholarship_id = s.scholarship_id
    WHERE ss.student_id = ? AND ss.status = 'awarded'
", [$student_id])['total'];

// Total income from finance_transactions
$total_income = $db->fetchOne("
    SELECT COALESCE(SUM(ft.amount), 0) as total
    FROM finance_transactions ft
    JOIN finance_accounts fa ON ft.account_id = fa.account_id
    WHERE fa.user_id = ? AND ft.finance_type_id = 1
", [$user['user_id']])['total'];

// Total expenses from finance_transactions
$total_expenses = $db->fetchOne("
    SELECT COALESCE(SUM(ft.amount), 0) as total
    FROM finance_transactions ft
    JOIN finance_accounts fa ON ft.account_id = fa.account_id
    WHERE fa.user_id = ? AND ft.finance_type_id = 2
", [$user['user_id']])['total'];

// Application fees breakdown (with payment status)
$application_fees = $db->fetchAll("
    SELECT a.application_id, a.applied_at, p.program_name, u.name as university_name,
           af.amount as fee_amount, af.currency as fee_currency,
           fp.amount_paid, fp.paid_at, fp.status as payment_status
    FROM applications a
    JOIN programs p ON a.program_id = p.program_id
    JOIN universities u ON a.university_id = u.university_id
    LEFT JOIN application_fees af ON a.program_id = af.program_id
    LEFT JOIN fee_payments fp ON a.application_id = fp.application_id
    WHERE a.student_id = ? AND a.deleted_at IS NULL
    ORDER BY a.applied_at DESC
", [$student_id]);

// Recent transactions (income/expenses from budget_planning)
$transactions = $db->fetchAll("
    SELECT ft.transaction_id, ft.transaction_date, ft.description, ft.amount,
           ft.finance_type_id, fc.name as category_name, ft2.name as type_name
    FROM finance_transactions ft
    JOIN finance_accounts fa ON ft.account_id = fa.account_id
    LEFT JOIN finance_categories fc ON ft.category_id = fc.category_id
    LEFT JOIN finance_types ft2 ON ft.finance_type_id = ft2.finance_type_id
    WHERE fa.user_id = ?
    ORDER BY ft.transaction_date DESC, ft.transaction_id DESC
    LIMIT 15
", [$user['user_id']]);

// Awarded scholarships
$scholarships = $db->fetchAll("
    SELECT ss.*, s.name as scholarship_name, s.amount as scholarship_amount,
           p.program_name, u.name as university_name
    FROM student_scholarships ss
    JOIN scholarships s ON ss.scholarship_id = s.scholarship_id
    JOIN programs p ON s.program_id = p.program_id
    JOIN universities u ON p.university_id = u.university_id
    WHERE ss.student_id = ? AND ss.status = 'awarded'
    ORDER BY ss.awarded_at DESC
", [$student_id]);

$flash = Helpers::getFlash();
?>

<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> Financial Management</h1>
    <p>Track application fees, scholarships, and your personal finances</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50, #2E7D32);">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($total_fees_paid, 2); ?></h3>
            <p>Application Fees Paid</p>
        </div>
    </div>
    
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, #2196F3, #0D47A1);">
            <i class="fas fa-gift"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($total_scholarships, 2); ?></h3>
            <p>Scholarships Awarded</p>
        </div>
    </div>
    
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #E65100);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($total_income, 2); ?></h3>
            <p>Total Income</p>
        </div>
    </div>
    
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, #F44336, #B71C1C);">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($total_expenses, 2); ?></h3>
            <p>Total Expenses</p>
        </div>
    </div>
</div>

<!-- Main Grid -->
<div class="dashboard-grid">
    <!-- Left Column -->
    <div class="dashboard-column">
        <!-- Budget Overview Card -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Budget Overview</h3>
                <a href="budget_planning.php" class="btn btn-primary btn-sm" style="margin-left: auto;">
                    <i class="fas fa-edit"></i> Manage Budget
                </a>
            </div>
            <div class="card-body">
                <?php if (($student['budget_min'] ?? 0) > 0 || ($student['budget_max'] ?? 0) > 0): ?>
                    <div class="budget-range">
                        <div class="budget-item">
                            <span class="budget-label">Minimum Budget</span>
                            <span class="budget-value">$<?php echo number_format($student['budget_min'] ?? 0, 2); ?></span>
                        </div>
                        <div class="budget-item">
                            <span class="budget-label">Maximum Budget</span>
                            <span class="budget-value">$<?php echo number_format($student['budget_max'] ?? 0, 2); ?></span>
                        </div>
                    </div>
                    <div class="progress-section">
                        <div class="progress-bar">
                            <?php 
                            $used = ($student['budget_max'] > 0) ? ($total_expenses / $student['budget_max']) * 100 : 0;
                            ?>
                            <div class="progress-fill" style="width: <?php echo min(100, $used); ?>%;"></div>
                        </div>
                        <p class="progress-text">
                            <?php echo number_format($used, 1); ?>% of max budget used
                        </p>
                    </div>
                <?php else: ?>
                    <div class="empty-state small">
                        <p>No budget set yet.</p>
                        <a href="budget_planning.php" class="btn btn-secondary btn-sm">Set up budget</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Application Fees Card -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3>Application Fees</h3>
            </div>
            <div class="card-body">
                <?php if (empty($application_fees)): ?>
                    <p class="text-muted">No applications with fees.</p>
                <?php else: ?>
                    <div class="fee-list">
                        <?php foreach ($application_fees as $fee): ?>
                            <div class="fee-item">
                                <div class="fee-info">
                                    <h4><?php echo htmlspecialchars($fee['program_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($fee['university_name']); ?></p>
                                </div>
                                <div class="fee-amount">
                                    <?php if ($fee['fee_amount']): ?>
                                        <span class="amount"><?php echo $fee['fee_currency'] . ' ' . number_format($fee['fee_amount'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Free</span>
                                    <?php endif; ?>
                                </div>
                                <div class="fee-status">
                                    <?php if ($fee['payment_status'] == 'completed'): ?>
                                        <span class="status-badge status-paid">Paid</span>
                                    <?php elseif ($fee['fee_amount'] && !$fee['payment_status']): ?>
                                        <span class="status-badge status-pending">Pending</span>
                                        <a href="/studysphere/public/student/pay_fee.php?application_id=<?php echo $fee['application_id']; ?>" 
                                           class="btn-icon btn-primary" title="Pay now">
                                            <i class="fas fa-credit-card"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="status-badge status-waived">Waived</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="dashboard-column">
        <!-- Recent Transactions Card -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #2ecc71, #27ae60);">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <h3>Recent Transactions</h3>
                <a href="budget_planning.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="empty-state small">
                        <i class="fas fa-exchange-alt fa-2x"></i>
                        <p>No transactions yet.</p>
                        <a href="budget_planning.php" class="btn btn-secondary btn-sm">Add transaction</a>
                    </div>
                <?php else: ?>
                    <div class="transaction-list">
                        <?php foreach ($transactions as $txn): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon" style="background: <?php echo $txn['finance_type_id'] == 1 ? '#e8f5e9' : '#ffebee'; ?>">
                                    <i class="fas <?php echo $txn['finance_type_id'] == 1 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>" 
                                       style="color: <?php echo $txn['finance_type_id'] == 1 ? '#2e7d32' : '#c62828'; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <span class="transaction-desc"><?php echo htmlspecialchars($txn['description'] ?? 'No description'); ?></span>
                                    <span class="transaction-meta">
                                        <?php echo Helpers::formatDate($txn['transaction_date'], 'M d'); ?> • 
                                        <?php echo $txn['category_name'] ?? 'Uncategorized'; ?>
                                    </span>
                                </div>
                                <div class="transaction-amount <?php echo $txn['finance_type_id'] == 1 ? 'income' : 'expense'; ?>">
                                    <?php echo $txn['finance_type_id'] == 1 ? '+' : '-'; ?>$<?php echo number_format($txn['amount'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scholarships Awarded Card -->
        <div class="modern-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #f1c40f, #f39c12);">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3>Scholarships Awarded</h3>
            </div>
            <div class="card-body">
                <?php if (empty($scholarships)): ?>
                    <p class="text-muted">No scholarships awarded yet.</p>
                <?php else: ?>
                    <div class="scholarship-list">
                        <?php foreach ($scholarships as $sch): ?>
                            <div class="scholarship-item">
                                <div class="scholarship-header">
                                    <h4><?php echo htmlspecialchars($sch['scholarship_name']); ?></h4>
                                    <span class="scholarship-amount">$<?php echo number_format($sch['awarded_amount'] ?? $sch['scholarship_amount'], 2); ?></span>
                                </div>
                                <p class="scholarship-details">
                                    <i class="fas fa-university"></i> <?php echo htmlspecialchars($sch['university_name']); ?> • 
                                    <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($sch['program_name']); ?>
                                </p>
                                <p class="scholarship-date">Awarded: <?php echo Helpers::formatDate($sch['awarded_at'] ?? $sch['applied_at'], 'M d, Y'); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Stats grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.8rem;
    flex-shrink: 0;
}
.stat-content h3 {
    font-size: 1.8rem;
    margin: 0 0 0.3rem 0;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

/* Modern cards */
.modern-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.modern-card:hover {
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}
.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f0;
}
.card-icon {
    width: 45px;
    height: 45px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.4rem;
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}
.card-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
}
.card-body {
    padding: 1.5rem 2rem 2rem;
}

/* Dashboard grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

/* Budget range */
.budget-range {
    display: flex;
    justify-content: space-between;
    background: #f8f9fa;
    border-radius: 16px;
    padding: 1.2rem;
    margin-bottom: 1.5rem;
}
.budget-item {
    text-align: center;
}
.budget-label {
    display: block;
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-bottom: 0.3rem;
}
.budget-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2c3e50;
}
.progress-section {
    margin-top: 0.5rem;
}
.progress-bar {
    height: 8px;
    background: #ecf0f1;
    border-radius: 8px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2ecc71);
    border-radius: 8px;
    transition: width 0.3s;
}
.progress-text {
    text-align: right;
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-top: 0.3rem;
}

/* Fee list */
.fee-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.fee-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
    border-radius: 16px;
    padding: 1rem;
    transition: background 0.2s;
}
.fee-item:hover {
    background: #f0f4f8;
}
.fee-info h4 {
    margin: 0 0 0.2rem;
    font-size: 1rem;
    color: #2c3e50;
}
.fee-info p {
    margin: 0;
    font-size: 0.85rem;
    color: #7f8c8d;
}
.fee-amount .amount {
    font-weight: 600;
    color: #27ae60;
}
.fee-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.status-badge {
    padding: 0.2rem 0.8rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-paid {
    background: #e8f5e9;
    color: #2e7d32;
}
.status-pending {
    background: #fff3e0;
    color: #f57c00;
}
.status-waived {
    background: #e3f2fd;
    color: #1976d2;
}
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    color: #495057;
    transition: all 0.2s;
}
.btn-icon.btn-primary {
    background: #3498db;
    color: white;
}
.btn-icon.btn-primary:hover {
    background: #2980b9;
    transform: scale(1.05);
}

/* Transaction list */
.transaction-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
.transaction-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #f8fafc;
    border-radius: 16px;
    padding: 0.8rem;
    transition: background 0.2s;
}
.transaction-item:hover {
    background: #f0f4f8;
}
.transaction-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.transaction-details {
    flex: 1;
}
.transaction-desc {
    display: block;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.95rem;
}
.transaction-meta {
    font-size: 0.75rem;
    color: #7f8c8d;
}
.transaction-amount {
    font-weight: 600;
    font-size: 1rem;
}
.transaction-amount.income {
    color: #27ae60;
}
.transaction-amount.expense {
    color: #e74c3c;
}

/* Scholarship list */
.scholarship-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.scholarship-item {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1rem;
}
.scholarship-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.3rem;
}
.scholarship-header h4 {
    margin: 0;
    font-size: 1rem;
    color: #2c3e50;
}
.scholarship-amount {
    font-weight: 600;
    color: #27ae60;
}
.scholarship-details {
    margin: 0.3rem 0;
    font-size: 0.85rem;
    color: #7f8c8d;
}
.scholarship-details i {
    width: 16px;
    color: #3498db;
}
.scholarship-date {
    margin: 0.3rem 0 0;
    font-size: 0.75rem;
    color: #95a5a6;
}

/* Empty states */
.empty-state.small {
    text-align: center;
    padding: 1.5rem;
}
.empty-state p {
    color: #7f8c8d;
    margin-bottom: 0.8rem;
}

/* Buttons */
.btn {
    padding: 0.5rem 1rem;
    border-radius: 40px;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-decoration: none;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52,152,219,0.3);
}
.btn-secondary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
}
.btn-secondary:hover {
    background: #e9ecef;
}
.btn-sm {
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
}

/* Responsive */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>