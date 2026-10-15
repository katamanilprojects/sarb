<?php
require_once __DIR__ . '/services/FeatureManager.php';

$menu_items = [
    "Home" => "studenthome.php"
];

// Conditionally show Profile & Documents link if active
if (\FeatureManager::isStudentVisible('MOD_STUDENT_PROFILE')) {
    $menu_items["Profile"] = "studentprofile.php";
}

// Conditionally show Results link if active
if (\FeatureManager::isStudentVisible('MOD_RESULTS_PUBLISH')) {
    $menu_items["Results"] = "studentresults.php";
}

// Conditionally show Certificates link if active
if (\FeatureManager::isStudentVisible('MOD_CERTIFICATES')) {
    $menu_items["Certificates"] = "studentcertificates.php";
}

// Conditionally show Feedback survey link if active
if (\FeatureManager::isStudentVisible('MOD_FEEDBACK')) {
    $menu_items["Feedback"] = "studentfeedback.php";
}

$menu_items["Change Password"] = "stchgpwd.php";
$menu_items["Logout"] = "logout.php";
?>
<div class="container dontprint">
    <div class="row g-1">
        <?php
        foreach ($menu_items as $title => $link) {
            $is_active = !empty($page_title) && $page_title == $title ? "active btn-success" : "";
            $aria_current = $is_active ? 'aria-current="page"' : "";
            $btn_class = ($title == "Logout") ? "btn-outline-danger" : ($is_active ? "btn-success" : "btn-outline-primary");
        ?>
            <div class="col-6 col-sm p-0">
                <a href="<?= $link ?>" class="w-100 btn <?= $btn_class ?>" <?= $aria_current ?>><?= $title ?></a>
            </div>
        <?php } ?>
    </div>
</div>
<br>