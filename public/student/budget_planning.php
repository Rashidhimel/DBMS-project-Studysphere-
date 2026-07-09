<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Budget Planning";
require_once '../../app/templates/header.php';

$auth = new Auth();
$student_id = $auth->getStudentId();
$db = new Database();

// Get student and user_id
$student = $db->fetchOne("SELECT * FROM students WHERE student_id = ?", [$student_id]);
if (!$student) {
    echo "Student record not found.";
    exit();
}

$user_id = $student['user_id'];

// Fetch accounts using the correct user_id
$accounts = $db->fetchAll("SELECT * FROM finance_accounts WHERE user_id = ?", [$user_id]);

// Fetch transactions
$transactions = $db->fetchAll("
    SELECT t.*, ft.name as type_name, fc.name as category_name
    FROM finance_transactions t
    LEFT JOIN finance_types ft ON t.finance_type_id = ft.finance_type_id
    LEFT JOIN finance_categories fc ON t.category_id = fc.category_id
    WHERE t.account_id IN (SELECT account_id FROM finance_accounts WHERE user_id = ?)
    ORDER BY t.transaction_date DESC, t.transaction_id DESC
    LIMIT 50
", [$user_id]);

// Calculate totals
$income_total = 0;
$expense_total = 0;
foreach($transactions as $t) {
    if ($t['finance_type_id'] == 1) {
        $income_total += $t['amount'];
    } else if ($t['finance_type_id'] == 2) {
        $expense_total += $t['amount'];
    }
}

$balance = $income_total - $expense_total;

$error = ''; // will be used only for errors that don't redirect

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_budget'])) {
        $budget_min = floatval($_POST['budget_min']);
        $budget_max = floatval($_POST['budget_max']);
        
        if ($budget_min > $budget_max) {
            $error = 'Minimum budget cannot be greater than maximum budget';
        } else {
            $db->update('students', [
                'budget_min' => $budget_min,
                'budget_max' => $budget_max,
                'updated_at' => date('Y-m-d H:i:s')
            ], "student_id = $student_id");
            
            Helpers::redirect('budget_planning.php', 'Budget range updated successfully!');
        }
    }
    
    if (isset($_POST['add_transaction'])) {
        $account_id = intval($_POST['account_id']);
        $finance_type_id = intval($_POST['finance_type_id']);
        $amount = floatval($_POST['amount']);
        $description = Helpers::sanitize($_POST['description']);
        $transaction_date = Helpers::sanitize($_POST['transaction_date']);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        
        // Check if the account exists and belongs to the user
        $account_check = $db->fetchOne("
            SELECT * FROM finance_accounts 
            WHERE account_id = ? AND user_id = ?
        ", [$account_id, $user_id]);
        
        if (!$account_check) {
            $error = 'Invalid account selected';
        } else {
            // Insert transaction with all required fields
            $result = $db->insert('finance_transactions', [
                'account_id' => $account_id,
                'category_id' => $category_id,
                'finance_type_id' => $finance_type_id,
                'amount' => $amount,
                'currency' => 'USD',
                'exchange_rate' => null,
                'base_currency' => 'USD',
                'converted_amount' => $amount,
                'description' => $description,
                'transaction_date' => $transaction_date,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                Helpers::redirect('budget_planning.php', 'Transaction added successfully!');
            } else {
                $error = 'Failed to add transaction';
            }
        }
    }
    
    if (isset($_POST['create_account'])) {
        $account_name = Helpers::sanitize($_POST['account_name']);
        $currency = Helpers::sanitize($_POST['currency']);
        
        $result = $db->insert('finance_accounts', [
            'user_id' => $user_id,
            'account_name' => $account_name,
            'currency' => $currency,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            Helpers::redirect('budget_planning.php', 'Account created successfully!');
        } else {
            $error = 'Failed to create account';
        }
    }
}

// After potential redirects, we need to refresh data if there was an error (no redirect)
if (!empty($error)) {
    // Data already fetched above, no need to refetch
}
?>

<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> Budget Planning</h1>
    <p>Manage your study abroad finances with ease</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php
$flash = Helpers::getFlash();
if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
<?php endif; ?>

<!-- Overview Cards -->
<div class="overview-cards">
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, #2ecc71, #27ae60);">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($student['budget_min'] ?? 0); ?> - $<?php echo number_format($student['budget_max'] ?? 0); ?></h3>
            <p>Budget Range</p>
        </div>
    </div>
    
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($income_total); ?></h3>
            <p>Total Income</p>
        </div>
    </div>
    
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($expense_total); ?></h3>
            <p>Total Expenses</p>
        </div>
    </div>
    
    <div class="stat-card glass">
        <div class="stat-icon" style="background: linear-gradient(135deg, <?php echo $balance >= 0 ? '#9b59b6, #8e44ad' : '#e74c3c, #c0392b'; ?>);">
            <i class="fas fa-balance-scale"></i>
        </div>
        <div class="stat-content">
            <h3>$<?php echo number_format($balance); ?></h3>
            <p>Current Balance</p>
        </div>
    </div>
</div>

<!-- Budget Utilization Progress -->
<?php if (($student['budget_max'] ?? 0) > 0): ?>
<div class="utilization-card">
    <div class="utilization-header">
        <h3><i class="fas fa-chart-line"></i> Budget Utilization</h3>
        <span><?php echo round(($expense_total / $student['budget_max']) * 100, 1); ?>% of max</span>
    </div>
    <div class="progress-bar">
        <div class="progress-fill" style="width: <?php echo min(100, ($expense_total / $student['budget_max']) * 100); ?>%;"></div>
    </div>
    <div class="utilization-labels">
        <span>Min: $<?php echo number_format($student['budget_min'] ?? 0); ?></span>
        <span>Max: $<?php echo number_format($student['budget_max'] ?? 0); ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Main Grid -->
<div class="budget-grid">
    <!-- Left Column -->
    <div class="budget-column">
        <!-- Update Budget Range -->
        <div class="card modern-card">
            <div class="card-header">
                <h3><i class="fas fa-sliders-h"></i> Update Budget Range</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Minimum Budget ($)</label>
                            <input type="number" name="budget_min" min="0" step="any" 
                                   value="<?php echo $student['budget_min'] ?? ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Maximum Budget ($)</label>
                            <input type="number" name="budget_max" min="0" step="any" 
                                   value="<?php echo $student['budget_max'] ?? ''; ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="update_budget" class="btn btn-primary btn-block">Update Budget</button>
                </form>
            </div>
        </div>

        <!-- Budget Calculator -->
        <div class="card modern-card">
            <div class="card-header">
                <h3><i class="fas fa-calculator"></i> Budget Calculator</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Tuition Fee ($)</label>
                    <input type="number" id="tuition" placeholder="e.g., 30000" min="0" step="any">
                </div>
                <div class="form-group">
                    <label>Living Expenses per month ($)</label>
                    <input type="number" id="living" placeholder="e.g., 1000" min="0" step="any">
                </div>
                <div class="form-group">
                    <label>Program Duration (months)</label>
                    <input type="number" id="duration" placeholder="e.g., 24" min="1">
                </div>
                <div class="form-group">
                    <label>Additional Costs ($)</label>
                    <input type="number" id="additional" placeholder="e.g., 2000" min="0" step="any">
                </div>
                <button onclick="calculateBudget()" class="btn btn-secondary btn-block">Calculate Total</button>
                
                <div id="calculationResult" class="calculation-result" style="display: none;">
                    <h4>Estimated Total Cost: <span id="totalCost">$0</span></h4>
                    <p id="budgetStatus"></p>
                </div>
            </div>
        </div>

        <!-- Create New Account -->
        <div class="card modern-card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Create New Account</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Account Name</label>
                            <input type="text" name="account_name" placeholder="e.g., Savings Account" required>
                        </div>
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="currency" required>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                                <option value="CAD">CAD</option>
                                <option value="AUD">AUD</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="create_account" class="btn btn-primary btn-block">Create Account</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="budget-column">
        <!-- Add Transaction -->
        <div class="card modern-card">
            <div class="card-header">
                <h3><i class="fas fa-exchange-alt"></i> Add Transaction</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Account</label>
                            <select name="account_id" required>
                                <option value="">Select Account</option>
                                <?php foreach($accounts as $account): ?>
                                    <option value="<?php echo $account['account_id']; ?>">
                                        <?php echo htmlspecialchars($account['account_name']); ?> 
                                        (<?php echo $account['currency']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if(empty($accounts)): ?>
                                <small class="text-muted">Create an account first</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select name="finance_type_id" required>
                                <option value="">Select Type</option>
                                <?php 
                                $types = $db->fetchAll("SELECT * FROM finance_types");
                                foreach($types as $type): ?>
                                    <option value="<?php echo $type['finance_type_id']; ?>">
                                        <?php echo $type['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount ($)</label>
                            <input type="number" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Category (Optional)</label>
                        <select name="category_id">
                            <option value="">No Category</option>
                            <?php 
                            $categories = $db->fetchAll("SELECT * FROM finance_categories ORDER BY name");
                            foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="e.g., Monthly savings, Book purchase">
                    </div>
                    
                    <button type="submit" name="add_transaction" class="btn btn-primary btn-block">Add Transaction</button>
                </form>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card modern-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Transactions</h3>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <p class="text-muted">No transactions recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($transactions as $t): ?>
                                    <tr>
                                        <td><?php echo Helpers::formatDate($t['transaction_date']); ?></td>
                                        <td><?php echo htmlspecialchars($t['description']); ?></td>
                                        <td>
                                            <span class="transaction-type type-<?php echo strtolower($t['type_name']); ?>">
                                                <?php echo $t['type_name']; ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo $t['finance_type_id'] == 1 ? 'income' : 'expense'; ?>">
                                            $<?php echo number_format($t['amount'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function calculateBudget() {
    const tuition = parseFloat(document.getElementById('tuition').value) || 0;
    const living = parseFloat(document.getElementById('living').value) || 0;
    const duration = parseFloat(document.getElementById('duration').value) || 0;
    const additional = parseFloat(document.getElementById('additional').value) || 0;
    
    const total = tuition + (living * duration) + additional;
    
    document.getElementById('totalCost').textContent = '$' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const budgetMin = <?php echo $student['budget_min'] ?? 0; ?>;
    const budgetMax = <?php echo $student['budget_max'] ?? 0; ?>;
    
    let status = '';
    if (budgetMax > 0) {
        if (total <= budgetMax && total >= budgetMin) {
            status = '✅ Within your budget range';
        } else if (total < budgetMin) {
            status = '✅ Below your minimum budget';
        } else {
            const over = total - budgetMax;
            status = '⚠️ $' + over.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' over your maximum budget';
        }
    } else {
        status = 'Set your budget range above for comparison';
    }
    
    document.getElementById('budgetStatus').textContent = status;
    document.getElementById('calculationResult').style.display = 'block';
}
</script>

<style>
/* Modern design */
.page-header {
    margin-bottom: 2rem;
}
.page-header h1 {
    font-size: 2rem;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}
.page-header p {
    color: #7f8c8d;
    font-size: 1rem;
}

/* Overview cards */
.overview-cards {
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
    font-size: 1.5rem;
    margin: 0 0 0.3rem 0;
    color: #2c3e50;
}
.stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

/* Glassmorphism effect */
.glass {
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

/* Budget utilization */
.utilization-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
.utilization-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.utilization-header h3 {
    font-size: 1.2rem;
    color: #2c3e50;
    margin: 0;
}
.utilization-header span {
    font-weight: 600;
    color: #3498db;
}
.progress-bar {
    height: 10px;
    background: #ecf0f1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2ecc71);
    border-radius: 10px;
    transition: width 0.3s;
}
.utilization-labels {
    display: flex;
    justify-content: space-between;
    color: #7f8c8d;
    font-size: 0.85rem;
}

/* Modern grid */
.budget-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}
.budget-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Modern cards */
.modern-card {
    border: none;
    border-radius: 20px;
    background: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.modern-card:hover {
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}
.card-header {
    padding: 1.5rem 1.5rem 0 1.5rem;
}
.card-header h3 {
    font-size: 1.2rem;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.card-header h3 i {
    color: #3498db;
}
.card-body {
    padding: 1.5rem;
}

/* Form elements */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2c3e50;
    font-weight: 500;
    font-size: 0.9rem;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}
.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: #7f8c8d;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 40px;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    font-size: 0.95rem;
}
.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(52,152,219,0.3);
}
.btn-secondary {
    background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    color: white;
}
.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(127,140,141,0.3);
}
.btn-block {
    width: 100%;
}

/* Transaction table */
.table {
    width: 100%;
    border-collapse: collapse;
}
.table th {
    text-align: left;
    padding: 0.75rem 1rem;
    background: #f8f9fa;
    color: #2c3e50;
    font-weight: 600;
    font-size: 0.9rem;
}
.table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #ecf0f1;
}
.table tr:hover td {
    background: #f8f9fa;
}
.transaction-type {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}
.type-income {
    background: #e8f5e9;
    color: #2e7d32;
}
.type-expense {
    background: #ffebee;
    color: #c62828;
}
.income {
    color: #27ae60;
    font-weight: 600;
}
.expense {
    color: #e74c3c;
    font-weight: 600;
}

/* Calculator result */
.calculation-result {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
}
.calculation-result h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}
.calculation-result p {
    margin: 0;
    color: #7f8c8d;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}
.alert-success {
    background: #e8f5e9;
    color: #2e7d32;
    border-left: 4px solid #2e7d32;
}
.alert-error {
    background: #ffebee;
    color: #c62828;
    border-left: 4px solid #c62828;
}

/* Responsive */
@media (max-width: 1200px) {
    .overview-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .budget-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .overview-cards {
        grid-template-columns: 1fr;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../../app/templates/footer.php'; ?>