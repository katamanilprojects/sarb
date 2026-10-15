<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

$obj = new Admin();
$departments = $obj->getAllDepartments();

$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container my-4">
    <!-- Header Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 text-primary">
                        <i class="bi bi-building text-primary me-2"></i>Academic Departments Operations Hub
                    </h4>
                    <p class="text-muted mb-0 small">
                        Principal's executive overview. Access department-wise leadership (HOD), teaching faculty rosters, and class cohorts with student enrollment.
                    </p>
                </div>
                <div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        <i class="bi bi-shield-check me-1"></i>Institutional Leadership
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Departments Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold text-dark">
                <i class="bi bi-diagram-3 me-2"></i>Academic Departments (<?= count($departments['data'] ?? []); ?>)
            </span>
            <span class="text-muted small">Select an operational branch below</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted text-uppercase">
                        <tr>
                            <th style="width: 15%;">Dept Code</th>
                            <th style="width: 40%;">Department Name</th>
                            <th style="width: 15%; text-align: center;">HOD Leadership</th>
                            <th style="width: 15%; text-align: center;">Faculty Roster</th>
                            <th style="width: 15%; text-align: center;">Classes & Students</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments['data'] as $dept): 
                            $deptShort = $dept['dept_shortname'];
                            $deptFull = $dept['dept_fullname'];
                            $hasFaculty = ($deptShort !== "BTech1");
                            $hasClasses = !in_array($deptShort, ["humanities", "physics", "chemistry", "maths", "PE"], true);
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 fs-6">
                                        <?= htmlspecialchars($deptShort); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($deptFull); ?></strong>
                                    <small class="text-muted">ID: #<?= (int)$dept['id']; ?></small>
                                </td>
                                <td class="text-center">
                                    <form action="hod_profile.php" method="post" class="d-inline">
                                        <input type="hidden" name="dept_id" value="<?= $dept['id']; ?>" />
                                        <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($deptFull, ENT_QUOTES); ?>" />
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100" title="View / Update HOD Details">
                                            <i class="bi bi-person-badge me-1"></i> HOD Details
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <?php if ($hasFaculty): ?>
                                        <form action="adminfac.php" method="post" class="d-inline">
                                            <input type="hidden" name="dept_id" value="<?= $dept['id']; ?>" />
                                            <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($deptFull, ENT_QUOTES); ?>" />
                                            <button type="submit" class="btn btn-sm btn-outline-success w-100" title="Manage Faculty Roster">
                                                <i class="bi bi-person-check me-1"></i> Faculty
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($hasClasses): ?>
                                        <form action="adminviewclasses.php" method="post" class="d-inline">
                                            <input type="hidden" name="dept_id" value="<?= $dept['id']; ?>" />
                                            <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($deptFull, ENT_QUOTES); ?>" />
                                            <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Classes & Student Enrollment">
                                                <i class="bi bi-people me-1"></i> Classes
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Basic Science</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once("adminfooter.php");
?>