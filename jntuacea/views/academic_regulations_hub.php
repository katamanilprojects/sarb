<?php
/**
 * View: Academic Regulations Reference Hub
 * Academic Section Role: Read-only governance view of statutory regulations,
 * with quick entry points to Course Structure and Curriculum Subjects.
 */
?>

<div class="container-fluid px-4 py-4">
    <!-- Top Breadcrumb and Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1 text-primary fw-bold">
                <i class="bi bi-diagram-3-fill me-2"></i>Academic Regulations Reference Hub
            </h4>
            <p class="text-muted small mb-0">
                Statutory academic regulations, degree credit ceilings, graduation duration limits, and academic pathways defined by SuperAdmin.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="academicsectioncoursestructure.php" class="btn btn-primary btn-sm">
                <i class="bi bi-diagram-2 me-1"></i>Course Structure Engine
            </a>
            <a href="academicsectioncurriculumsubjects.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-book me-1"></i>Curriculum Subjects Catalog
            </a>
        </div>
    </div>

    <!-- Architectural Role Separation Callout -->
    <div class="alert alert-primary bg-primary-subtle border-primary-subtle shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-shield-lock-fill fs-3 text-primary me-3"></i>
            <div>
                <h6 class="fw-bold mb-1 text-primary">Architectural Governance & Role Separation</h6>
                <div class="small text-secondary">
                    Regulations, Degree Credit Ceilings, Allocation Rules, and Autonomous Evaluation Schemes are statutory parameters defined by the <strong>SuperAdmin</strong>. 
                    The <strong>Academic Section</strong> creates and maintains semester-by-semester <strong>Course Structures</strong> and subject details in strict conformance with these statutory ceilings.
                </div>
            </div>
        </div>
    </div>

    <!-- Summary KPI cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-primary bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Total Regulations</div>
                            <h3 class="fw-bold mb-0"><?= count($regulations) ?></h3>
                        </div>
                        <i class="bi bi-journal-text fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-success bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Undergraduate (UG)</div>
                            <h3 class="fw-bold mb-0">
                                <?= count(array_filter($regulations, fn($r) => ($r['program_level'] ?? '') === 'UG')) ?>
                            </h3>
                        </div>
                        <i class="bi bi-mortarboard fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-info bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Postgraduate (PG)</div>
                            <h3 class="fw-bold mb-0">
                                <?= count(array_filter($regulations, fn($r) => ($r['program_level'] ?? '') === 'PG')) ?>
                            </h3>
                        </div>
                        <i class="bi bi-award fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Regulations Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-card-list me-2"></i>Active Regulations Ledger</h5>
                <small class="text-muted">Explore degree boundaries, credit ceilings, and manage curriculum structures per regulation.</small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 140px;">Regulation</th>
                            <th>Program</th>
                            <th class="text-center" style="width: 130px;">Degree Credits</th>
                            <th class="text-center" style="width: 150px;">Duration & Sems</th>
                            <th>Statutory Pathways & Features</th>
                            <th>Effective Batches</th>
                            <th class="text-center pe-3" style="width: 220px;">Curriculum Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($regulations)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    No regulations available in the system.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($regulations as $r): ?>
                                <?php
                                $isUG = (($r['program_level'] ?? 'UG') === 'UG');
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge <?= $isUG ? 'bg-primary' : 'bg-dark' ?> fs-6 px-2 py-1">
                                            <?= htmlspecialchars($r['regulation']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($r['prog_fullname']) ?>
                                        </div>
                                        <div class="small text-muted">
                                            <span class="badge <?= $isUG ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-dark-subtle text-dark border border-dark-subtle' ?> me-1">
                                                <?= htmlspecialchars($r['program_level'] ?? 'UG') ?>
                                            </span>
                                            <?= htmlspecialchars($r['prog_shortname']) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold text-primary fs-6">
                                            <?= number_format((float)($r['total_degree_credits'] ?? 160.0), 1) ?>
                                        </div>
                                        <div class="small text-muted">Credits</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold text-dark">
                                            <?= (int)($r['normal_duration_years'] ?? 4) ?> yrs / <?= (int)($r['total_semesters'] ?? 8) ?> sems
                                        </div>
                                        <div class="small text-muted">
                                            Max: <?= (int)($r['max_duration_years'] ?? 8) ?> yrs
                                            <?php if (!empty($r['gap_year_extension_years'])): ?>
                                                | Gap: +<?= (int)$r['gap_year_extension_years'] ?>y
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if (!empty($r['has_lateral_entry'])): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle" title="Lateral Entry Scheme">
                                                    <i class="bi bi-box-arrow-in-right me-1"></i>LES (<?= number_format((float)($r['lateral_entry_credits'] ?? 0), 1) ?> cr)
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($r['has_honors'])): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle" title="Honors Degree">
                                                    <i class="bi bi-award me-1"></i>Honors (+<?= number_format((float)($r['honors_credits'] ?? 0), 1) ?> cr)
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($r['has_minors'])): ?>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle" title="Minor Degree">
                                                    <i class="bi bi-diagram-3 me-1"></i>Minors (+<?= number_format((float)($r['minor_credits'] ?? 0), 1) ?> cr)
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($r['has_gap_year'])): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" title="Gap Year for Incubation">
                                                    <i class="bi bi-lightbulb me-1"></i>Gap Year
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($r['has_internal_improvement'])): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="Internal Marks Improvement">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Internal Improvement
                                                </span>
                                            <?php endif; ?>
                                            <?php if (empty($r['has_lateral_entry']) && empty($r['has_honors']) && empty($r['has_minors']) && empty($r['has_gap_year']) && empty($r['has_internal_improvement'])): ?>
                                                <span class="text-muted small">Standard Degree Pathway</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($r['effective_admitted_batch'])): ?>
                                            <div><strong>Batch:</strong> <?= htmlspecialchars($r['effective_admitted_batch']) ?></div>
                                        <?php else: ?>
                                            <div class="text-muted">Start Year: <?= htmlspecialchars((string)($r['start_year'] ?? '—')) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($r['les_effective_batch'])): ?>
                                            <div class="text-muted"><strong>LES:</strong> <?= htmlspecialchars($r['les_effective_batch']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="academicsectioncoursestructure.php?reg_id=<?= $r['id'] ?>&prog_id=<?= $r['prog_id'] ?>" 
                                               class="btn btn-primary" title="Open Course Structure Roadmap">
                                                <i class="bi bi-diagram-2 me-1"></i>Structure
                                            </a>
                                            <a href="academicsectioncurriculumsubjects.php?reg_id=<?= $r['id'] ?>&prog_id=<?= $r['prog_id'] ?>" 
                                               class="btn btn-outline-primary" title="View Curriculum Subjects Catalog">
                                                <i class="bi bi-book me-1"></i>Catalog
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
