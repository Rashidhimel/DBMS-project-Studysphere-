<?php
require_once '../../app/includes/init.php';
requireStudent();

$page_title = "Manage Addresses";
require_once '../../app/templates/header.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
$student_id = $auth->getStudentId();
$db = new Database();

$addresses = $db->fetchAll(
    "SELECT * FROM addresses WHERE user_id = ? ORDER BY 
     CASE address_type 
        WHEN 'permanent' THEN 1 
        WHEN 'home' THEN 2 
        WHEN 'mailing' THEN 3 
        ELSE 4 
     END, created_at DESC",
    [$user['user_id']]
);

$countries = Helpers::getCountries();
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_address'])) {
        $address_type = Helpers::sanitize($_POST['address_type']);
        $country_code = Helpers::sanitize($_POST['country_code']);
        $region = Helpers::sanitize($_POST['region'] ?? '');
        $city = Helpers::sanitize($_POST['city'] ?? '');
        $postal_code = Helpers::sanitize($_POST['postal_code'] ?? '');
        $address_line1 = Helpers::sanitize($_POST['address_line1'] ?? '');
        $address_line2 = Helpers::sanitize($_POST['address_line2'] ?? '');

        $db->insert('addresses', [
            'user_id' => $user['user_id'],
            'address_type' => $address_type,
            'country_code' => $country_code,
            'region' => $region,
            'city' => $city,
            'postal_code' => $postal_code,
            'address_line1' => $address_line1,
            'address_line2' => $address_line2,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // If this is a permanent address, update student's country_from
        if ($address_type == 'permanent') {
            $db->update('students', 
                ['country_from' => $country_code],
                "student_id = " . $student_id
            );
        }
        
        $success = "Address added successfully!";
        
    } elseif (isset($_POST['edit_address'])) {
        $address_id = intval($_POST['address_id']);
        $address_type = Helpers::sanitize($_POST['address_type']);
        $country_code = Helpers::sanitize($_POST['country_code']);
        $region = Helpers::sanitize($_POST['region'] ?? '');
        $city = Helpers::sanitize($_POST['city'] ?? '');
        $postal_code = Helpers::sanitize($_POST['postal_code'] ?? '');
        $address_line1 = Helpers::sanitize($_POST['address_line1'] ?? '');
        $address_line2 = Helpers::sanitize($_POST['address_line2'] ?? '');

        $db->update('addresses', [
            'address_type' => $address_type,
            'country_code' => $country_code,
            'region' => $region,
            'city' => $city,
            'postal_code' => $postal_code,
            'address_line1' => $address_line1,
            'address_line2' => $address_line2
        ], "address_id = $address_id");
        
        // If this is a permanent address, update student's country_from
        if ($address_type == 'permanent') {
            $db->update('students', 
                ['country_from' => $country_code],
                "student_id = " . $student_id
            );
        }
        
        $success = "Address updated successfully!";
        
    } elseif (isset($_POST['delete_address'])) {
        $address_id = intval($_POST['address_id']);
        $address = $db->fetchOne("SELECT * FROM addresses WHERE address_id = ?", [$address_id]);
        
        $db->query("DELETE FROM addresses WHERE address_id = ?", [$address_id]);
        
        // If deleted address was permanent, clear student's country_from
        if ($address && $address['address_type'] == 'permanent') {
            $db->update('students', 
                ['country_from' => null],
                "student_id = " . $student_id
            );
        }
        
        $success = "Address deleted successfully!";
    }
    
    // Refresh addresses
    $addresses = $db->fetchAll(
        "SELECT * FROM addresses WHERE user_id = ? ORDER BY 
         CASE address_type 
            WHEN 'permanent' THEN 1 
            WHEN 'home' THEN 2 
            WHEN 'mailing' THEN 3 
            ELSE 4 
         END, created_at DESC",
        [$user['user_id']]
    );
}

// For editing, get single address
$edit_address = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_address = $db->fetchOne("SELECT * FROM addresses WHERE address_id = ? AND user_id = ?", [$edit_id, $user['user_id']]);
}
?>

<div class="page-header">
    <h1><i class="fas fa-map-marker-alt"></i> Manage Addresses</h1>
    <p>Add and manage your addresses</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- Address Form -->
    <div class="dashboard-card">
        <h3><?php echo $edit_address ? 'Edit Address' : 'Add New Address'; ?></h3>
        <form method="POST">
            <?php if ($edit_address): ?>
                <input type="hidden" name="address_id" value="<?php echo $edit_address['address_id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="address_type">Address Type *</label>
                <select name="address_type" id="address_type" class="form-control" required>
                    <option value="">Select Type</option>
                    <option value="home" <?php echo ($edit_address && $edit_address['address_type'] == 'home') ? 'selected' : ''; ?>>Home</option>
                    <option value="permanent" <?php echo ($edit_address && $edit_address['address_type'] == 'permanent') ? 'selected' : ''; ?>>Permanent</option>
                    <option value="mailing" <?php echo ($edit_address && $edit_address['address_type'] == 'mailing') ? 'selected' : ''; ?>>Mailing</option>
                    <option value="other" <?php echo ($edit_address && $edit_address['address_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <small class="text-muted">Permanent address will be used as your country of origin</small>
            </div>
            
            <div class="form-group">
                <label for="country_code">Country *</label>
                <select name="country_code" id="country_code" class="form-control" required>
                    <option value="">Select Country</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?php echo $c['iso2']; ?>" 
                            <?php echo ($edit_address && $edit_address['country_code'] == $c['iso2']) ? 'selected' : ''; ?>>
                            <?php echo $c['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="address_line1">Address Line 1 *</label>
                <input type="text" name="address_line1" id="address_line1" class="form-control" required
                       value="<?php echo $edit_address ? htmlspecialchars($edit_address['address_line1']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="address_line2">Address Line 2</label>
                <input type="text" name="address_line2" id="address_line2" class="form-control"
                       value="<?php echo $edit_address ? htmlspecialchars($edit_address['address_line2']) : ''; ?>">
            </div>
            
            <div class="form-row" style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label for="city">City *</label>
                    <input type="text" name="city" id="city" class="form-control" required
                           value="<?php echo $edit_address ? htmlspecialchars($edit_address['city']) : ''; ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="region">State/Province</label>
                    <input type="text" name="region" id="region" class="form-control"
                           value="<?php echo $edit_address ? htmlspecialchars($edit_address['region']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="postal_code">Postal Code</label>
                <input type="text" name="postal_code" id="postal_code" class="form-control"
                       value="<?php echo $edit_address ? htmlspecialchars($edit_address['postal_code']) : ''; ?>">
            </div>
            
            <button type="submit" name="<?php echo $edit_address ? 'edit_address' : 'add_address'; ?>" 
                    class="btn btn-primary btn-block">
                <?php echo $edit_address ? 'Update Address' : 'Add Address'; ?>
            </button>
            <?php if ($edit_address): ?>
                <a href="addresses.php" class="btn btn-secondary btn-block mt-2">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Address List -->
    <div class="dashboard-card" style="grid-column: span 2;">
        <h3>Your Addresses</h3>
        <?php if (empty($addresses)): ?>
            <p class="text-muted">No addresses added yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Address</th>
                        <th>City</th>
                        <th>Country</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($addresses as $addr): 
                        $country_name = '';
                        if ($addr['country_code']) {
                            $c = $db->fetchOne("SELECT name FROM countries WHERE iso2 = ?", [$addr['country_code']]);
                            $country_name = $c ? $c['name'] : $addr['country_code'];
                        }
                    ?>
                        <tr>
                            <td>
                                <?php echo ucfirst($addr['address_type']); ?>
                                <?php if ($addr['address_type'] == 'permanent'): ?>
                                    <br><small class="text-success">(Country of Origin)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($addr['address_line1']); ?>
                                <?php if ($addr['address_line2']): ?><br><?php echo htmlspecialchars($addr['address_line2']); ?><?php endif; ?>
                                <?php if ($addr['postal_code']): ?><br>Postal: <?php echo $addr['postal_code']; ?><?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($addr['city']); ?>
                                <?php if ($addr['region']): ?><br><small><?php echo $addr['region']; ?></small><?php endif; ?>
                            </td>
                            <td><?php echo $country_name; ?></td>
                            <td>
                                <a href="?edit=<?php echo $addr['address_id']; ?>" class="btn btn-small btn-primary">Edit</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this address?');">
                                    <input type="hidden" name="address_id" value="<?php echo $addr['address_id']; ?>">
                                    <button type="submit" name="delete_address" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../app/templates/footer.php'; ?>