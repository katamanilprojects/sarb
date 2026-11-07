        <div class="alert alert-light border small">Question-wise CO values use selected question evidence. Values inferred from aggregate marks or grades are estimates. Percentages use evaluated students; missing coverage is reported separately and remains provisional. Final marks use the configured maximum and scaling rules.</div><div class="card">
            <div class="card-header bg-secondary text-white">
                <h4 class="mb-1">Analysis for: <?php echo htmlspecialchars($subject_details_header); ?></h4>
                <h6 class="mb-0">Assessment Scope:
                    <?php
                    $is_lab_scope = (isset($selected_sub_type) && ($selected_sub_type === 'lab' || $selected_sub_type === 'dti'));
                    if (strcasecmp((string)$selected_assessment_number, 'see') === 0) {
                        echo "Semester End Examination (SEE)";
                    } elseif ($is_lab_scope) {
                        echo "Lab CIA";
                    } elseif ($selected_assessment_number === 'all') {
                        echo "Overall CIA";
                    } elseif (is_numeric($selected_assessment_number)) {
                        echo "CIA - " . htmlspecialchars($selected_assessment_number);
                    } else {
                        echo "Not Selected";
                    }
                    ?>
                </h6>
            </div>
            <div class="card-body">
                <?php
                // Check if SEE marks exist for the current course
                require_once __DIR__ . '/seeassessment.class.php';
                require_once __DIR__ . '/services/FeatureManager.php';
                $seeCheckObj = new SEEAssessment();
                $hasSeeMarks = false;
                if (!empty($selected_sub_id)) {
                    $firstSubId = is_numeric($selected_sub_id) ? (int)$selected_sub_id : (int)explode(',', (string)$selected_sub_id)[0];
                    $hasSeeMarks = $seeCheckObj->isSEEMarksSubmitted($firstSubId);
                }
                $isSeeEnabled = \Services\FeatureManager::getInstance()->isModuleEnabled('MOD_SEE_MARKS');
                $isCiaEnabled = \Services\FeatureManager::getInstance()->isModuleEnabled('MOD_CIA_MARKS');
                ?>

                <ul class="nav nav-tabs mb-3" id="analysisTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-tab-pane" type="button" role="tab" aria-controls="overview-tab-pane" aria-selected="true">Overview</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="outcome-tab" data-bs-toggle="tab" data-bs-target="#outcome-tab-pane" type="button" role="tab" aria-controls="outcome-tab-pane" aria-selected="false">Outcome Analysis (CO/PO)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="blooms-tab" data-bs-toggle="tab" data-bs-target="#blooms-tab-pane" type="button" role="tab" aria-controls="blooms-tab-pane" aria-selected="false">Cognitive Level (Bloom's)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="copo-matrix-tab" data-bs-toggle="tab" data-bs-target="#copo-matrix-tab-pane" type="button" role="tab" aria-controls="copo-matrix-tab-pane" aria-selected="false">CO-PO Matrix</button>
                    </li>
                    <?php if ($isSeeEnabled): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="comprehensive-tab" data-bs-toggle="tab" data-bs-target="#comprehensive-tab-pane" type="button" role="tab" aria-controls="comprehensive-tab-pane" aria-selected="false"><i class="bi bi-award me-1"></i>Direct & Overall Attainment (NBA/NAAC)</button>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced-tab-pane" type="button" role="tab" aria-controls="advanced-tab-pane" aria-selected="false">Other Insights</button>
                    </li>
                </ul>

                <div class="tab-content" id="analysisTabContent">

                    <div class="tab-pane fade show active" id="overview-tab-pane" role="tabpanel" aria-labelledby="overview-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Assessment Performance Snapshot</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Displays the overall student performance percentage for the selected assessment scope (e.g., a specific CIA or Overall).<br>
                                            <i>Benefit:</i> Provides a clear view of performance on individual assessments or allows comparison when 'Overall CIA' is selected.
                                        </p>
                                        <div id="assessmentChartContainer" class="chart-container"><canvas id="assessmentChart"></canvas></div>
                                        <div id="assessmentStatWidget" style="display:none;"></div>
                                        <div id="assessmentChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Component-wise Performance</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Displays average attainment percentage across evaluation components (Subjective, Objective, Assignment for Theory; Day-to-Day vs. Internal Exam for Lab).<br>
                                            <i>Benefit:</i> Pinpoints format-specific strengths/weaknesses and compares continuous lab work against formal exam execution.
                                        </p>
                                        <div class="chart-container"><canvas id="componentChart"></canvas></div>
                                        <div id="componentChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="outcome-tab-pane" role="tabpanel" aria-labelledby="outcome-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">CO Attainment Analysis</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Displays the achievement level for each Course Outcome (CO). <br>
                                            <i>Benefit:</i> Pinpoints specific learning objectives where students are meeting or falling short of expectations.
                                        </p>
                                        <div class="chart-container"><canvas id="coChart"></canvas></div>
                                        <div id="coChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">PO Attainment Analysis</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Shows the calculated achievement level for each Program Outcome (PO) based on mapped COs. <br>
                                            <i>Benefit:</i> Provides insight into how the course contributes to broader program-level objectives (OBE).
                                        </p>
                                        <div class="chart-container"><canvas id="poChart"></canvas></div>
                                        <div id="poChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="blooms-tab-pane" role="tabpanel" aria-labelledby="blooms-tab" tabindex="0">
                        <div class="row justify-content-center">
                            <div class="col-md-8 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Bloom's Taxonomy Performance</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Analyzes student performance across different cognitive levels (Remember, Understand, Apply, etc.). <br>
                                            <i>Benefit:</i> Helps assess if students are developing both foundational knowledge and higher-order thinking skills.
                                        </p>
                                        <div class="chart-container"><canvas id="bloomsChart"></canvas></div>
                                        <div id="bloomsChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="copo-matrix-tab-pane" role="tabpanel" aria-labelledby="copo-matrix-tab" tabindex="0">
                        <div class="row justify-content-center">
                            <div class="col-md-10 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">CO-PO Attainment Matrix</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Lists each Course Outcome (CO), its attainment percentage, and the Program Outcomes (POs) it maps to. <br>
                                            <i>Benefit:</i> Provides a detailed view of how specific course learning objectives contribute to program outcomes and identifies potentially weak links.
                                        </p>
                                        <div id="coPoMatrixTableContainer" class="table-responsive mt-2">
                                            <p>Loading matrix data...</p>
                                        </div>
                                        <div id="coPoMatrixSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isSeeEnabled): ?>
                    <div class="tab-pane fade" id="comprehensive-tab-pane" role="tabpanel" aria-labelledby="comprehensive-tab" tabindex="0">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body bg-light rounded">
                                <h5 class="fw-bold mb-1"><i class="bi bi-award-fill text-primary me-2"></i>Comprehensive OBE Outcome Attainment (NBA / NAAC Compliance)</h5>
                                <p class="text-muted small mb-2">
                                    Calculates finalized Course Outcome (CO) and Program Outcome (PO/PSO) attainment by weighting Continuous Internal Assessment (CIA), Semester End Examination (SEE), and Indirect Student Course-End Feedback.
                                </p>
                                <div id="comprehensiveWeightsBanner" class="d-flex flex-wrap gap-2 pt-1">
                                    <span class="badge bg-secondary p-2">Loading regulation parameters...</span>
                                </div>
                            </div>
                        </div>

                        <!-- CO Attainment Table Card -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2 text-primary"></i>Course Outcomes (CO) Direct, Indirect & Overall Attainment Matrix</h6>
                                <span id="seeSubmissionBadge" class="badge bg-secondary fs-6"><i class="bi bi-hourglass-split me-1"></i>Checking SEE...</span>
                            </div>
                            <div class="card-body p-0">
                                <div id="comprehensiveCoTableContainer" class="table-responsive">
                                    <p class="p-3 text-muted mb-0">Loading Comprehensive Attainment matrix...</p>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0 pt-0">
                                <div id="comprehensiveSuggestions" class="suggestions alert alert-info small mt-3">Loading suggestions...</div>
                            </div>
                        </div>

                        <!-- Chart: Side-by-Side Comparison -->
                        <div class="row">
                            <div class="col-lg-7 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-white py-3">
                                        <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-fill me-2 text-success"></i>CO Attainment Levels Comparison (0.0 to 3.0 Scale)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container" style="min-height: 350px;">
                                            <canvas id="comprehensiveChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-white py-3">
                                        <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3-fill me-2 text-info"></i>Program Outcome (PO / PSO) Attainment</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div id="comprehensivePoTableContainer" class="table-responsive" style="max-height: 380px;">
                                            <p class="p-3 text-muted mb-0">Loading PO Attainment matrix...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="tab-pane fade" id="advanced-tab-pane" role="tabpanel" aria-labelledby="advanced-tab" tabindex="0">
                        <div class="row">
                            <!-- Student-wise CO Spread -->
                            <div class="col-md-12 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Student-wise CO Distribution</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Visualizes how students are distributed in performance across each CO. <br>
                                            <i>Benefit:</i> Useful for identifying if averages are skewed by outliers or if many students are underachieving.
                                        </p>
                                        <div class="chart-container"><canvas id="coSpreadChart"></canvas></div>
                                        <div id="coSpreadSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Question Difficulty Analysis -->
                            <div class="col-md-12 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Question Difficulty Overview</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Shows difficulty levels and variation for each question. <br>
                                            <i>Benefit:</i> Helps refine question setting and alignment with learning outcomes.
                                        </p>
                                        <div id="questionDifficultyTable" class="table-responsive mt-2">
                                            <p>Loading difficulty data...</p>
                                        </div>
                                        <div id="questionDiffSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>

                            <?php
                            $isFacultyReport = (!empty($is_faculty_cia_report) || basename($_SERVER['SCRIPT_NAME'] ?? '') === 'facciaanalysis2.php');
                            if ($isFacultyReport):
                            ?>
                            <!-- Zero Marks Executive Overview & Graphical Analysis -->
                            <div class="col-md-12 mb-4" id="zeroMarksSectionCard">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                            <h5 class="card-title mb-1">Zero Marks Analysis</h5>
                                            <span class="badge bg-secondary-subtle text-secondary border small">Faculty Executive Diagnostic</span>
                                        </div>
                                        <p class="card-text small text-muted mb-2">
                                            <i>Purpose:</i> Visual overview of recorded zero marks across questions and Course Outcomes (COs) for the selected CIA assessment scope.<br>
                                            <i>Benefit:</i> Pinpoints questions and learning outcomes with high non-attempt or zero-score rates to target academic support.
                                        </p>
                                        
                                        <!-- Faculty-facing Explanation Callout -->
                                        <div class="alert alert-light border small text-muted my-3 py-2 px-3">
                                            <strong>About recorded zeros:</strong> This count shows recorded zeros. A student may have not attempted the question or may have secured zero. The recorded mark alone cannot distinguish between the two. Historical zeros may also include placeholders or values saved as zero by older imports. A student may appear in more than one CO row if they have recorded zeros across questions mapped to different COs. Zero total across mapped questions reflects evaluated students whose earned sum across mapped CIA questions is zero.
                                        </div>

                                        <!-- Executive KPI Summary Cards -->
                                        <div id="zeroMarksKpiCards" class="row g-2 mb-4">
                                            <div class="col-12 text-center py-2 text-muted small">Loading summary metrics...</div>
                                        </div>

                                        <!-- Visual Graphs Row -->
                                        <div id="zeroMarksChartsRow" class="row mb-4">
                                            <div class="col-lg-7 mb-3">
                                                <div class="border rounded p-3 h-100 bg-white shadow-xs">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="fw-bold mb-0 text-dark">Question Zero-Marks Distribution</h6>
                                                        <span class="badge bg-light text-muted border small">Evaluated vs. Recorded Zeros</span>
                                                    </div>
                                                    <p class="small text-muted mb-2">
                                                        Shows recorded zeros among recorded scores. Choice questions with unknown selection applicability are distinguished.
                                                    </p>
                                                    <div class="chart-container" style="min-height: 270px;">
                                                        <canvas id="zeroMarksQuestionChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5 mb-3">
                                                <div class="border rounded p-3 h-100 bg-white shadow-xs">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="fw-bold mb-0 text-dark">Course Outcome (CO) Impact</h6>
                                                        <span class="badge bg-light text-muted border small">Distinct Students</span>
                                                    </div>
                                                    <p class="small text-muted mb-2">
                                                        Students with a recorded zero on at least one mapped question per CO.
                                                    </p>
                                                    <div class="chart-container" style="min-height: 270px;">
                                                        <canvas id="zeroMarksCoChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Course Outcome (CO) Summary -->
                                        <div class="mt-3 pt-3 border-top">
                                            <h6 class="fw-bold mb-1">Course Outcome (CO) Summary</h6>
                                            <p class="small text-muted mb-2">
                                                Students with a recorded zero on at least one mapped question. Each student is counted once per CO in the selected scope.
                                            </p>
                                            <div id="zeroMarksCoSummaryContainer" class="table-responsive">
                                                <p class="text-muted small">Loading CO summary...</p>
                                            </div>
                                        </div>

                                        <div id="zeroMarksSuggestions" class="suggestions mt-2 alert alert-info small" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted small">
                <?php
                require_once __DIR__ . '/facciaanalysis2.class.php';
                $coAnalysisFooter = new COAnalysis();
                $targetThreshFooter = !empty($selected_sub_id) ? $coAnalysisFooter->getTargetAttainmentThreshold($selected_sub_id) : 60.0;
                ?>
                Analysis generated on: <?php echo date('Y-m-d H:i:s'); ?>. Target Attainment Threshold set at <?php echo rtrim(rtrim(number_format($targetThreshFooter, 2), '0'), '.'); ?>% for suggestions.
                <?php
                // Check if ANY data was found for the subject - basic check using one endpoint
                //$facanalysisObj = new COAnalysis(); // Already instantiated
                //$resJson = $facanalysisObj->getAssessmentPerformance($selected_sub_id); // Fetch assessment data
                //$res = json_decode($resJson, true);
                //if (empty($res['data'])) {
                //    echo '<br><strong>Note: No assessment mark data found for this subject. Analysis may be incomplete. Please ensure detailed CIA marks are entered.</strong>';
                // }
                // This check is now less reliable due to AJAX loading, maybe remove or adapt
                ?>
            </div>
            <div class="card-footer">
                <p class="text-muted">Click on each section to view the formula and chart description.</p>
                <div class="mb-3">
                    <button class="btn btn-sm btn-primary me-2" onclick="expandAll()">&#x25BC;</button>
                    <button class="btn btn-sm btn-secondary" onclick="collapseAll()">&#x25B2;</button>
                </div>

                <?php
                require_once("ciaanalysis2_common.php");
                echo renderAccordion();
                echo renderExpandCollapseScripts();
                ?>
            </div>
            <div class="card-footer bg-light py-3 border-top d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-arrow-down me-1"></i>Course Documents & Reports</h6>
                    <span class="text-muted small">Generate and download official PDF dossiers for departmental audits<!-- and NBA/NAAC accreditations-->.</span>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                    <a href="download_feedback_enhanced.php?format=pdf&level=subject&sub_id=<?php echo htmlspecialchars($selected_sub_id); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-chat-square-quote me-1"></i> Feedback Report
                    </a>
                    <a href="download_obe_analysis.php?sub_id=<?php echo htmlspecialchars($selected_sub_id); ?>" target="_blank" class="btn btn-danger btn-sm shadow-sm">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> OBE Analytics Dossier <?php echo $hasSeeMarks ? '' : '<span class="badge bg-warning text-dark ms-1" style="font-size:0.7em;">CIA Stage</span>'; ?>
                    </a>
                    <?php if ($hasSeeMarks && \FeatureManager::isFacultyVisible('MOD_SAR_FORM_C3')): ?>
                    <a href="download_obe_analysis.php?action=sar_form_c3&sub_id=<?php echo htmlspecialchars($selected_sub_id); ?>" target="_blank" class="btn btn-warning btn-sm shadow-sm text-dark">
                        <i class="bi bi-file-earmark-ruled me-1"></i> NBA SAR Form C-3
                    </a>
                    <?php endif; ?>
                    <a href="download_bluebook.php?sub_id=<?php echo htmlspecialchars($selected_sub_id); ?>" target="_blank" class="btn btn-success btn-sm shadow-sm">
                        <i class="bi bi-file-earmark-pdf me-1"></i> e-Bluebook Course File
                    </a>
                </div>
            </div>
        </div>
