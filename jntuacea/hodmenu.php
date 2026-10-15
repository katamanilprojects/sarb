<?php
require_once __DIR__ . '/services/FeatureManager.php';

$showAtt = \FeatureManager::isHodVisible('MOD_ATTENDANCE');
?>
<div class="container dontprint">
    <div class="row g-1">
        <div class="col-6 col-md p-0">
            <a href="hodhome.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="Home"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>"><i class="bi bi-house-door me-1"></i> Home</a>
        </div>
        <div class="col-6 col-md p-0">
            <a href="hodviewclasses.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="Classes"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>"><i class="bi bi-journal-bookmark me-1"></i> Classes</a>
        </div>
        <div class="col-6 col-md p-0">
            <a href="hodviewfaculties.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="Manage Faculty"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>"><i class="bi bi-person-badge me-1"></i> Faculty</a>
        </div>
        <?php if ($showAtt): ?>
        <div class="col-6 col-md p-0">
            <a href="hodshowattendance.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="View Attendance"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>"><i class="bi bi-calendar-check me-1"></i> Attendance</a>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md p-0">
            <a href="hodchgpwd.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="Change Pwd"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>"><i class="bi bi-key me-1"></i> Change Password</a>
        </div>
        <div class="col-6 col-md p-0">
            <a href="logout.php" class="w-100 btn btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
    </div>
</div>
<br />