<?php
$criteria_options = [
    'overall_percentage' => 'Overall Percentage',
    'subject_wise_percentage' => 'Subject Wise Percentage'
];

$operator1_options = ['>=', '>', '<', '<=', '=', '!='];
$operator2_options = ['<', '<=', '>', '>=', '=', '!='];

?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Attendance Rules</div>
        <div class="card-body">

            <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>

            <form method="post" action="<?= htmlspecialchars($page_action) ?>" class="mb-4">
                <input type="hidden" name="action" value="add_or_update">
                <input type="hidden" name="id" value="<?= $edit_rule['id'] ?? '' ?>">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="reg_id" class="form-select" required>
                            <option value="">Select Regulation</option>
                            <?php foreach ($regulations as $regulation): ?>
                                <option value="<?= $regulation['id'] ?>" <?= (isset($edit_rule) && $edit_rule['reg_id'] == $regulation['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($regulation['regulation'] ?? '') . ' - ' . ($regulation['prog_shortname'] ?? $regulation['program_code'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="criteria" class="form-select" required>
                            <?php foreach ($criteria_options as $value => $label): ?>
                                <option value="<?= $value ?>" <?= (isset($edit_rule) && $edit_rule['criteria'] === $value) ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <select name="operator1" class="form-select" required>
                            <?php foreach ($operator1_options as $operator): ?>
                                <option value="<?= $operator ?>" <?= (isset($edit_rule) && $edit_rule['operator1'] === $operator) ? 'selected' : '' ?>><?= $operator ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="value1" class="form-control" placeholder="Value 1" value="<?= htmlspecialchars($edit_rule['value1'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-1">
                        <select name="operator2" class="form-select" required>
                            <?php foreach ($operator2_options as $operator): ?>
                                <option value="<?= $operator ?>" <?= (isset($edit_rule) && $edit_rule['operator2'] === $operator) ? 'selected' : '' ?>><?= $operator ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="value2" class="form-control" placeholder="Value 2" value="<?= htmlspecialchars($edit_rule['value2'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><?= isset($edit_rule) ? 'Update' : 'Add' ?></button>
                    </div>
                </div>
            </form>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Regulation - Program</th>
                        <th>Criteria</th>
                        <th>Rule</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_rules as $rule): ?>
                        <tr>
                            <td><?= htmlspecialchars(($rule['regulation'] ?? '') . ' - ' . ($rule['prog_shortname'] ?? $rule['program_code'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($criteria_options[$rule['criteria']] ?? $rule['criteria']) ?></td>
                            <td><?= htmlspecialchars(($rule['operator1'] ?? '') . ' ' . ($rule['value1'] ?? '') . ' and ' . ($rule['operator2'] ?? '') . ' ' . ($rule['value2'] ?? '')) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($page_action) ?>?edit=<?= $rule['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="post" action="<?= htmlspecialchars($page_action) ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this attendance rule?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
