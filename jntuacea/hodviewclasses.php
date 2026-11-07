<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Classes";
require_once("hod.class.php");
require_once("hodheader.php");

$obj = new HOD();

// Fetch specializations for the selected department
$specializations = $obj->getSpecializationsByDepartment($_SESSION["dept_id"]);
?>

<div class="container">

    <?php if (!empty($specializations)) { ?>
        <?php foreach ($specializations as $spec) { ?>
            <div class="card mb-4">
                <div class="card-header">
                    Specialization: <?= htmlspecialchars($spec['spec_fullname']) ?>
                </div>
                <div class="card-body">
                    <?php
                    $classes = $obj->getActiveClassesBySpecialization($spec['id']);

                    if (!empty($classes)) {
                        // Group by academic year
                        $groupedClasses = [];
                        foreach ($classes as $class) {
                            $groupedClasses[$class['acad_year']][] = $class;
                        }

                        // Bootstrap 5 Accordion for Academic Years
                        echo '<div class="accordion mb-3" id="accordion_' . $spec['id'] . '">';
                        $index = 0;
                        foreach ($groupedClasses as $acadYear => $yearClasses) {
                            $collapseId = 'collapse_' . $spec['id'] . '_' . $index;

                            $isRelevant = false;
                            foreach ($yearClasses as $c) {
                                if (strtotime($c['end_date']) >= strtotime(date('Y-m-d'))) {
                                    $isRelevant = true;
                                    break;
                                }
                            }
                            $yearClassMuted = $isRelevant ? ' text-success-emphasis' : ' text-secondary-emphasis';

                            echo '<div class="accordion-item">';
                            echo '<h2 class="accordion-header" id="heading_' . $collapseId . '">';
                            echo '<button class="accordion-button collapsed text-uppercase' . $yearClassMuted . '" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">';
                            echo '<i class="bi bi-calendar3 me-2"></i>Academic Year: ' . htmlspecialchars($acadYear);
                            echo '</button></h2>';
                            echo '<div id="' . $collapseId . '" class="accordion-collapse collapse" aria-labelledby="heading_' . $collapseId . '" data-bs-parent="#accordion_' . $spec['id'] . '">';
                            echo '<div class="accordion-body">';
                            echo "<div class='table-responsive'><table class='table table-sm table-bordered table-striped align-middle'>";
                            echo "<thead class='table-light'><tr><th>Class Name</th><th>Details</th><th>Actions</th></tr></thead><tbody>";

                            foreach ($yearClasses as $class) {
                                $isEnrolled = $obj->checkIfStudentsEnrolled($class['id']);
                                $today = strtotime(date('Y-m-d'));
                                $start = strtotime($class['start_date']);
                                $end = strtotime($class['end_date']);
                                // Apply contextual color to start and end dates
                                $startColor = $start > $today ? 'text-secondary' : ($end < $today ? 'text-secondary' : 'text-success');
                                $endColor = $end < $today ? 'text-secondary' : ($start > $today ? 'text-secondary' : 'text-success');

                                $isCompleted = $end < $today;

                                $iconClass = $isCompleted ? 'bi bi-building text-secondary' : 'bi bi-building text-primary';
                                $classTextClass = $isCompleted ? 'text-secondary' : 'fw-semibold text-dark';
                                echo "<tr>
                                        <td><i class=\"{$iconClass} me-1\" data-bs-toggle=\"tooltip\" title=\"Class Section\"></i><span class=\"{$classTextClass}\">{$class['classname']}</span></td>";
                                echo "<td class='small'>
                                        <div class='{$startColor}'>Start: {$class['start_date']}</div>
                                        <div class='{$endColor}'>End: {$class['end_date']}</div>
                                      </td>
                                        <td>";
                                $btnSubject = $isCompleted ? 'btn-outline-primary' : 'btn-primary';
                                $btnFaculty = $isCompleted ? 'btn-outline-success' : 'btn-success';
                                $btnStudents = $isCompleted ? 'btn-outline-info' : 'btn-info';
                                $btnTimetable = $isCompleted ? 'btn-outline-warning' : 'btn-warning';

                                echo '<div class="d-flex flex-wrap gap-1">
                                        <form action="hodviewsubjects.php" method="post" class="m-0">
                                            <input type="hidden" name="class_id" value="' . $class['id'] . '" />
                                            <input type="hidden" name="spec_fullname" value="' . $spec['spec_fullname'] . '" />
                                            <input type="hidden" name="class_fullname" value="' . $class['classname'] . ' (' . $class['acad_year'] . ')" />
                                            <button type="submit" class="btn btn-sm ' . $btnSubject . '"><i class="bi bi-book"></i> Subjects</button>
                                        </form>
                                        <form action="hodmapfaculty.php" method="post" class="m-0">
                                            <input type="hidden" name="class_id" value="' . $class['id'] . '" />
                                            <input type="hidden" name="spec_fullname" value="' . $spec['spec_fullname'] . '" />
                                            <input type="hidden" name="class_fullname" value="' . $class['classname'] . ' (' . $class['acad_year'] . ')" />
                                            <button type="submit" class="btn btn-sm ' . $btnFaculty . '"><i class="bi bi-person"></i> Faculty</button>
                                        </form>';

                                if ($isEnrolled) {
                                    echo '<form action="hodmapstudents.php" method="post" class="m-0">
                                            <input type="hidden" name="class_id" value="' . $class['id'] . '" />
                                            <input type="hidden" name="spec_fullname" value="' . $spec['spec_fullname'] . '" />
                                            <input type="hidden" name="class_fullname" value="' . $class['classname'] . ' (' . $class['acad_year'] . ')" />
                                            <button type="submit" class="btn btn-sm ' . $btnStudents . '"><i class="bi bi-people"></i> Students</button>
                                        </form>';
                                } else {
                                    echo '<span class="badge bg-light text-secondary border align-self-center px-2 py-1" title="Students not enrolled yet">Enrollment Pending</span>';
                                }

                                echo '<form action="hodmanage_timetable.php" method="post" class="m-0">
                                        <input type="hidden" name="class_id" value="' . $class['id'] . '" />
                                        <input type="hidden" name="spec_fullname" value="' . $spec['spec_fullname'] . '" />
                                        <input type="hidden" name="class_fullname" value="' . $class['classname'] . ' (' . $class['acad_year'] . ')" />
                                        <button type="submit" class="btn btn-sm ' . $btnTimetable . '"><i class="bi bi-calendar-week"></i> Timetable</button>
                                    </form>
                                </div>';
                                echo "</td></tr>";
                            }

                            echo "</tbody></table></div></div></div></div>";
                            $index++;
                        }
                        echo '</div>'; // Close accordion container
                    } else {
                        echo "<div class='alert alert-warning mb-0'>No classes found for this specialization.</div>";
                    }
                    ?>
                </div> <!-- .card-body -->
            </div> <!-- .card -->
        <?php } ?>
    <?php } else {
        echo "<p>No specializations found for this department.</p>";
    } ?>
</div>

<?php
require_once("hodfooter.php");
?>