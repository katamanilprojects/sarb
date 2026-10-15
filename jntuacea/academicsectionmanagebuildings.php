<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Manage Buildings";
require_once("academicsection.class.php");
require_once("academicsectionheader.php");

$obj = new AcademicSection();
$msg = '';
$msg_type = 'info';

if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    // Add Building
    if (!empty($_POST['action']) && $_POST['action'] == "add_building") {
        $building_name = trim($_POST['building_name']);
        $result = $obj->addBuilding($building_name);
        if ($result['status'] === 1) {
            $msg = $result['message'];
            $msg_type = 'success';
        } else {
            $msg = $result['err'] ?? "Failed to add building";
            $msg_type = 'danger';
        }
    }

    // Update Building
    if (!empty($_POST['action']) && $_POST['action'] == "update_building") {
        $id = intval($_POST['building_id']);
        $building_name = trim($_POST['building_name']);
        $status = intval($_POST['status']);
        $result = $obj->updateBuilding($id, $building_name, $status);
        if ($result['status'] === 1) {
            $msg = $result['message'];
            $msg_type = 'success';
        } else {
            $msg = $result['err'] ?? "Failed to update building";
            $msg_type = 'danger';
        }
    }

    // Delete Building
    if (!empty($_POST['action']) && $_POST['action'] == "delete_building") {
        $id = intval($_POST['building_id']);
        $result = $obj->deleteBuilding($id);
        if ($result['status'] === 1) {
            $msg = $result['message'];
            $msg_type = 'success';
        } else {
            $msg = $result['err'] ?? "Failed to delete building";
            $msg_type = 'danger';
        }
    }

    // Add Hall
    if (!empty($_POST['action']) && $_POST['action'] == "add_hall") {
        $building_id = intval($_POST['building_id']);
        $hall_name = trim($_POST['hall_name']);
        $result = $obj->addHall($building_id, $hall_name);
        if ($result['status'] === 1) {
            $msg = $result['message'];
            $msg_type = 'success';
        } else {
            $msg = $result['err'] ?? "Failed to add hall";
            $msg_type = 'danger';
        }
    }

    // Update Hall
    if (!empty($_POST['action']) && $_POST['action'] == "update_hall") {
        $id = intval($_POST['hall_id']);
        $hall_name = trim($_POST['hall_name']);
        $status = intval($_POST['status']);
        $result = $obj->updateHall($id, $hall_name, $status);
        if ($result['status'] === 1) {
            $msg = $result['message'];
            $msg_type = 'success';
        } else {
            $msg = $result['err'] ?? "Failed to update hall";
            $msg_type = 'danger';
        }
    }

    // Delete Hall
    if (!empty($_POST['action']) && $_POST['action'] == "delete_hall") {
        $id = intval($_POST['hall_id']);
        $result = $obj->deleteHall($id);
        if ($result['status'] === 1) {
            $msg = $result['message'];
            $msg_type = 'success';
        } else {
            $msg = $result['err'] ?? "Failed to delete hall";
            $msg_type = 'danger';
        }
    }
}

$_SESSION['secretcode'] = bin2hex(random_bytes(32));

$buildings = $obj->getAllBuildings();
?>

<style>
    .building-card {
        border-left: 4px solid #007bff;
        margin-bottom: 20px;
    }
    .hall-item {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .hall-item:last-child {
        border-bottom: none;
    }
    .hall-item:hover {
        background-color: #f8f9fa;
    }
    .status-badge {
        font-size: 0.8rem;
    }
</style>

<div class="container">
    <br />
    <div class="card">
        <div class="card-header">
            <strong><i class="bi bi-building"></i> Manage Buildings & Halls</strong>
        </div>
        <div class="card-body">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add Building Form -->
            <div class="card mb-4" style="background: #f8f9fa;">
                <div class="card-body">
                    <h6><i class="bi bi-plus-circle"></i> Add New Building</h6>
                    <form action="academicsectionmanagebuildings.php" method="post" class="row g-3">
                        <div class="col-md-8">
                            <input type="text" name="building_name" class="form-control" placeholder="Enter Building Name" required maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <input type="hidden" name="action" value="add_building">
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus"></i> Add Building
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Buildings List -->
            <?php if ($buildings['status'] === 1 && !empty($buildings['data'])): ?>
                <?php foreach ($buildings['data'] as $building): ?>
                    <?php $halls = $obj->getHallsByBuilding($building['id']); ?>
                    <div class="card building-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <strong><?= htmlspecialchars($building['building_name']) ?></strong>
                                    <span class="badge bg-<?= $building['status'] == 1 ? 'success' : 'secondary' ?> status-badge ms-2">
                                        <?= $building['status'] == 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBuildingModal<?= $building['id'] ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addHallModal<?= $building['id'] ?>">
                                        <i class="bi bi-plus"></i> Add Hall
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this building and all its halls?')" data-bs-toggle="modal" data-bs-target="#deleteBuildingModal<?= $building['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($halls['status'] === 1 && !empty($halls['data'])): ?>
                                <small class="text-muted">Halls/Rooms (<?= count($halls['data']) ?>):</small>
                                <div class="mt-2">
                                    <?php foreach ($halls['data'] as $hall): ?>
                                        <div class="hall-item">
                                            <div>
                                                <i class="bi bi-door-open"></i> <?= htmlspecialchars($hall['hall_name']) ?>
                                                <span class="badge bg-<?= $hall['status'] == 1 ? 'success' : 'secondary' ?> status-badge ms-2">
                                                    <?= $hall['status'] == 1 ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editHallModal<?= $hall['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this hall?')" data-bs-toggle="modal" data-bs-target="#deleteHallModal<?= $hall['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Edit Hall Modal -->
                                        <div class="modal fade" id="editHallModal<?= $hall['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="academicsectionmanagebuildings.php" method="post">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Hall</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Hall Name</label>
                                                                <input type="text" name="hall_name" class="form-control" value="<?= htmlspecialchars($hall['hall_name']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="1" <?= $hall['status'] == 1 ? 'selected' : '' ?>>Active</option>
                                                                    <option value="0" <?= $hall['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <input type="hidden" name="action" value="update_hall">
                                                            <input type="hidden" name="hall_id" value="<?= $hall['id'] ?>">
                                                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Hall Modal -->
                                        <div class="modal fade" id="deleteHallModal<?= $hall['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="academicsectionmanagebuildings.php" method="post">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirm Delete</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete hall "<?= htmlspecialchars($hall['hall_name']) ?>"?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <input type="hidden" name="action" value="delete_hall">
                                                            <input type="hidden" name="hall_id" value="<?= $hall['id'] ?>">
                                                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0"><small>No halls added yet</small></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Edit Building Modal -->
                    <div class="modal fade" id="editBuildingModal<?= $building['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="academicsectionmanagebuildings.php" method="post">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Building</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Building Name</label>
                                            <input type="text" name="building_name" class="form-control" value="<?= htmlspecialchars($building['building_name']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="1" <?= $building['status'] == 1 ? 'selected' : '' ?>>Active</option>
                                                <option value="0" <?= $building['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="action" value="update_building">
                                        <input type="hidden" name="building_id" value="<?= $building['id'] ?>">
                                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Add Hall Modal -->
                    <div class="modal fade" id="addHallModal<?= $building['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="academicsectionmanagebuildings.php" method="post">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Hall to <?= htmlspecialchars($building['building_name']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Hall Name</label>
                                            <input type="text" name="hall_name" class="form-control" placeholder="Enter Hall/Room Name" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="action" value="add_hall">
                                        <input type="hidden" name="building_id" value="<?= $building['id'] ?>">
                                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add Hall</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Building Modal -->
                    <div class="modal fade" id="deleteBuildingModal<?= $building['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="academicsectionmanagebuildings.php" method="post">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Delete</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete building "<?= htmlspecialchars($building['building_name']) ?>" and all its halls?
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="action" value="delete_building">
                                        <input type="hidden" name="building_id" value="<?= $building['id'] ?>">
                                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">No buildings found. Add your first building above.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once("academicsectionfooter.php");
?>
