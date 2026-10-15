<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CLASS_DIARY');

$page_title = "Edit";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$selected_date = date('Y-m-d');

// Handle form submissions (combined logic)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate secret code (if applicable)
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);

        if (!empty($_POST['sub_id']) && !empty($_POST['date']) && empty($_POST['diary'])) {
            $selected_sub_id = $_POST['sub_id'];
            $selected_date = $_POST['date'];
            $unmarkedHours = $facultyObj->getUnmarkedHours($selected_sub_id, $selected_date);
        } elseif (!empty($_POST['hours']) && !empty($_POST['diary'])) {
            \FeatureManager::requireWriteAccess('MOD_CLASS_DIARY');
            $result = $facultyObj->addDairy($_POST['hours'], $_POST['sub_id'], $_POST['date'], $_POST['diary'], $faculty_id);

            if ($result['status'] == 1) {
                $msg = "Dairy Added successfully.";
            } else {
                $msg = "Failed to Add Dairy. Please try again.";
            }
        } else {
            $msg = "Required fields missing. please try again";
        }
    } else {
        $msg = "Please try again";
    }
}

$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$subjectsDataJson = json_encode($facultySubjects);

$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<style>
    #studentTable {
        border-collapse: collapse;
        width: 100%;
    }

    #studentTable th,
    #studentTable td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    #studentTable th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #04AA6D;
        color: white;
    }

    .studentCheckbox {
        /* Style the checkbox */
        width: 18px;
        /* Increase size */
        height: 18px;
        cursor: pointer;
        transform: scale(1.2);
        /* Make it slightly bigger */
    }

    .highlightedRow {
        /* Style for highlighted rows */
        background-color: #04AA6D;
        /* Light yellow */
        font-weight: bold;
    }
</style>

<div class="container">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_CLASS_DIARY'); ?>
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Add Diary (Wtihout Attendance)</div>
                <div class="card-body">
                    <form action="facadddairy.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    if (strtotime($subject['end_date']) >= strtotime(date('Y-m-d'))) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8') . "' $selected>";
                                        echo htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8') . " (";
                                        echo htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8') . ") - ";
                                        echo htmlspecialchars($subject['class_name'], ENT_QUOTES, 'UTF-8') . " - ";
                                        echo htmlspecialchars($subject['acad_year'], ENT_QUOTES, 'UTF-8') . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo $selected_date; ?>" min="<?php echo date("Y-m-d"); ?>" max="<?php echo date("Y-m-d"); ?>" required />
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Get Timings</button>
                    </form>
                </div>
            </div>
            <?php if (!empty($selected_sub_id)) : ?>
                <br>
                <div class="card">
                    <div class="card-body">

                        <form action="facadddairy.php" method="post">
                            <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>" />
                            <input type="hidden" name="date" value="<?php echo $selected_date; ?>" />
                            <div class="row">
                                <div class="col-sm-6">
                                    <h5>Diary</h5>
                                    <textarea name="diary" class="form-control" rows="8" required="required"></textarea>
                                </div>

                                <div class="col-sm-6">
                                    <h5> Period / Hours</h5>
                                    <?php
                                    if (!empty($unmarkedHours['data'])) {
                                        require_once("modulehoursshow.php");
                                    } else {
                                        echo "<p>No available hours for attendance marking on this date and subject.</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                    </div>
                </div>
                <br>
                <div class="card">
                    <div class="card-body">
                        <br />
                        <div class="form-group">
                            <input type="checkbox" class="studentCheckbox" id="confirmCheckbox" name="confirmCheckbox" required> &nbsp;
                            <label for="confirmCheckbox" id="confirmLabel">I accept, Only Diary is Added Without Student Attendance</label>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-outline-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CLASS_DIARY') ? 'disabled' : ''; ?>>Save Dairy</button>
                        </form>
                    <?php endif; ?>
                    <?php if (isset($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>
                    </div>
                </div>
        </div>
    </div>
</div>
<script>
    const subjectData = <?php echo $subjectsDataJson; ?>;

    document.getElementById('sub_id').addEventListener('change', function() {
        const selectedSubId = this.value;
        let foundStartDate = null;

        for (const subject of subjectData.data) {
            if (subject.id == selectedSubId) {
                foundStartDate = subject.start_date;
                break;
            }
        }

        if (foundStartDate) {
            document.getElementById('date').min = foundStartDate;
        } else {
            document.getElementById('date').min = '';
        }
    });
</script>
<?php
require_once("facfooter.php");
?>