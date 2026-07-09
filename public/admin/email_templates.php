<?php
require_once '../../app/includes/init.php';
requireAdmin();

$page_title = "Email Templates";
require_once '../../app/templates/header.php';

$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_template']) || isset($_POST['edit_template'])) {
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $name = Helpers::sanitize($_POST['name']);
        $subject = Helpers::sanitize($_POST['subject']);
        $body = $_POST['body']; // Allow HTML, not sanitized with strip_tags
        $variables = Helpers::sanitize($_POST['variables'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Convert variables string to JSON array
        $var_array = array_filter(array_map('trim', explode(',', $variables)));
        $variables_json = json_encode($var_array);

        $data = [
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'variables' => $variables_json,
            'is_active' => $is_active,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($template_id > 0 && isset($_POST['edit_template'])) {
            $db->update('email_templates', $data, "template_id = $template_id");
            $success = "Template updated successfully.";
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->insert('email_templates', $data);
            $success = "Template added successfully.";
        }
    } elseif (isset($_POST['delete_template'])) {
        $template_id = intval($_POST['template_id']);
        $db->query("DELETE FROM email_templates WHERE template_id = $template_id");
        $success = "Template deleted.";
    }
}

$templates = $db->fetchAll("SELECT * FROM email_templates ORDER BY name");
?>

<div class="page-header">
    <h1><i class="fas fa-envelope-open-text"></i> Email Templates</h1>
    <button class="btn btn-primary" onclick="showAddModal()">
        <i class="fas fa-plus"></i> New Template
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="dashboard-card">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Subject</th>
                <th>Variables</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $tpl): 
                $vars = json_decode($tpl['variables'], true);
                $var_str = is_array($vars) ? implode(', ', $vars) : '';
            ?>
                <tr>
                    <td><?php echo $tpl['template_id']; ?></td>
                    <td><?php echo htmlspecialchars($tpl['name']); ?></td>
                    <td><?php echo htmlspecialchars($tpl['subject']); ?></td>
                    <td><small><?php echo htmlspecialchars($var_str); ?></small></td>
                    <td><?php echo $tpl['is_active'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <button class="btn btn-small btn-primary" onclick="editTemplate(<?php echo htmlspecialchars(json_encode($tpl)); ?>)">Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this template?');">
                            <input type="hidden" name="template_id" value="<?php echo $tpl['template_id']; ?>">
                            <button type="submit" name="delete_template" class="btn btn-small btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div id="templateModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add Template</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="templateForm">
            <input type="hidden" name="template_id" id="template_id" value="">
            <div class="form-group">
                <label for="name">Template Name *</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="e.g., application_received">
            </div>
            <div class="form-group">
                <label for="subject">Email Subject *</label>
                <input type="text" name="subject" id="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="body">Email Body (HTML allowed)</label>
                <textarea name="body" id="body" class="form-control" rows="8" required></textarea>
            </div>
            <div class="form-group">
                <label for="variables">Variables (comma-separated, e.g., {{user_name}}, {{application_id}})</label>
                <input type="text" name="variables" id="variables" class="form-control" placeholder="user_name, application_id, program_name">
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="is_active" id="is_active" value="1" checked> Active
                </label>
            </div>
            <button type="submit" name="add_template" id="submitBtn" class="btn btn-primary btn-block">Save Template</button>
        </form>
    </div>
</div>

<script>
function showAddModal() {
    document.getElementById('modalTitle').innerText = 'Add Template';
    document.getElementById('template_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('subject').value = '';
    document.getElementById('body').value = '';
    document.getElementById('variables').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('submitBtn').name = 'add_template';
    document.getElementById('templateModal').style.display = 'block';
}

function editTemplate(tpl) {
    document.getElementById('modalTitle').innerText = 'Edit Template';
    document.getElementById('template_id').value = tpl.template_id;
    document.getElementById('name').value = tpl.name;
    document.getElementById('subject').value = tpl.subject;
    document.getElementById('body').value = tpl.body;
    let vars = '';
    try {
        let v = JSON.parse(tpl.variables);
        vars = Array.isArray(v) ? v.join(', ') : '';
    } catch(e) {}
    document.getElementById('variables').value = vars;
    document.getElementById('is_active').checked = tpl.is_active == 1;
    document.getElementById('submitBtn').name = 'edit_template';
    document.getElementById('templateModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('templateModal').style.display = 'none';
}
</script>

<?php require_once '../../app/templates/footer.php'; ?>