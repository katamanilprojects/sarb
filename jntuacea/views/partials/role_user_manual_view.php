<?php
/**
 * Shared Role-Aware Interactive User Manual View Partial
 *
 * Expects `$manualRole` ('faculty', 'hod', 'admin', 'academic_section', 'superadmin')
 * to be defined by the parent controller before inclusion.
 */
require_once __DIR__ . '/../../services/UserManualService.php';
require_once __DIR__ . '/../../services/FeatureManager.php';

$targetRole = $manualRole ?? ($_SESSION['role'] ?? 'faculty');
$manualService = \Services\UserManualService::getInstance();
$manual = $manualService->getManualForRole($targetRole);
$activeSessionRole = $_SESSION['role'] ?? '';
?>

<style>
    .manual-hero {
        background: linear-gradient(135deg, #1a365d 0%, #0d6efd 100%);
        color: #fff;
        border-radius: 0.65rem;
    }
    .manual-toc-sticky {
        position: sticky;
        top: 1rem;
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
    }
    .manual-toc-link {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.35rem 0.6rem;
        border-radius: 0.375rem;
        color: #334155;
        text-decoration: none;
        font-size: 0.85rem;
        line-height: 1.35;
        transition: background-color 0.15s ease, color 0.15s ease;
    }
    .manual-toc-link:hover,
    .manual-toc-link.active {
        background-color: #e7f1ff;
        color: #0d6efd;
        font-weight: 600;
    }
    .story-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.6rem;
        transition: box-shadow 0.15s ease, border-color 0.15s ease;
        scroll-margin-top: 1.25rem;
    }
    .story-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(15, 23, 42, 0.06);
        border-color: #cbd5e1;
    }
    .story-number-badge {
        min-width: 2.1rem;
        height: 2.1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.9rem;
    }
    .step-list {
        counter-reset: manual-step;
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
    }
    .step-list > li {
        counter-increment: manual-step;
        position: relative;
        padding-left: 2.25rem;
        margin-bottom: 0.65rem;
        line-height: 1.5;
    }
    .step-list > li:last-child {
        margin-bottom: 0;
    }
    .step-list > li::before {
        content: counter(manual-step);
        position: absolute;
        left: 0;
        top: 0.1rem;
        width: 1.55rem;
        height: 1.55rem;
        border-radius: 50%;
        background-color: #e7f1ff;
        color: #0d6efd;
        font-weight: 700;
        font-size: 0.78rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #b6d4fe;
    }
    .breadcrumb-path-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.4rem;
        padding: 0.45rem 0.75rem;
        font-size: 0.85rem;
    }
    .example-callout {
        background-color: #f0fdf4;
        border-left: 4px solid #198754;
        border-radius: 0.375rem;
        padding: 0.7rem 0.9rem;
        font-size: 0.88rem;
    }
    .validation-callout {
        background-color: #fffbeb;
        border-left: 4px solid #f59e0b;
        border-radius: 0.375rem;
        padding: 0.7rem 0.9rem;
        font-size: 0.86rem;
    }
    .cat-filter-btn.active {
        background-color: #0d6efd !important;
        color: #fff !important;
        border-color: #0d6efd !important;
    }
    @media print {
        .story-card {
            break-inside: avoid;
            page-break-inside: avoid;
            box-shadow: none !important;
            border: 1px solid #ccc !important;
        }
    }
</style>

<div class="container my-3">
    <?php
    $allRoleManualLinks = [
        'superadmin'       => ['label' => 'SuperAdmin',       'url' => 'superadminusermanual.php'],
        'admin'            => ['label' => 'Principal / Admin','url' => 'adminusermanual.php'],
        'academic_section' => ['label' => 'Academic Section', 'url' => 'academicsectionusermanual.php'],
        'hod'              => ['label' => 'HOD',              'url' => 'hodusermanual.php'],
        'faculty'          => ['label' => 'Faculty',          'url' => 'facusermanual.php'],
    ];

    $accessibleRoleKeys = match ($activeSessionRole) {
        'superadmin'       => ['superadmin', 'admin', 'academic_section', 'hod', 'faculty'],
        'admin'            => ['admin', 'hod', 'faculty'],
        'academic_section' => ['academic_section', 'faculty'],
        'hod'              => ['hod', 'faculty'],
        default            => [],
    };

    if ($activeSessionRole !== 'superadmin') {
        $accessibleRoleKeys = array_values(array_filter($accessibleRoleKeys, function ($rKey) {
            return \FeatureManager::isUserManualVisible($rKey);
        }));
    }

    $switcherTitle = match ($activeSessionRole) {
        'superadmin'       => 'SuperAdmin Role Manual Inspector:',
        'admin'            => 'Principal Role Manual Switcher:',
        'academic_section' => 'Academic Section Manual Switcher:',
        'hod'              => 'Department Manual Switcher:',
        default            => 'Role Manual Switcher:',
    };
    ?>
    <?php if (count($accessibleRoleKeys) > 1): ?>
        <!-- Role Manual Switcher Bar -->
        <div class="card shadow-sm border-0 mb-3 bg-white dontprint">
            <div class="card-body py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small fw-bold text-secondary">
                    <i class="bi bi-shield-check text-primary me-1"></i><?= htmlspecialchars($switcherTitle); ?>
                </span>
                <div class="d-flex flex-wrap gap-1">
                    <?php
                    foreach ($accessibleRoleKeys as $rKey):
                        $rInfo = $allRoleManualLinks[$rKey];
                        $isCurrentRole = ($manual['role'] === $rKey);
                    ?>
                        <a href="<?= htmlspecialchars($rInfo['url']); ?>" class="btn btn-sm <?= $isCurrentRole ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            <?= htmlspecialchars($rInfo['label']); ?> Manual
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hero Banner -->
    <div class="card manual-hero shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-primary fw-semibold px-2 py-1">
                            <i class="bi bi-sliders me-1"></i><?= htmlspecialchars($manual['role_label']); ?>
                        </span>
                        <span class="badge bg-white bg-opacity-25 text-white fw-normal">
                            <i class="bi bi-check2-circle me-1"></i><?= (int)$manual['total_stories']; ?> Enabled Workflows across <?= (int)$manual['total_categories']; ?> Modules
                        </span>
                    </div>
                    <h3 class="mb-1 fw-bold">
                        <i class="bi bi-book-half me-2"></i><?= htmlspecialchars($manual['portal_title']); ?>
                    </h3>
                    <p class="mb-0 text-white-50 small">
                        <?= htmlspecialchars($manual['subtitle']); ?>
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 dontprint">
                    <a href="<?= htmlspecialchars($manual['pdf_url']); ?>" target="_blank" class="btn btn-light text-danger fw-semibold shadow-sm">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i>View / Print PDF
                    </a>
                    <a href="<?= htmlspecialchars($manual['pdf_url']); ?>?download=1" class="btn btn-warning text-dark fw-semibold shadow-sm">
                        <i class="bi bi-download me-1"></i>Download PDF Manual
                    </a>
                    <?php
                    $userHomeUrl = match ($activeSessionRole) {
                        'superadmin'       => 'superadminhome.php',
                        'admin'            => 'adminhome.php',
                        'academic_section' => 'academicsectionhome.php',
                        'hod'              => 'hodhome.php',
                        'faculty'          => 'fachome.php',
                        default            => $manual['home_url'],
                    };
                    ?>
                    <a href="<?= htmlspecialchars($userHomeUrl); ?>" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left me-1"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Category Filter Bar -->
    <div class="card shadow-sm border-0 mb-4 dontprint">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" id="manualSearchInput" class="form-control" placeholder="Search by feature, button name, or topic...">
                        <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn" style="display:none;">Clear</button>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="d-flex flex-wrap gap-1 justify-content-md-end" id="categoryFilterBar">
                        <button type="button" class="btn btn-sm btn-outline-primary cat-filter-btn active" data-cat="all">
                            All (<?= (int)$manual['total_stories']; ?>)
                        </button>
                        <?php foreach ($manual['categories'] as $cat): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary cat-filter-btn" data-cat="<?= htmlspecialchars($cat['id']); ?>">
                                <i class="bi <?= htmlspecialchars($cat['icon']); ?> me-1"></i>Part <?= htmlspecialchars($cat['Roman']); ?> (<?= count($cat['stories']); ?>)
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Layout: Sidebar TOC + User Story Cards -->
    <div class="row g-4">
        <!-- Left Sidebar: Sticky Table of Contents -->
        <div class="col-lg-3 dontprint">
            <div class="card shadow-sm border-0 manual-toc-sticky">
                <div class="card-header bg-white fw-bold py-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-1 text-primary"></i>Contents</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill"><?= (int)$manual['total_stories']; ?> Workflows</span>
                </div>
                <div class="card-body p-2">
                    <?php foreach ($manual['categories'] as $cat): ?>
                        <div class="mb-2 toc-category-group" data-cat="<?= htmlspecialchars($cat['id']); ?>">
                            <div class="px-2 py-1 text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                <i class="bi <?= htmlspecialchars($cat['icon']); ?> me-1"></i>Part <?= htmlspecialchars($cat['Roman']); ?>: <?= htmlspecialchars($cat['title']); ?>
                            </div>
                            <nav class="nav flex-column">
                                <?php foreach ($cat['stories'] as $story): ?>
                                    <a class="manual-toc-link" href="#story-<?= htmlspecialchars($story['id']); ?>" data-story-id="<?= htmlspecialchars($story['id']); ?>">
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size: 0.7rem;"><?= (int)$story['number']; ?></span>
                                        <span><?= htmlspecialchars($story['title']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-light small text-muted py-2">
                    <i class="bi bi-info-circle me-1"></i>Filtered automatically for active features.
                </div>
            </div>
        </div>

        <!-- Right Column: Step-by-Step User Stories -->
        <div class="col-lg-9" id="manualStoriesContainer">
            <!-- Quick Start Workflow Strip -->
            <?php if (!empty($manual['quick_start'])): ?>
                <div class="card shadow-sm border-0 mb-4 bg-white" id="quickStartBanner">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-primary mb-2">
                            <i class="bi bi-compass me-1"></i><?= htmlspecialchars($manual['role_label']); ?> Key Operational Flow at a Glance
                        </h6>
                        <div class="row g-2 small text-muted">
                            <?php foreach ($manual['quick_start'] as $qs): ?>
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-2 border rounded bg-light h-100">
                                        <strong class="text-dark d-block mb-1"><?= htmlspecialchars($qs['title']); ?></strong>
                                        <?= \Services\UserManualService::formatWebText($qs['text']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Empty Search Result Alert -->
            <div id="noSearchResultsAlert" class="alert alert-warning shadow-sm" style="display:none;">
                <i class="bi bi-search me-2"></i>No user manual sections matched your search filter. Try clearing the search box or selecting <strong>All</strong> categories.
            </div>

            <?php foreach ($manual['categories'] as $cat): ?>
                <div class="manual-category-section mb-4" data-cat="<?= htmlspecialchars($cat['id']); ?>">
                    <!-- Category Header -->
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= htmlspecialchars($cat['color']); ?> px-2 py-1">
                                Part <?= htmlspecialchars($cat['Roman']); ?>
                            </span>
                            <h5 class="mb-0 fw-bold text-dark">
                                <i class="bi <?= htmlspecialchars($cat['icon']); ?> text-<?= htmlspecialchars($cat['color']); ?> me-1"></i><?= htmlspecialchars($cat['title']); ?>
                            </h5>
                        </div>
                        <span class="text-muted small"><?= count($cat['stories']); ?> guide(s)</span>
                    </div>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($cat['description']); ?></p>

                    <!-- User Stories in Category -->
                    <?php foreach ($cat['stories'] as $story): ?>
                        <div class="card story-card bg-white mb-3"
                             id="story-<?= htmlspecialchars($story['id']); ?>"
                             data-cat="<?= htmlspecialchars($cat['id']); ?>"
                             data-search="<?= htmlspecialchars(strtolower($story['title'] . ' ' . $story['nav_path'] . ' ' . $story['purpose'] . ' ' . implode(' ', $story['steps']) . ' ' . $story['example'])); ?>">
                            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="story-number-badge bg-<?= htmlspecialchars($cat['color']); ?>-subtle text-<?= htmlspecialchars($cat['color']); ?> border border-<?= htmlspecialchars($cat['color']); ?>-subtle">
                                        <?= (int)$story['number']; ?>
                                    </span>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">
                                            <i class="bi <?= htmlspecialchars($story['icon']); ?> text-<?= htmlspecialchars($cat['color']); ?> me-1"></i><?= htmlspecialchars($story['title']); ?>
                                        </h6>
                                        <span class="text-muted" style="font-size: 0.76rem;">
                                            Module: <strong><?= htmlspecialchars($story['module_name']); ?></strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($story['access_mode'] === 'READONLY'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="bi bi-lock-fill me-1"></i>Read-Only Mode
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Active
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($story['route_url'])): ?>
                                        <a href="<?= htmlspecialchars($story['route_url']); ?>" class="btn btn-sm btn-outline-<?= htmlspecialchars($cat['color']); ?> dontprint">
                                            <i class="bi bi-box-arrow-up-right me-1"></i><?= htmlspecialchars($story['route_label'] ?? 'Open Page'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card-body p-3">
                                <?php if (!empty($story['readonly_notice'])): ?>
                                    <div class="alert alert-warning py-2 px-3 small d-flex align-items-center mb-3">
                                        <i class="bi bi-lock-fill me-2 fs-6"></i>
                                        <div><?= htmlspecialchars($story['readonly_notice']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Navigation Path -->
                                <div class="breadcrumb-path-box mb-3">
                                    <span class="text-muted fw-semibold me-1"><i class="bi bi-signpost-split text-primary me-1"></i>Where to click:</span>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($story['nav_path']); ?></span>
                                </div>

                                <!-- Purpose -->
                                <p class="mb-3 text-secondary" style="font-size: 0.92rem;">
                                    <strong class="text-dark"><i class="bi bi-bullseye me-1 text-primary"></i>Purpose:</strong>
                                    <?= \Services\UserManualService::formatWebText($story['purpose']); ?>
                                </p>

                                <!-- Prerequisites -->
                                <?php if (!empty($story['prerequisites'])): ?>
                                    <div class="mb-3">
                                        <div class="fw-bold text-dark small mb-1">
                                            <i class="bi bi-check2-all text-success me-1"></i>Before You Start (Prerequisites):
                                        </div>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <?php foreach ($story['prerequisites'] as $prereq): ?>
                                                <li><?= \Services\UserManualService::formatWebText($prereq); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- Step-by-Step Instructions -->
                                <div class="mb-3">
                                    <div class="fw-bold text-dark small mb-2">
                                        <i class="bi bi-list-ol text-primary me-1"></i>Step-by-Step Process:
                                    </div>
                                    <ol class="step-list small text-dark">
                                        <?php foreach ($story['steps'] as $stepText): ?>
                                            <li><?= \Services\UserManualService::formatWebText($stepText); ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>

                                <!-- Practical Example -->
                                <?php if (!empty($story['example'])): ?>
                                    <div class="example-callout mb-3">
                                        <div class="fw-bold text-success-emphasis mb-1">
                                            <i class="bi bi-lightbulb-fill text-success me-1"></i>Practical Example:
                                        </div>
                                        <div class="text-dark"><?= \Services\UserManualService::formatWebText($story['example']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Validations & Important Rules -->
                                <?php if (!empty($story['validations'])): ?>
                                    <div class="validation-callout">
                                        <div class="fw-bold text-warning-emphasis mb-1">
                                            <i class="bi bi-shield-check me-1"></i>System Rules &amp; Helpful Checks:
                                        </div>
                                        <ul class="mb-0 ps-3 text-dark">
                                            <?php foreach ($story['validations'] as $valRule): ?>
                                                <li><?= \Services\UserManualService::formatWebText($valRule); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('manualSearchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const filterBtns = document.querySelectorAll('.cat-filter-btn');
    const categorySections = document.querySelectorAll('.manual-category-section');
    const tocGroups = document.querySelectorAll('.toc-category-group');
    const noResultsAlert = document.getElementById('noSearchResultsAlert');

    let activeCategory = 'all';

    function applyFilters() {
        const query = (searchInput.value || '').trim().toLowerCase();
        clearBtn.style.display = query.length > 0 ? 'inline-block' : 'none';

        let totalVisibleStories = 0;

        categorySections.forEach(function(section) {
            const catId = section.getAttribute('data-cat');
            const matchesCat = (activeCategory === 'all' || activeCategory === catId);
            const cards = section.querySelectorAll('.story-card');
            let visibleInSection = 0;

            cards.forEach(function(card) {
                const hay = card.getAttribute('data-search') || '';
                const matchesQuery = (query === '' || hay.indexOf(query) !== -1);
                if (matchesCat && matchesQuery) {
                    card.style.display = '';
                    visibleInSection++;
                    totalVisibleStories++;
                } else {
                    card.style.display = 'none';
                }
            });

            section.style.display = visibleInSection > 0 ? '' : 'none';
        });

        tocGroups.forEach(function(group) {
            const catId = group.getAttribute('data-cat');
            group.style.display = (activeCategory === 'all' || activeCategory === catId) ? '' : 'none';
        });

        if (noResultsAlert) {
            noResultsAlert.style.display = totalVisibleStories === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            applyFilters();
            searchInput.focus();
        });
    }

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeCategory = this.getAttribute('data-cat') || 'all';
            applyFilters();
        });
    });
});
</script>
