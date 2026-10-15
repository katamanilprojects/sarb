<?php
/**
 * Partial: Student Qualitative Remarks on Faculty
 * Reusable collapsible card for faculty-level and subject-level views.
 * Anonymizes student identity to Student-N to enforce privacy policy.
 *
 * Expected variables in scope:
 * - $remarks (array)
 * - $collapseId (string)
 * - $showSubjectLabel (bool, optional, default false)
 * - $metaSubCode (string, optional)
 * - $metaSubName (string, optional)
 */

if (empty($remarks)) {
    return;
}

$collapseId = $collapseId ?? 'facRemarksCollapse';
$showSubjectLabel = $showSubjectLabel ?? false;
$metaSubCode = $metaSubCode ?? '';
$metaSubName = $metaSubName ?? '';
$singleSubjectHeader = (!empty($metaSubCode) || !empty($metaSubName)) ? trim("$metaSubCode - $metaSubName", " -") : '';
?>
<div class="card mb-4 border shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2" 
         role="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId) ?>" aria-expanded="false" aria-controls="<?= htmlspecialchars($collapseId) ?>"
         style="cursor: pointer;">
        <div>
            <h6 class="mb-0 text-secondary fw-bold d-inline-block">
                <i class="bi bi-chat-quote-fill text-info me-2"></i>Student Qualitative Remarks on Faculty
                <span class="badge bg-secondary ms-2"><?= count($remarks) ?> Feedback Comments</span>
            </h6>
            <?php if (!empty($singleSubjectHeader) && !$showSubjectLabel): ?>
                <span class="text-muted small ms-2 d-none d-md-inline">• <?= htmlspecialchars($singleSubjectHeader) ?></span>
            <?php endif; ?>
        </div>
        <span class="text-primary small fw-semibold">
            <i class="bi bi-chevron-down"></i> Click to View / Hide
        </span>
    </div>
    <div id="<?= htmlspecialchars($collapseId) ?>" class="collapse">
        <div class="card-body">
            <div class="row g-3">
                <?php $facRemSno = 1; foreach ($remarks as $rem): ?>
                    <div class="col-md-6">
                        <div class="card bg-light border-0 shadow-sm p-3 h-100">
                            <div class="d-flex justify-content-between text-muted small mb-2">
                                <?php 
                                    $sCode = $rem['subcode'] ?? $metaSubCode;
                                    $sName = $rem['sub_fullname'] ?? $metaSubName;
                                    $sLabel = (!empty($sCode) || !empty($sName)) ? trim("$sCode - $sName", " -") : '';
                                ?>
                                <strong>
                                    Student-<?= $facRemSno++ ?>
                                    <?php if ($showSubjectLabel && !empty($sLabel)): ?>
                                        <span class="text-muted fw-normal ms-1">(<?= htmlspecialchars($sLabel) ?>)</span>
                                    <?php endif; ?>
                                </strong>
                                <span><?= htmlspecialchars($rem['submitted_at'] ?? '') ?></span>
                            </div>
                            <?php if (!empty($rem['faculty_strengths'])): ?>
                                <p class="mb-1 small"><strong>Strengths:</strong> <?= nl2br(htmlspecialchars($rem['faculty_strengths'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($rem['improvement_areas'])): ?>
                                <p class="mb-1 small"><strong>Areas for Improvement:</strong> <?= nl2br(htmlspecialchars($rem['improvement_areas'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($rem['additional_comments'])): ?>
                                <p class="mb-0 small"><strong>Additional Comments:</strong> <?= nl2br(htmlspecialchars($rem['additional_comments'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
