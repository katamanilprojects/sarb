<?php
require_once __DIR__.'/bootstrap.php';
if (!empty($unmarkedHours['data'])) {
    foreach ($unmarkedHours['data'] as $hourData) {
        $hour_id = $hourData["hour_id"];
        $hour = $hourData['hour'];
        $start_time_12hr = date("g:i A", strtotime($hourData['start_time'])); // Convert start_time to 12-hour format
        $end_time_12hr = date("g:i A", strtotime($hourData['end_time']));     // Convert end_time to 12-hour format
        $timing = "$start_time_12hr - $end_time_12hr";
        if (!empty($hourData["hour_desc"])) {
            $timing .= " (" . $hourData["hour_desc"].")";
        }

        echo "<div class='form-check'>";
        echo "<input class='form-check-input' type='checkbox' name='hours[]' value='$hour_id' id='hour_$hour_id'>";
        echo "<label class='form-check-label' for='hour_$hour_id'>$timing</label>";
        echo "</div>";
    }
}
