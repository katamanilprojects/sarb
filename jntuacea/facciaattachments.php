<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_METADATA');

if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = (int)$_GET['sub_id'];
    $assessmentNumber = (int)$_GET['assessment_number'];
    $action_self = "facciaattachments.php?sub_id=" . $sub_id . "&assessment_number=" . $assessmentNumber;
} else {
    header('Location: facciamarks.php');
    exit();
}

require_once("facheader.php");
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    \FeatureManager::requireWriteAccess('MOD_CIA_METADATA');
    unset($_SESSION['secretcode']);
    $sub_id = (int)$_POST['sub_id'];
    $assessmentNumber = (int)$_POST['assessment_number'];

    if (!empty($_FILES['attachment']) && !empty($_POST["file_title"])) {
        $file = $_FILES['attachment'];
        $fileType = $file['type'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        $existing = $facultyObj->getCIAAttachments($sub_id, $assessmentNumber);
        $attachmentCount = (int)($existing['count'] ?? 0);

        $allowedTypes = ['xlsx', 'xls', 'pdf', 'docx', 'doc', 'jpg', 'png', 'csv'];
        $maxSize = 20971520;

        if ($fileError === UPLOAD_ERR_OK && $attachmentCount < 5 && in_array(strtolower(pathinfo($file["name"], PATHINFO_EXTENSION)), $allowedTypes, true) && $fileSize > 0 && $fileSize <= $maxSize) {
            $targetDir = 'uploads/';
            $fileName = 'cia_' . bin2hex(random_bytes(24)) . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $targetFile = $targetDir . $fileName;
            if (move_uploaded_file($fileTmpName, $targetFile)) {
                $res = $facultyObj->addCIAAttachment($sub_id, $assessmentNumber, $_POST["file_title"], $targetFile);
                if (!empty($res["status"]) && $res["status"] == 1) {
                    $succ = "File Upload and Saved Successfully";
                } else {
                    if (is_file($targetFile)) { unlink($targetFile); }
                    $err = "File Not Saved. Please try again";
                }
            } else {
                $err = "File Not Uploaded. Please try again";
            }
        } else {
            $err = "Please Upload Valid File (Doc, Excel, PDF) with Size < 20MB.";
        }
    } elseif (!empty($_POST['id']) && !empty($_POST['submitbtn']) && $_POST['submitbtn'] == "X") {
        $res = $facultyObj->deleteCIAAttachment($_POST['id'], $sub_id, $assessmentNumber);
        if (!empty($res["status"]) && $res["status"] == 1) {
            $succ = "File Successfully Removed";
        } else {
            $err = "File Not Removed. Please try again";
        }
    }
}

$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>
<?php $url_get_data = "sub_id=" . $sub_id . "&assessment_number=" . $assessmentNumber; ?>

<div class="container">
    <br />
    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_METADATA'); ?>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    Adding Attachments for Continuous Internal Assessment - <?php echo $assessmentNumber; ?>
                </div>
                <div class="card-header">
                    <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <?php
                $attachments = $facultyObj->getCIAAttachments($sub_id, $assessmentNumber);
                $components = $ciaObj->getAssessmentComponents($sub_id, $assessmentNumber);
                $isReadOnly = \FeatureManager::isFacultyReadOnly('MOD_CIA_METADATA');

                if ((!empty($attachments['count']) && $attachments['count'] > 0) || !empty($components['data'])) {
                    echo '<div class="card-header">Uploaded Attachments</div>';
                    echo '<div class="card-body">';
                    echo '<table class="table table-bordered">';
                    echo '<tr><td>S.No.</td><td>Title</td><td>Download</td><td>Delete</td></tr>';
                    $sno = 1;
                    $attachments_count = 0;
                    foreach (($attachments["files"] ?? []) as $attachment) {
                        $delBtnAttr = $isReadOnly ? 'disabled' : '';
                        echo '<tr><td>' . $sno . '</td><td>' . htmlspecialchars($attachment['file_title'], ENT_QUOTES, 'UTF-8') . '</td><td><a href="' . ('download_attachment.php?id=' . (int)$attachment['id']) . '" target="_blank" download class="btn btn-sm btn-primary">Download</a></td><td>';
                        echo '<form action="' . $action_self . '" method="post" onsubmit=\'return confirm("Are you sure you want to delete the Attachment ? ");\'>
                        <input type="hidden" name="id" value="' . $attachment['id'] . '">
                        <input type="hidden" name="sub_id" value="' . $sub_id . '">
                        <input type="hidden" name="assessment_number" value="' . $assessmentNumber . '">
                        <input type="hidden" name="secretcode" value="' . $_SESSION['secretcode'] . '">
                        <input type="submit" value="X" name="submitbtn" class="btn btn-sm btn-danger" ' . $delBtnAttr . '></form>';
                        echo '</td></tr>';
                        $sno++;
                        $attachments_count++;
                    }

                    if (!empty($components['data'])) {
                        foreach ($components['data'] as $index => $component) {

                            $questions = $ciaObj->getQuestionsByComponent($component['id']);
                            $marks = $ciaObj->getMarksByComponent($component['id']);
                            if(!empty($questions)){
                                if(!empty($marks)){
                                    echo "<tr>"
                                            . "<td>" . $sno++ . "</td>"
                                            . "<td>" . htmlspecialchars($component['component_type']) . "</td>"
                                            . "<td><a href='facviewmarkscsv.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "' class='btn btn-sm btn-primary'>Download</a></td>"
                                            . "<td>System-generated</td>"
                                            . "</tr>";
                                }
                            }
                        }
                    }

                    echo '</table>';
                    echo '</div>';
                    echo '<div class="card-footer">Note: Each Assessment can have Upto 5 Attachments</div>';
                } else {
                    echo '<div class="card-body">No Attachments Uploaded yet..!</div>';
                }
                ?>
            </div>
        </div>
    </div>
    <br>
    <button id="showaddnewdiv" class="btn btn-success" <?php if ((!empty($attachments_count) && $attachments_count >= 5) || \FeatureManager::isFacultyReadOnly('MOD_CIA_METADATA')) {
                                                            echo 'disabled';
                                                        } ?>>Add New Attachment</button>
    <?php if (!empty($succ)) echo '<br><div class="alert alert-success">' . $succ . '</div>'; ?>
    <?php if (!empty($err)) echo '<br><div class="alert alert-danger">' . $err . '</div>'; ?>
    <br />
    <div class="row" id="addNew" style="display:none;">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-body">
                    <form action="<?php echo $action_self; ?>" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="file_title">Title of the File:</label>
                            <input type="text" name="file_title" id="file_title" placeholder="Enter the Title of the File" required class="form-control" />
                        </div>
                        <br>
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <div class="form-group">
                            <label for="attachment">Attachment:</label>
                            <input type="file" name="attachment" id="attachment" class="form-control" accept=".pdf,.docx,.jpg,.png,.xlsx,.xls,.csv,.doc" />
                        </div>
                        <br>
                        <button type="submit" class="btn btn-success" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_METADATA') ? 'disabled' : ''; ?>>Submit</button>
                    </form>
                </div>
                <div class="card-footer">
                    Reference:
                    <ul>
                        <li><a href="uploads/CIA_format_Theory_ug.xlsx" download target="_blank" class="text-primary">Click here to download Sample Record of Evaluation for Continuous Internal Assessment (Excel)</a></li>
                        <br>
                        <li><a href="uploads/CIA_QP_format_theory_ug.docx" download target="_blank" class="text-primary">Click here to download Sample Format of Question Paper for Continuous Internal Assessment (Word)</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('showaddnewdiv').addEventListener("click", () => {
            document.getElementById("addNew").style.display = "block";
            document.getElementById('showaddnewdiv').style.display = "none";
        })
    </script>
</div>

<?php
require_once("facfooter.php");
?>
