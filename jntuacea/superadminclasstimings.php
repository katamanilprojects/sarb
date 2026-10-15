<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Class Timing Templates";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

// Handle form submissions for adding or deleting templates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $timing_id = $_POST['timing_id'] ?? null;
    $hours_data = $_POST['hours'] ?? [];

    if (!empty($_POST['delete_template'])) {
        // Delete the entire timing template by timing_id
        $result = $superadmin->deleteTimingTemplate($timing_id);
        $msg = $result['status'] == 1 ? "Template deleted successfully." : $result['error'];
    } elseif (!empty($timing_id) && !empty($hours_data)) {
        // Add or update the timing template in bulk
        $result = $superadmin->saveTimingTemplate($timing_id, $hours_data);
        $msg = $result['status'] == 1 ? "Template saved successfully." : $result['error'];
    }
}

// Fetch all timing templates grouped by timing_id
$timingTemplates = $superadmin->getAllTimingTemplates();

// Check if edit form should be shown based on timing_id in URL
$showEditForm = isset($_GET['edit']);
$editData = $showEditForm ? $superadmin->getTimingTemplateDetails($_GET['timing_id']) : null;
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Class Timing Templates</div>
        <div class="card-body">
            <!-- Add Template Form -->
            <button type="button" class="btn btn-primary mb-3" id="addTemplateBtn" <?= $showEditForm ? 'style="display: none;"' : ''; ?>>Add Timing Template</button>
            <div id="addForm" style="display: none;">
                <form action="superadminclasstimings.php" id="formtemplete" method="post">
                    <div class="form-group">
                        <label for="timing_id">Template ID:</label>
                        <input type="number" name="timing_id" id="timing_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="num_hours">Number of Hours:</label>
                        <input type="number" name="num_hours" id="num_hours" class="form-control" min="1" max="10" required>
                    </div>

                    <div id="hoursContainer" class="mt-3"></div>

                    <button type="button" id="generatebtn" style="display: none;" class="btn btn-secondary mt-3" onclick="generateHourInputs()">Generate Hour Fields</button>
                    <button type="button" id="canceladdbtn" style="display: none;" class="btn btn-danger mt-3">Cancel Adding</button>
                    &nbsp;
                    <button type="submit" id="addsubmitbtn" style="display: none;" class="btn btn-primary mt-3">Add Template</button>
                </form>
            </div>

            <!-- Edit Template Form -->
            <?php if ($showEditForm): ?>
                <div id="editForm">
                    <form action="superadminclasstimings.php" method="post">
                        <div class="form-group">
                            <label for="timing_id">Template ID:</label>
                            <input type="number" name="timing_id" id="timing_id" class="form-control" required value="<?= $editData['timing_id']; ?>" readonly>
                        </div>
                        <div id="edithoursContainer" class="mt-3">
                            <?php foreach ($editData['hours'] as $hourData): ?>
                                <div class="form-group row">
                                    <label class="col-sm-2 col-form-label">Hour <?= $hourData['hour'] ?>:</label>
                                    <div class="col-sm-2">
                                        <input type="text" name="hours[<?= $hourData['hour'] ?>][hour]" class="form-control" required value="<?= $hourData['hour'] ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <input type="text" name="hours[<?= $hourData['hour'] ?>][hour_desc]" class="form-control" value="<?= $hourData['hour_desc'] ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <input type="time" name="hours[<?= $hourData['hour'] ?>][start_time]" class="form-control" required value="<?= date("H:i", strtotime($hourData['start_time'])); ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <input type="time" name="hours[<?= $hourData['hour'] ?>][end_time]" class="form-control" required value="<?= date("H:i", strtotime($hourData['end_time'])); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a class="btn btn-danger mt-3" href="superadminclasstimings.php">Cancel Update</a> &nbsp;

                        <button type="submit" class="btn btn-primary mt-3">Update Template</button>
                        <input type="hidden" name="update_template" value="1">
                    </form>
                </div>
            <?php endif; ?>

            <hr>

            <?php if (!empty($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>

            <!-- List Existing Templates -->
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Template ID</th>
                        <th>Total Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timingTemplates as $template): ?>
                        <tr>
                            <td><?= $template['timing_id']; ?></td>
                            <td>
                                <?php
                                $count = count($template["hours"]);
                                echo '<table class="table table-bordered">';
                                echo '<tr><th colspan="3">No. of Hours: ' . $count . '</th></tr>';
                                if ($count > 0) {
                                    echo '<tr><th>Hour</th><th>Description</th><th>From</th><th>To</th></tr>';
                                    foreach ($template["hours"] as $timing) {
                                        echo '<tr>';
                                        echo '<td>' . $timing["hour"] . '</td>';
                                        echo '<td>' . $timing["hour_desc"] . '</td>';
                                        echo '<td>' . date("H:i A", strtotime($timing["start_time"])) . '</td>';
                                        echo '<td>' . date("H:i A", strtotime($timing["end_time"])) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                                echo '</table>';
                                ?>
                            </td>
                            <td>
                                <a href="superadminclasstimings.php?edit=1&timing_id=<?= $template['timing_id']; ?>" class="btn btn-primary">Edit</a>
                                <form action="superadminclasstimings.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                    <input type="hidden" name="delete_template" value="1">
                                    <input type="hidden" name="timing_id" value="<?= $template['timing_id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // JavaScript to dynamically generate hour fields based on number of hours input
    function generateHourInputs() {
        const numHours = parseInt(document.getElementById('num_hours').value);
        const hoursContainer = document.getElementById('hoursContainer');
        hoursContainer.innerHTML = ''; // Clear previous entries

        for (let i = 1; i <= numHours; i++) {
            const hourGroup = document.createElement('div');
            hourGroup.classList.add('form-group', 'row');

            hourGroup.innerHTML = `
            <label class="col-sm-2 col-form-label">Hour ${i}:</label>
            <div class="col-sm-2">
                <input type="text" name="hours[${i}][hour]" class="form-control" required value="Hr.${i}">
            </div>
            <div class="col-sm-2">
                <input type="text" name="hours[${i}][hour_desc]" class="form-control">
            </div>
            <div class="col-sm-3">
                <input type="time" name="hours[${i}][start_time]" class="form-control" required>
            </div>
            <div class="col-sm-3">
                <input type="time" name="hours[${i}][end_time]" class="form-control" required>
            </div>

        `;

            hoursContainer.appendChild(hourGroup);
        }
        document.getElementById("canceladdbtn").style.display = "block";
        document.getElementById("generatebtn").style.display = "none";
        document.getElementById("addTemplateBtn").style.display = "none";
        document.getElementById("addsubmitbtn").style.display = "block";
    }

    document.getElementById("addTemplateBtn").addEventListener("click", () => {
        document.getElementById("addForm").style.display = "block";
        document.getElementById("canceladdbtn").style.display = "none";
        document.getElementById("generatebtn").style.display = "block";
        document.getElementById("addTemplateBtn").style.display = "none";
        document.getElementById("addsubmitbtn").style.display = "none";
    });

    document.getElementById("canceladdbtn").addEventListener("click", () => {
        document.getElementById("addForm").style.display = "none";
        document.getElementById("canceladdbtn").style.display = "none";
        document.getElementById("generatebtn").style.display = "none";
        document.getElementById("addTemplateBtn").style.display = "block";
        document.getElementById("addsubmitbtn").style.display = "none";
        document.getElementById("formtemplete").reset();
        document.getElementById('hoursContainer').innerHTML = '';
    });
</script>

<?php
require_once("superadminfooter.php");
?>