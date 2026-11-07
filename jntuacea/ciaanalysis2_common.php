<?php
function renderAccordionItem($id, $title, $formula) {
    return "
        <div class=\"accordion-item\">
            <h2 class=\"accordion-header\" id=\"heading{$id}\">
                <button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#collapse{$id}\" aria-expanded=\"false\" aria-controls=\"collapse{$id}\">
                    {$title}
                </button>
            </h2>
            <div id=\"collapse{$id}\" class=\"accordion-collapse collapse\" data-bs-parent=\"#formulaAccordion\">
                <div class=\"accordion-body\">
                    {$formula}
                </div>
            </div>
        </div>
    ";
}

function renderAccordion() {
    require_once __DIR__ . '/services/FeatureManager.php';
    $showCia = \Services\FeatureManager::getInstance()->isModuleEnabled('MOD_CIA_MARKS');
    $showSee = \Services\FeatureManager::getInstance()->isModuleEnabled('MOD_SEE_MARKS');

    // Note the double backslashes (\\_) used to safely pass literal \_ to MathJax through PHP strings
    $items = [];
    if ($showCia) {
        $items[] = [1, "Assessment Performance Snapshot (AssessmentChart)", "<p>Attainment %:</p><p>$$ \\text{Attainment %} = \\left( \\frac{\\sum \\text{obtained\\_marks}}{\\sum \\text{effective\\_max\\_marks}} \\right) \\times 100 $$</p><p class='small text-muted'>* For Theory subjects with choice questions (Q1/Q2, Q3/Q4, Q5/Q6), effective marks use the answered choice pair max: \\(\\sum \\max(Q_{2i-1}, Q_{2i})\\) with total 30M maximum instead of doubling the denominator to 60M. For Lab subjects, reflects the formal Internal Exam (15M).</p>"];
        $items[] = [2, "Component-wise Performance (ComponentChart)", "<p>Attainment %:</p><p>$$ \\text{Attainment %} = \\left( \\frac{\\sum \\text{component\\_obtained}}{\\sum \\text{component\\_effective\\_max}} \\right) \\times 100 $$</p><p class='small text-muted'>* Subjective either/or choice pairs are resolved to student pair max score over pair max marks.</p>"];
    }
    $items[] = [3, "CO Attainment Analysis (COChart)", "<p>CO Attainment %:</p><p>$$ \\text{CO Attainment %} = \\left( \\frac{\\sum \\text{CO\\_obtained\\_marks}}{\\sum \\text{CO\\_effective\\_marks}} \\right) \\times 100 $$</p><p class='small text-muted'>* When either/or choice questions map to the target CO, attainment is calculated from the attempted question pair rather than penalizing unanswered choices.</p>"];
    $items[] = [4, "PO Attainment Analysis (POChart)", "<p>PO Attainment %:</p><p>$$ \\text{PO Attainment %} = \\frac{\\sum (\\text{CO\\_Attainment\\%} \\times \\text{Weightage})}{\\sum \\text{Weightage}} $$</p>"];
    $items[] = [5, "Bloom's Taxonomy Performance (BloomsChart)", "<p>Attainment %:</p><p>$$ \\text{Attainment %} = \\left( \\frac{\\sum \\text{Blooms\\_obtained\\_marks}}{\\sum \\text{Blooms\\_effective\\_marks}} \\right) \\times 100 $$</p>"];
    $items[] = [6, "Student-wise CO Distribution (COSpreadChart)", "<p>Avg Student-wise CO Distribution %:</p><p>$$ \\text{Avg CO Distribution %} = \\text{CO Attainment \\%} $$</p>"];
    $items[] = [7, "Question Difficulty Overview (QuestionDifficultyTable)", <<<HTML
        <p><strong>Average Difficulty %:</strong></p>
        <p>$$ \\text{Avg Difficulty %} = \\text{AVG}\\left( \\frac{\\text{student\\_marks\\_obtained}}{\\text{question\\_total\\_marks}} \\times 100 \\right) $$</p>
        <p><strong>Standard Deviation (Population):</strong></p>
        <p>$$ \\sigma = \\sqrt{ \\frac{ \\sum (x_i - \\mu)^2 }{ N } } $$</p>
        <ul>
            <li><strong>\( x_i \)</strong> = Individual student's marks obtained for the question</li>
            <li><strong>\( \mu \)</strong> = Average marks obtained for the question</li>
            <li><strong>\( N \)</strong> = Total number of students</li>
        </ul>
    HTML];

    if ($showSee) {
        $items[] = [8, "Comprehensive Direct & Overall Attainment (NBA/NAAC Compliance)", <<<HTML
            <p><strong>1. Direct Course Outcome Attainment:</strong></p>
            <p>$$ \\text{Direct Level} = (w_{\\text{cia}} \\times \\text{CIA Level}) + (w_{\\text{see}} \\times \\text{SEE Level}) $$</p>
            <p><strong>2. Overall Course Outcome Attainment:</strong></p>
            <p>$$ \\text{Overall Level} = (w_{\\text{direct}} \\times \\text{Direct Level}) + (w_{\\text{indirect}} \\times \\text{Indirect Feedback Level}) $$</p>
            <p><strong>3. Overall Program Outcome (PO / PSO) Attainment:</strong></p>
            <p>$$ \\text{PO Level} = \\frac{\\sum (\\text{Overall CO Level} \\times \\text{Weightage})}{\\sum \\text{Weightage}} $$</p>
            <p class='small text-muted'>* Default institutional weights under autonomous regulations: 30% CIA + 70% SEE for Direct Attainment; 80% Direct + 20% Indirect Student Feedback for Overall Attainment.</p>
        HTML];
    }


    $output = "<div class=\"accordion\" id=\"formulaAccordion\">";
    foreach ($items as $item) {
        $output .= renderAccordionItem($item[0], $item[1], $item[2]);
    }
    $output .= "</div>";
    return $output;
}

function renderExpandCollapseScripts() {
    return "
        <script>
            function expandAll() {
                document.querySelectorAll('.accordion-collapse').forEach(item => item.classList.add('show'));
            }
            function collapseAll() {
                document.querySelectorAll('.accordion-collapse').forEach(item => item.classList.remove('show'));
            }
        </script>
    ";
}

function renderAnalysisAssets() {
    return '
        <script defer src="https://cdn.jsdelivr.net/npm/mathjax@4/tex-mml-chtml.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <style>
            .chart-container {
                position: relative;
                width: 100%;
                min-height: 300px;
                padding-bottom: 20px;
            }
            .suggestions {
                font-size: 0.85rem;
                padding: 0.5rem 0.8rem;
            }
            #coPoMatrixTable {
                width: 100%;
                font-size: 0.9rem;
            }
            #coPoMatrixTable th,
            #coPoMatrixTable td {
                text-align: center;
                vertical-align: middle;
            }
            #coPoMatrixTable .low-attainment {
                background-color: #f8d7da;
                color: #842029;
                font-weight: bold;
            }
        </style>
    ';
}

function renderAnalysisScripts($selected_sub_id, $selected_assessment_number) {
    
    require_once __DIR__ . '/facciaanalysis2.class.php';
    $coAnalysis = new COAnalysis();
    $targetThreshold = $coAnalysis->getTargetAttainmentThreshold($selected_sub_id);
    $thresholdJson = json_encode($targetThreshold);
    $subIdJson = json_encode($selected_sub_id);
    $assessmentJson = json_encode($selected_assessment_number);

    $script = <<<'HTML'
    <script>
        const TARGET_THRESHOLD = __THRESHOLD__;
        const SUB_ID = __SUB_ID__;
        const ASSESSMENT_NUMBER = __ASSESSMENT__;

                // Function to generate random colors (improved)
                function generateDistinctColors(count) {
                    const colors = [];
                    const hueStep = 360 / count;
                    for (let i = 0; i < count; i++) {
                        // Use HSL for better distinctiveness - Fixed Saturation/Lightness
                        const hue = i * hueStep;
                        colors.push(`hsla(${hue}, 70%, 60%, 0.7)`); // Adjust S/L for desired look
                    }
                    return colors;
                }

                // Function to display suggestions
                function displaySuggestions(containerId, suggestions) {
                    const container = $(`#${containerId}`);
                    if (suggestions && suggestions.length > 0) {
                        let html = '<ul>';
                        suggestions.forEach(s => {
                            html += `<li>${s}</li>`;
                        });
                        html += '</ul>';
                        container.html(html).removeClass('alert-info').addClass('alert-warning'); // Use warning style for suggestions
                    } else {
                        container.html('No specific suggestions based on current data.').removeClass('alert-warning').addClass('alert-secondary');
                    }
                }

                // Chart Instances Holder
                let chartInstances = {};

                // Enhanced Chart Loading Function
                function loadChart(endpoint, canvasId, labelKey, valueKey, chartLabel, chartType = 'bar', isPercentage = true, suggestionsContainerId) {
                    // Destroy previous chart instance if exists
                    if (chartInstances[canvasId]) {
                        chartInstances[canvasId].destroy();
                    }

                    $.getJSON(`facciaanalysis2.class.php?action=${endpoint}&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            if (!response || !response.data || response.data.length === 0) {
                                console.warn(`No data returned for ${endpoint}`);
                                $(`#${canvasId}`).hide(); // Hide canvas if no data
                                $(`#${suggestionsContainerId}`).html('No data available for this chart.').addClass('alert-secondary');
                                return;
                            }
                            console.log(response);
                            $(`#${canvasId}`).show(); // Ensure canvas is visible

                            let labels = response.data.map(item => item[labelKey]);
                            let values = response.data.map(item => item[valueKey]);
                            let colors = generateDistinctColors(labels.length);

                            // Prepare dataset(s)
                            const datasets = [{
                                label: chartLabel,
                                data: values,
                                backgroundColor: colors,
                                borderColor: colors.map(c => c.replace('0.7', '1')), // Solid border
                                borderWidth: 1
                            }];

                            // Chart Configuration
                            const config = {
                                type: chartType,
                                data: {
                                    labels: labels,
                                    datasets: datasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false, // Allow chart to fill container height
                                    plugins: {
                                        legend: {
                                            display: true, // Keep legend for context
                                            position: 'top',
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    let label = context.dataset.label || '';
                                                    if (label) {
                                                        label += ': ';
                                                    }
                                                    const itemData = response.data[context.dataIndex];
                                                    if (context.parsed.y !== null && context.parsed.y !== undefined && (!itemData || itemData.is_assessed !== false)) {
                                                        const formattedVal = isPercentage ? parseFloat(context.parsed.y).toFixed(2) + '%' : context.parsed.y;
                                                        label += formattedVal;
                                                    } else {
                                                        label += 'Not Assessed in this Assessment';
                                                    }
                                                    // Add description on tooltip (example for CO chart)
                                                    if(endpoint === 'co_attainment' && itemData?.evidence_basis){label += `\nEvidence: ${itemData.evidence_basis}; estimated students: ${itemData.estimated_students || 0}; missing: ${itemData.missing_students || 0}`;}
                                                    if (endpoint === 'co_attainment' && itemData?.co_description) {
                                                        label += `\n(${itemData.co_description})`;
                                                    } else if (endpoint === 'po_attainment' && itemData?.po_description) {
                                                        label += `\n(${itemData.po_description})`;
                                                    }
                                                    return label;
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: isPercentage ? 100 : undefined, // Set max to 100 for percentages
                                            ticks: {
                                                callback: function(value) {
                                                    return value + (isPercentage ? '%' : ''); // Add % to Y-axis labels
                                                }
                                            }
                                        }
                                    }
                                }
                            };

                            // Render Chart
                            chartInstances[canvasId] = new Chart(document.getElementById(canvasId), config);

                            // Display Suggestions
                            if (suggestionsContainerId && response.suggestions) {
                                displaySuggestions(suggestionsContainerId, response.suggestions);
                            }

                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error(`Error fetching ${endpoint}:`, textStatus, errorThrown, jqXHR.responseText);
                            $(`#${suggestionsContainerId}`).html(`Error loading data for ${chartLabel}. Please check console or try again later.`).addClass('alert-danger');
                        });
                }

                // Function to load CO-PO Matrix Table
                function loadCoPoMatrix(containerId, suggestionsContainerId) {
                    const container = $(`#${containerId}`);
                    container.html('<p>Loading matrix data...</p>'); // Show loading state

                    $.getJSON(`facciaanalysis2.class.php?action=co_po_matrix&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            if (!response || !response.data || response.data.length === 0) {
                                container.html('<p class="text-muted">No CO-PO mapping data available for this subject or assessment scope.</p>');
                                if (response.suggestions) displaySuggestions(suggestionsContainerId, response.suggestions);
                                return;
                            }

                            let tableHtml = '<table id="coPoMatrixTable" class="table table-bordered table-striped table-hover">';
                            tableHtml += '<thead class="table-light"><tr><th>CO Label</th><th>CO Attainment (%)</th><th>Mapped PO</th><th>Weightage</th></tr></thead><tbody>';

                            response.data.forEach(item => {
                                // Apply conditional styling for low CO attainment
                                let attainmentClass = '';
                                let attainmentDisplay = item.co_attainment;
                                if (attainmentDisplay !== 'N/A' && attainmentDisplay !== null && attainmentDisplay !== undefined) {
                                    const numVal = parseFloat(attainmentDisplay);
                                    if (numVal < TARGET_THRESHOLD) {
                                        attainmentClass = 'low-attainment';
                                    }
                                    attainmentDisplay = numVal.toFixed(2) + '%';
                                } else {
                                    attainmentDisplay = 'N/A';
                                }


                                tableHtml += `<tr>
                                                <td>${item.co_label}</td>
                                                <td class="${attainmentClass}">${attainmentDisplay}</td>
                                                <td>${item.po_label}</td>
                                                <td>${item.weightage}</td>
                                            </tr>`;
                            });

                            tableHtml += '</tbody></table>';
                            container.html(tableHtml);

                            // Display Suggestions
                            if (suggestionsContainerId && response.suggestions) {
                                displaySuggestions(suggestionsContainerId, response.suggestions);
                            }
                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error('Error fetching CO-PO Matrix data:', textStatus, errorThrown, jqXHR.responseText);
                            container.html('<p class="text-danger">Error loading CO-PO Matrix data.</p>');
                            $(`#${suggestionsContainerId}`).html('Error loading suggestions for CO-PO Matrix.').addClass('alert-danger');
                        });
                }

                function loadCOSpreadChart() {
                    $.getJSON(`facciaanalysis2.class.php?action=co_spread&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            if (!response.data || response.data.length === 0) {
                                $('#coSpreadChart').hide();
                                $('#coSpreadSuggestions').html('No data available.').addClass('alert-secondary');
                                return;
                            }

                            const labels = response.data.map(r => r.co_label);
                            const data = response.data.map(r => r.avg_attainment);

                            if (chartInstances['coSpreadChart']) chartInstances['coSpreadChart'].destroy();
                            chartInstances['coSpreadChart'] = new Chart(document.getElementById('coSpreadChart'), {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Avg Student-wise CO Distribution',
                                        data: data,
                                        backgroundColor: generateDistinctColors(data.length)
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            display: true
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    let label = context.dataset.label || '';
                                                    if (label) label += ': ';
                                                    if (context.parsed.y !== null && context.parsed.y !== undefined) {
                                                        label += parseFloat(context.parsed.y).toFixed(2) + '%';
                                                    } else {
                                                        label += 'Not Assessed in this Assessment';
                                                    }
                                                    return label;
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 100
                                        }
                                    }
                                }
                            });

                            displaySuggestions('coSpreadSuggestions', response.suggestions);
                        });
                }

                function loadQuestionDifficultyTable() {
                    $.getJSON(`facciaanalysis2.class.php?action=question_difficulty&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            const container = $('#questionDifficultyTable');
                            if (!response.data || response.data.length === 0) {
                                container.html('<p class="text-muted">No difficulty data available.</p>');
                                return;
                            }

                            let html = '<table class="table table-bordered table-striped table-sm">';
                            let theadRow = '<tr><th>#</th>';
                            if (ASSESSMENT_NUMBER === 'all') {
                                theadRow += '<th>Assessment</th>';
                            }
                            theadRow += '<th>Component</th><th>Question</th>';
                            theadRow += '<th>Avg Difficulty (%)</th><th>Std. Deviation</th></tr>';
                            html += '<thead>' + theadRow + '</thead><tbody>';

                            response.data.forEach((q, i) => {
                                const diff = parseFloat(q.avg_difficulty);
                                const cls = diff < TARGET_THRESHOLD ? 'table-danger' : '';
                                let componentDisplay = '';
                                if (q.component_type == "Assignment") {
                                    componentDisplay = `Assignment`;
                                } else {
                                    componentDisplay = q.component_type.charAt(0).toUpperCase() + q.component_type.slice(1);
                                }

                                let row = `<tr class="${cls}"><td>${i + 1}</td>`;
                                if (ASSESSMENT_NUMBER === 'all') {
                                    row += `<td>${q.assessment_number}</td>`;
                                }
                                row += `<td>${componentDisplay}</td>`;
                                if (q.component_type == "Assignment") {
                                    row += `<td>${q.sequence_number}</td>`;
                                } else {
                                    row += `<td>${q.question_text}</td>`;
                                }
                                row += `<td>${diff}%</td><td>${parseFloat(q.std_dev).toFixed(2)}</td></tr>`;
                                html += row;
                            });

                            html += '</tbody></table>';
                            container.html(html);
                            displaySuggestions('questionDiffSuggestions', response.suggestions);
                        });
                }

                function loadZeroMarksAnalysis() {
                    const sectionCard = $('#zeroMarksSectionCard');
                    const kpiContainer = $('#zeroMarksKpiCards');
                    const chartsRow = $('#zeroMarksChartsRow');
                    const coContainer = $('#zeroMarksCoSummaryContainer');
                    const suggContainer = $('#zeroMarksSuggestions');

                    if (!sectionCard.length) return;

                    $.getJSON(`facciaanalysis2.class.php?action=zero_marks&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}&_=${Date.now()}`)
                        .done(function(response) {
                            if (!response || !response.question_level_available || !response.data || !response.data.questions || response.data.questions.length === 0) {
                                const msg = (response && response.message) ? response.message : 'Question-level marks unavailable.';
                                const emptyHtml = `
                                    <div class="col-12">
                                        <div class="alert alert-warning text-center my-3 p-3 shadow-sm rounded">
                                            <i class="bi bi-exclamation-circle me-1"></i> <strong>Question-level marks unavailable.</strong>
                                            <div class="small text-muted mt-1">${msg}</div>
                                        </div>
                                    </div>
                                `;
                                kpiContainer.html(emptyHtml);
                                chartsRow.hide();
                                coContainer.html('<p class="text-muted small fst-italic">Question-level marks unavailable.</p>');
                                return;
                            }

                            const data = response.data;
                            const isOverall = data.is_overall;
                            const summary = data.summary || {};
                            chartsRow.show();

                            // 1. Render Executive KPI Summary Cards
                            let kpiHtml = `
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-light text-center">
                                        <div class="text-muted small">Questions in Scope</div>
                                        <div class="fs-4 fw-bold text-dark">${summary.total_questions || data.questions.length}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">${summary.choice_questions_count ? summary.choice_questions_count + ' with choice options' : 'Compulsory questions'}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-light text-center">
                                        <div class="text-muted small">Questions with Zeros</div>
                                        <div class="fs-4 fw-bold ${(summary.questions_with_zeros > 0) ? 'text-danger' : 'text-success'}">${summary.questions_with_zeros || 0}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Not attempted or secured zero</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-light text-center">
                                        <div class="text-muted small">Students with &ge; 1 Zero</div>
                                        <div class="fs-4 fw-bold ${(summary.total_distinct_students_with_zeros > 0) ? 'text-danger' : 'text-success'}">${summary.total_distinct_students_with_zeros || 0}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Distinct across all questions</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-light text-center">
                                        <div class="text-muted small">COs Impacted</div>
                                        <div class="fs-4 fw-bold ${(summary.cos_with_zeros > 0) ? 'text-warning text-dark' : 'text-success'}">${summary.cos_with_zeros || 0} <span class="fs-6 fw-normal text-muted">/ ${summary.total_cos || (data.co_summary ? data.co_summary.length : 0)}</span></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Course Outcomes with zeros</div>
                                    </div>
                                </div>
                            `;
                            kpiContainer.html(kpiHtml);

                            // 2. Render Chart 1: Question Zero-Marks Distribution
                            const qCanvasId = 'zeroMarksQuestionChart';
                            if (chartInstances[qCanvasId]) {
                                chartInstances[qCanvasId].destroy();
                            }

                            const qLabels = data.questions.map(q => {
                                let prefix = isOverall ? `${q.assessment_label}: ` : '';
                                let label = `${prefix}${q.question_label}`;
                                return q.is_choice ? `${label}*` : label;
                            });
                            const qRecordedScores = data.questions.map(q => q.recorded_scores_count);
                            const qZeroScores = data.questions.map(q => q.zero_scores_count);
                            const qZeroColors = data.questions.map(q => q.is_choice ? 'rgba(255, 193, 7, 0.75)' : 'rgba(220, 53, 69, 0.75)');
                            const qZeroBorderColors = data.questions.map(q => q.is_choice ? 'rgb(255, 193, 7)' : 'rgb(220, 53, 69)');

                            const qCtx = document.getElementById(qCanvasId);
                            if (qCtx) {
                                chartInstances[qCanvasId] = new Chart(qCtx, {
                                    type: 'bar',
                                    data: {
                                        labels: qLabels,
                                        datasets: [
                                            {
                                                label: 'Students with Recorded Score',
                                                data: qRecordedScores,
                                                backgroundColor: 'rgba(54, 162, 235, 0.55)',
                                                borderColor: 'rgb(54, 162, 235)',
                                                borderWidth: 1
                                            },
                                            {
                                                label: 'Not Attempted or Secured Zero',
                                                data: qZeroScores,
                                                backgroundColor: qZeroColors,
                                                borderColor: qZeroBorderColors,
                                                borderWidth: 1
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                                labels: { boxWidth: 14 }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    afterLabel: function(context) {
                                                        const qIdx = context.dataIndex;
                                                        const qObj = data.questions[qIdx];
                                                        if (context.datasetIndex === 1) {
                                                            if (qObj.is_choice) {
                                                                return 'Choice applicability unknown (*)';
                                                            } else if (qObj.rate_percentage !== null) {
                                                                return `${qObj.rate_percentage}% recorded zeros among recorded scores`;
                                                            }
                                                        }
                                                        return '';
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: { precision: 0 }
                                            },
                                            x: {
                                                grid: { display: false }
                                            }
                                        }
                                    }
                                });
                            }

                            // 3. Render Chart 2: Course Outcome (CO) Impact
                            const coCanvasId = 'zeroMarksCoChart';
                            if (chartInstances[coCanvasId]) {
                                chartInstances[coCanvasId].destroy();
                            }

                            const coLabels = (data.co_summary || []).map(co => co.co_label);
                            const coZeroCounts = (data.co_summary || []).map(co => co.has_mapped_evidence ? (co.zero_students_count || 0) : 0);
                            const coColors = (data.co_summary || []).map(co => {
                                if (!co.has_mapped_evidence) return 'rgba(108, 117, 125, 0.3)';
                                return co.zero_students_count > 0 ? 'rgba(253, 126, 20, 0.75)' : 'rgba(25, 135, 84, 0.75)';
                            });
                            const coBorderColors = (data.co_summary || []).map(co => {
                                if (!co.has_mapped_evidence) return 'rgb(108, 117, 125)';
                                return co.zero_students_count > 0 ? 'rgb(253, 126, 20)' : 'rgb(25, 135, 84)';
                            });

                            const coCtx = document.getElementById(coCanvasId);
                            if (coCtx) {
                                chartInstances[coCanvasId] = new Chart(coCtx, {
                                    type: 'bar',
                                    data: {
                                        labels: coLabels,
                                        datasets: [{
                                            label: 'Students with Recorded Zero on Mapped Question(s)',
                                            data: coZeroCounts,
                                            backgroundColor: coColors,
                                            borderColor: coBorderColors,
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                display: false
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        const coIdx = context.dataIndex;
                                                        const coObj = data.co_summary[coIdx];
                                                        if (!coObj.has_mapped_evidence) {
                                                            return 'No mapped question evidence';
                                                        }
                                                        return `${coObj.zero_students_count} distinct student(s) with recorded zero`;
                                                    },
                                                    afterLabel: function(context) {
                                                        const coIdx = context.dataIndex;
                                                        const coObj = data.co_summary[coIdx];
                                                        if (coObj && coObj.limitation_note) {
                                                            return coObj.limitation_note;
                                                        }
                                                        return '';
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: { precision: 0 }
                                            },
                                            x: {
                                                grid: { display: false }
                                            }
                                        }
                                    }
                                });
                            }

                            // 4. Render Course Outcome (CO) Summary Matrix Table
                            let coHtml = '<table class="table table-bordered table-striped table-sm align-middle mb-0">';
                            coHtml += '<thead><tr class="table-light"><th>CO</th><th>Description</th><th>Mapped Questions (CIA)</th>';
                            coHtml += '<th class="text-center">Recorded zero on &ge; 1 question (CIA)</th>';
                            coHtml += '<th class="text-center">Zero total across mapped questions (CIA)</th>';
                            coHtml += '<th class="text-center">Semester End (SEE) Status</th>';
                            coHtml += '<th>Limitation / Note</th></tr></thead><tbody>';

                            if (!data.co_summary || data.co_summary.length === 0) {
                                coHtml += '<tr><td colspan="7" class="text-center text-muted py-3">No Course Outcomes defined for this course.</td></tr>';
                            } else {
                                data.co_summary.forEach(co => {
                                    coHtml += '<tr>';
                                    coHtml += `<td><strong>${co.co_label}</strong></td>`;
                                    coHtml += `<td><small class="text-muted">${co.co_description || '—'}</small></td>`;

                                    let seeBadge = (co.see_status === 'SEE Marks Submitted')
                                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">SEE Submitted</span>'
                                        : '<span class="badge bg-secondary-subtle text-secondary border">Pending SEE Marks</span>';

                                    if (!co.has_mapped_evidence) {
                                        coHtml += '<td class="text-muted small fst-italic">No mapped questions</td>';
                                        coHtml += '<td class="text-center text-muted small fst-italic">No mapped question evidence</td>';
                                        coHtml += '<td class="text-center text-muted small fst-italic">No mapped question evidence</td>';
                                        coHtml += `<td class="text-center">${seeBadge}</td>`;
                                        coHtml += `<td class="text-muted small fst-italic">${co.limitation_note || 'Not assessed in current CIA scope'}</td>`;
                                    } else {
                                        let qList = (co.mapped_questions && co.mapped_questions.length > 0)
                                            ? co.mapped_questions.map(q => `<span class="badge bg-light text-dark border me-1 mb-1">${q}</span>`).join('')
                                            : '<span class="text-muted small">—</span>';
                                        coHtml += `<td>${qList}</td>`;

                                        let countDisplay = '';
                                        if (co.zero_students_count === 0) {
                                            countDisplay = '<span class="badge bg-success-subtle text-success border border-success-subtle">0 students</span>';
                                        } else {
                                            countDisplay = `<span class="badge bg-danger">${co.zero_students_count} student${co.zero_students_count > 1 ? 's' : ''}</span>`;
                                        }
                                        coHtml += `<td class="text-center">${countDisplay}</td>`;

                                        let totalZeroDisplay = '';
                                        if (co.zero_total_count === 0) {
                                            totalZeroDisplay = '<span class="badge bg-success-subtle text-success border border-success-subtle">0 students</span>';
                                        } else {
                                            totalZeroDisplay = `<span class="badge bg-danger">${co.zero_total_count} student${co.zero_total_count > 1 ? 's' : ''}</span>`;
                                        }
                                        coHtml += `<td class="text-center">${totalZeroDisplay}</td>`;

                                        coHtml += `<td class="text-center">${seeBadge}</td>`;

                                        let notes = [];
                                        if (co.limitation_note) {
                                            notes.push(co.limitation_note);
                                        }
                                        notes.push('Recorded mark alone cannot distinguish attempt reason.');
                                        coHtml += `<td><small class="text-muted">${notes.join(' ')}</small></td>`;
                                    }
                                    coHtml += '</tr>';
                                });
                            }

                            coHtml += '</tbody></table>';
                            coContainer.html(coHtml);
                        })
                        .fail(function(jqxhr, textStatus, error) {
                            const errHtml = `
                                <div class="col-12">
                                    <div class="alert alert-danger text-center my-3 p-3">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Unable to load Zero Marks analysis at this moment. Please retry.
                                    </div>
                                </div>
                            `;
                            kpiContainer.html(errHtml);
                            chartsRow.hide();
                            coContainer.html(errHtml);
                        });
                }

                function loadAssessmentSnapshot() {
                    const canvasId = 'assessmentChart';
                    const widgetId = 'assessmentStatWidget';
                    const chartContainerId = 'assessmentChartContainer';
                    const suggestionsContainerId = 'assessmentChartSuggestions';

                    if (chartInstances[canvasId]) {
                        chartInstances[canvasId].destroy();
                    }

                    $.getJSON(`facciaanalysis2.class.php?action=assessment_performance&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}&_=${Date.now()}`)
                        .done(function(response) {
                            if (!response || !response.data || response.data.length === 0) {
                                $(`#${chartContainerId}`).hide();
                                const isLab = (response && (response.sub_type === 'lab' || response.sub_type === 'dti'));
                                if (isLab) {
                                    $(`#${widgetId}`).html(`
                                        <div class="alert alert-info text-center my-3 p-3 shadow-sm rounded">
                                            <h6 class="alert-heading fw-bold mb-2">&#9432; Lab Internal Exam Pending</h6>
                                            <p class="small mb-1">
                                                For Lab courses, the <strong>Assessment Performance Snapshot</strong> specifically evaluates the formal <strong>Internal Exam</strong> (15 marks).
                                            </p>
                                            <p class="small text-muted mb-0">
                                                Weekly Day-to-Day continuous evaluation marks are displayed in the <strong>Component-wise Performance</strong> section.
                                            </p>
                                        </div>
                                    `).show();
                                } else {
                                    $(`#${widgetId}`).hide();
                                }
                                const msg = (response && response.suggestions && response.suggestions.length > 0)
                                    ? response.suggestions[0]
                                    : 'No assessment performance data available for the selected scope.';
                                $(`#${suggestionsContainerId}`).html(msg).addClass('alert-secondary');
                                return;
                            }

                            const isLab = (response.sub_type === 'lab' || response.sub_type === 'dti');
                            // Single assessment mode applies whenever a specific assessment (e.g. CIA 1, CIA 2) is chosen,
                            // or if it's a lab subject, or if only 1 data item exists.
                            const isSingleAssessment = (String(ASSESSMENT_NUMBER).toLowerCase() !== 'all') || isLab || (response.data.length === 1);

                            if (isSingleAssessment) {
                                // Destroy any existing chart instance and hide canvas container
                                if (chartInstances[canvasId]) {
                                    chartInstances[canvasId].destroy();
                                    delete chartInstances[canvasId];
                                }
                                $(`#${chartContainerId}`).hide();
                                $(`#${canvasId}`).hide();

                                // Find data item corresponding to selected assessment number, or fallback to first
                                let item = response.data[0];
                                if (ASSESSMENT_NUMBER && String(ASSESSMENT_NUMBER).toLowerCase() !== 'all') {
                                    const match = response.data.find(d => String(d.assessment_number) === String(ASSESSMENT_NUMBER));
                                    if (match) item = match;
                                }

                                const pct = parseFloat(item.attainment_percentage);
                                const pctFormatted = (!isNaN(pct)) ? pct.toFixed(2) : '0.00';
                                const meetsTarget = pct >= TARGET_THRESHOLD;
                                const statusBadge = meetsTarget 
                                    ? `<span class="badge bg-success p-2">&check; Target Met (&ge; ${TARGET_THRESHOLD}%)</span>`
                                    : `<span class="badge bg-warning text-dark p-2">&#9888; Below Target (&lt; ${TARGET_THRESHOLD}%)</span>`;
                                const scopeTitle = item.assessment_label || (isLab ? 'Lab Assessment' : (ASSESSMENT_NUMBER ? `CIA - ${ASSESSMENT_NUMBER}` : 'Assessment Performance'));

                                const widgetHtml = `
                                    <div class="text-center py-2 px-1">
                                        <h6 class="text-secondary fw-semibold mb-1">${scopeTitle} Attainment</h6>
                                        <div style="position: relative; height: 180px; max-width: 320px; margin: 0 auto;">
                                            <canvas id="speedometerGaugeCanvas"></canvas>
                                        </div>
                                        <div class="mt-1">
                                            <div class="display-6 fw-bold ${meetsTarget ? 'text-success' : 'text-danger'} mb-1">
                                                ${pctFormatted}%
                                            </div>
                                            <div class="mb-2">${statusBadge}</div>
                                            <div class="d-flex justify-content-center gap-2 text-muted small">
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">
                                                    &#9632; 0% - ${TARGET_THRESHOLD - 1}% (Deficient)
                                                </span>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                                    &#9632; ${TARGET_THRESHOLD}% - 100% (Target Met)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                $(`#${widgetId}`).html(widgetHtml).show();

                                // Render Speedometer Dial Gauge on speedometerGaugeCanvas
                                const speedoCanvas = document.getElementById('speedometerGaugeCanvas');
                                if (speedoCanvas) {
                                    const gaugeNeedlePlugin = {
                                        id: 'gaugeNeedle',
                                        afterDatasetDraw(chart, args, pluginOptions) {
                                            const { ctx } = chart;
                                            const meta = chart.getDatasetMeta(0);
                                            if (!meta || !meta.data || !meta.data[0]) return;

                                            const arc = meta.data[0];
                                            const cx = arc.x;
                                            const cy = arc.y;
                                            const outerRadius = arc.outerRadius;
                                            const innerRadius = arc.innerRadius;

                                            const val = (pluginOptions && pluginOptions.value !== undefined) ? pluginOptions.value : 0;
                                            const targetVal = (pluginOptions && pluginOptions.targetThreshold !== undefined) ? pluginOptions.targetThreshold : 60;
                                            const clampedVal = Math.min(Math.max(val, 0), 100);

                                            // Angle math: 0% at -Math.PI (left), 100% at 0 (right)
                                            const angle = -Math.PI + (clampedVal / 100) * Math.PI;
                                            const threshAngle = -Math.PI + (targetVal / 100) * Math.PI;

                                            ctx.save();

                                            // 1. Draw Target Threshold dividing tick line
                                            ctx.save();
                                            ctx.translate(cx, cy);
                                            ctx.rotate(threshAngle);
                                            ctx.beginPath();
                                            ctx.moveTo(innerRadius - 4, 0);
                                            ctx.lineTo(outerRadius + 4, 0);
                                            ctx.strokeStyle = '#0f172a';
                                            ctx.lineWidth = 2.5;
                                            ctx.stroke();
                                            ctx.restore();

                                            // 2. Threshold label outside the arc
                                            const threshLabelRadius = outerRadius + 14;
                                            const tx = cx + threshLabelRadius * Math.cos(threshAngle);
                                            const ty = cy + threshLabelRadius * Math.sin(threshAngle);
                                            ctx.font = 'bold 11px system-ui, -apple-system, sans-serif';
                                            ctx.fillStyle = '#1e293b';
                                            ctx.textAlign = 'center';
                                            ctx.textBaseline = 'bottom';
                                            ctx.fillText(`${targetVal}% Target`, tx, ty);

                                            // 3. Base labels 0% and 100%
                                            ctx.font = '600 11px system-ui, -apple-system, sans-serif';
                                            ctx.fillStyle = '#64748b';
                                            ctx.textAlign = 'center';
                                            ctx.textBaseline = 'top';
                                            const midR = innerRadius + (outerRadius - innerRadius) / 2;
                                            ctx.fillText('0%', cx - midR, cy + 6);
                                            ctx.fillText('100%', cx + midR, cy + 6);

                                            // 4. Draw Needle
                                            ctx.save();
                                            ctx.translate(cx, cy);
                                            ctx.rotate(angle);

                                            // Needle pointer
                                            ctx.beginPath();
                                            ctx.moveTo(0, -4);
                                            ctx.lineTo(outerRadius - 6, 0);
                                            ctx.lineTo(0, 4);
                                            ctx.closePath();
                                            ctx.fillStyle = '#0f172a';
                                            ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
                                            ctx.shadowBlur = 4;
                                            ctx.shadowOffsetY = 2;
                                            ctx.fill();

                                            // Pivot circle
                                            ctx.beginPath();
                                            ctx.arc(0, 0, 8, 0, Math.PI * 2);
                                            ctx.fillStyle = '#0f172a';
                                            ctx.fill();
                                            ctx.beginPath();
                                            ctx.arc(0, 0, 3.5, 0, Math.PI * 2);
                                            ctx.fillStyle = '#ffffff';
                                            ctx.fill();

                                            ctx.restore();

                                            ctx.restore();
                                        }
                                    };

                                    if (chartInstances['speedometerGauge']) {
                                        chartInstances['speedometerGauge'].destroy();
                                    }

                                    chartInstances['speedometerGauge'] = new Chart(speedoCanvas, {
                                        type: 'doughnut',
                                        data: {
                                            labels: [`Below Target (0-${TARGET_THRESHOLD - 1}%)`, `Target Met (${TARGET_THRESHOLD}-100%)`],
                                            datasets: [{
                                                data: [TARGET_THRESHOLD, 100 - TARGET_THRESHOLD],
                                                backgroundColor: ['#ef4444', '#10b981'],
                                                hoverBackgroundColor: ['#dc2626', '#059669'],
                                                borderWidth: 2,
                                                borderColor: '#ffffff',
                                                spacing: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            rotation: -90,
                                            circumference: 180,
                                            cutout: '68%',
                                            layout: {
                                                padding: {
                                                    top: 20,
                                                    bottom: 10,
                                                    left: 10,
                                                    right: 10
                                                }
                                            },
                                            plugins: {
                                                datalabels: { display: false },
                                                legend: { display: false },
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(context) {
                                                            return ' ' + context.label;
                                                        }
                                                    }
                                                },
                                                gaugeNeedle: {
                                                    value: pct,
                                                    targetThreshold: TARGET_THRESHOLD
                                                }
                                            }
                                        },
                                        plugins: [gaugeNeedlePlugin]
                                    });
                                }

                            } else {
                                // Multi-assessment comparison mode (Overall CIA on Theory subjects)
                                if (chartInstances['speedometerGauge']) {
                                    chartInstances['speedometerGauge'].destroy();
                                    delete chartInstances['speedometerGauge'];
                                }
                                $(`#${widgetId}`).hide();
                                $(`#${chartContainerId}`).show();
                                $(`#${canvasId}`).show();

                                let labels = response.data.map(item => item.assessment_label);
                                let values = response.data.map(item => item.attainment_percentage);
                                let colors = generateDistinctColors(labels.length);

                                const datasets = [{
                                    label: 'Attainment (%)',
                                    data: values,
                                    backgroundColor: colors,
                                    borderColor: colors.map(c => c.replace('0.7', '1')),
                                    borderWidth: 1
                                }];

                                const config = {
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: datasets
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                display: true,
                                                position: 'top',
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        const yVal = (context.parsed.y !== null && context.parsed.y !== undefined)
                                                            ? parseFloat(context.parsed.y).toFixed(2) + '%'
                                                            : 'N/A';
                                                        return (context.dataset.label || '') + ': ' + yVal;
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                max: 100,
                                                ticks: {
                                                    callback: function(value) {
                                                        return value + '%';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                };

                                chartInstances[canvasId] = new Chart(document.getElementById(canvasId), config);
                            }

                            if (suggestionsContainerId && response.suggestions) {
                                displaySuggestions(suggestionsContainerId, response.suggestions);
                            }
                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error(`Error fetching assessment performance:`, textStatus, errorThrown);
                            $(`#${suggestionsContainerId}`).html(`Error loading assessment performance data.`).addClass('alert-danger');
                        });
                }

                // Function to load Comprehensive Outcome Attainment (Direct, Indirect, Overall)
                function loadComprehensiveAttainment() {
                    const coContainer = $('#comprehensiveCoTableContainer');
                    if (coContainer.length === 0) {
                        return; // Tab is not present when SEE marks are not submitted
                    }
                    const poContainer = $('#comprehensivePoTableContainer');
                    const bannerContainer = $('#comprehensiveWeightsBanner');
                    const badgeContainer = $('#seeSubmissionBadge');
                    const suggContainerId = 'comprehensiveSuggestions';

                    $.getJSON(`facciaanalysis2.class.php?action=comprehensive_attainment&sub_id=${SUB_ID}`)
                        .done(function(response) {
                            if (!response || !response.cos || response.cos.length === 0) {
                                coContainer.html('<p class="p-3 text-muted">No Course Outcomes (COs) configured or available for this subject.</p>');
                                if (response && response.suggestions) {
                                    displaySuggestions(suggContainerId, response.suggestions);
                                }
                                return;
                            }

                            const meta = response.meta || {};
                            const isSeeSubmitted = meta.is_see_submitted;
                            const wCia = Math.round((meta.w_cia || 0.3) * 100);
                            const wSee = Math.round((meta.w_see || 0.7) * 100);
                            const wDir = Math.round((meta.w_direct || 0.8) * 100);
                            const wInd = Math.round((meta.w_indirect || 0.2) * 100);

                            // Update SEE submission status badge
                            if (isSeeSubmitted) {
                                badgeContainer.removeClass('bg-secondary bg-warning text-dark').addClass('bg-success').html('<i class="bi bi-check-circle-fill me-1"></i>SEE Results Finalized');
                            } else {
                                badgeContainer.removeClass('bg-secondary bg-success').addClass('bg-warning text-dark').html('<i class="bi bi-hourglass-split me-1"></i>SEE Pending (CIA Only)');
                            }

                            // Weights banner
                            bannerContainer.html(`
                                <span class="badge bg-dark p-2"><i class="bi bi-shield-check me-1"></i>Regulation: ${meta.regulation || 'R23'}</span>
                                <span class="badge bg-primary p-2"><i class="bi bi-pie-chart me-1"></i>Direct: ${wCia}% CIA + ${wSee}% SEE</span>
                                <span class="badge bg-info text-dark p-2"><i class="bi bi-sliders me-1"></i>Overall: ${wDir}% Direct + ${wInd}% Feedback (Indirect)</span>
                                <span class="badge bg-secondary p-2"><i class="bi bi-bullseye me-1"></i>Benchmark Target: ${meta.target_threshold || 60}%</span>
                            `);

                            // 1. Build CO Attainment Table
                            let coHtml = `
                                <table class="table table-bordered table-hover align-middle mb-0 text-center" style="font-size: 0.88rem;">
                                    <thead class="table-dark">
                                        <tr>
                                            <th rowspan="2" class="align-middle" style="width: 7%;">CO</th>
                                            <th colspan="2" class="table-primary text-dark">Internal (CIA)</th>
                                            <th colspan="2" class="table-info text-dark">External (SEE)</th>
                                            <th rowspan="2" class="table-primary text-dark align-middle" style="width: 11%;">Direct Level<br><small>(${wCia}% CIA + ${wSee}% SEE)</small></th>
                                            <th colspan="2" class="table-warning text-dark">Student Feedback</th>
                                            <th rowspan="2" class="table-success text-dark align-middle fw-bold" style="width: 11%;">Overall Level<br><small>(${wDir}% Dir + ${wInd}% Ind)</small></th>
                                            <th rowspan="2" class="align-middle" style="width: 15%;">Attainment Status</th>
                                        </tr>
                                        <tr>
                                            <th class="table-light text-dark" style="width: 8%;">Cohort %</th>
                                            <th class="table-light text-dark" style="width: 7%;">Level</th>
                                            <th class="table-light text-dark" style="width: 8%;">Cohort %</th>
                                            <th class="table-light text-dark" style="width: 7%;">Level</th>
                                            <th class="table-light text-dark" style="width: 9%;">Avg Rating</th>
                                            <th class="table-light text-dark" style="width: 7%;">Level</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;

                            response.cos.forEach(co => {
                                const ciaPctStr = (co.cia_pct !== null) ? parseFloat(co.cia_pct).toFixed(2) + '%' : 'N/A';
                                const seePctStr = (co.see_pct !== null) ? parseFloat(co.see_pct).toFixed(2) + '%' : '<span class="text-muted fst-italic">Unavailable</span>';
                                const seeLvlStr = (co.see_level !== null) ? co.see_level : '<span class="text-muted fst-italic">Unavailable</span>';
                                const fbRatingStr = (co.indirect_avg_rating !== null) ? (parseFloat(co.indirect_avg_rating).toFixed(2) + ' / 5') : 'N/A';
                                const fbLvlStr = (co.indirect_level !== null) ? co.indirect_level : 'N/A';

                                coHtml += `
                                    <tr>
                                        <td class="fw-bold" title="${co.co_description || ''}">${co.co_label}</td>
                                        <td>${ciaPctStr}</td>
                                        <td class="fw-bold">${co.cia_level ?? 'N/A'}</td>
                                        <td>${seePctStr}</td>
                                        <td class="fw-bold">${seeLvlStr}</td>
                                        <td class="fw-bold bg-light">${co.direct_attainment === null ? 'N/A' : parseFloat(co.direct_attainment).toFixed(2)}${co.is_provisional ? ' (provisional)' : ''}</td>
                                        <td>${fbRatingStr}</td>
                                        <td class="fw-bold">${fbLvlStr}</td>
                                        <td class="fw-bold text-primary fs-6 bg-success bg-opacity-10">${co.overall_attainment === null ? 'N/A' : parseFloat(co.overall_attainment).toFixed(2)}</td>
                                        <td><span class="badge ${co.status_badge}">${co.status_text}</span></td>
                                    </tr>
                                `;
                            });

                            coHtml += '</tbody></table>';
                            coContainer.html(coHtml);

                            // 2. Render Chart.js Multi-bar Comparison Chart
                            const chartCanvas = document.getElementById('comprehensiveChart');
                            if (chartCanvas) {
                                if (chartInstances['comprehensiveChart']) {
                                    chartInstances['comprehensiveChart'].destroy();
                                }

                                const labels = response.cos.map(c => c.co_label);
                                const ciaLevels = response.cos.map(c => c.cia_level);
                                const seeLevels = response.cos.map(c => (c.see_level !== null ? c.see_level : 0));
                                const directLevels = response.cos.map(c => parseFloat(c.direct_attainment));
                                const indirectLevels = response.cos.map(c => (c.indirect_level !== null ? c.indirect_level : 0));
                                const overallLevels = response.cos.map(c => parseFloat(c.overall_attainment));

                                chartInstances['comprehensiveChart'] = new Chart(chartCanvas, {
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: [
                                            {
                                                label: 'CIA Level',
                                                data: ciaLevels,
                                                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                                borderColor: 'rgb(54, 162, 235)',
                                                borderWidth: 1
                                            },
                                            {
                                                label: 'SEE Level',
                                                data: seeLevels,
                                                backgroundColor: 'rgba(255, 159, 64, 0.7)',
                                                borderColor: 'rgb(255, 159, 64)',
                                                borderWidth: 1
                                            },
                                            {
                                                label: 'Direct Attainment',
                                                data: directLevels,
                                                backgroundColor: 'rgba(153, 102, 255, 0.7)',
                                                borderColor: 'rgb(153, 102, 255)',
                                                borderWidth: 1
                                            },
                                            {
                                                label: 'Indirect (Feedback)',
                                                data: indirectLevels,
                                                backgroundColor: 'rgba(255, 205, 86, 0.7)',
                                                borderColor: 'rgb(255, 205, 86)',
                                                borderWidth: 1
                                            },
                                            {
                                                label: 'Final Overall Level',
                                                data: overallLevels,
                                                backgroundColor: 'rgba(75, 192, 192, 0.85)',
                                                borderColor: 'rgb(75, 192, 192)',
                                                borderWidth: 1.5
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                                labels: { boxWidth: 14 }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        return context.dataset.label + ': Level ' + parseFloat(context.parsed.y).toFixed(2);
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                max: 3.2,
                                                ticks: {
                                                    stepSize: 0.5,
                                                    callback: function(v) { return 'Level ' + v; }
                                                }
                                            }
                                        }
                                    }
                                });
                            }

                            // 3. Build PO/PSO Table
                            if (response.pos && response.pos.length > 0) {
                                let poHtml = `
                                    <table class="table table-bordered table-striped table-hover align-middle mb-0 text-center" style="font-size: 0.88rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>PO / PSO</th>
                                                <th>Direct</th>
                                                <th>Indirect</th>
                                                <th class="table-success text-dark">Overall</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;
                                response.pos.forEach(p => {
                                    poHtml += `
                                        <tr>
                                            <td class="fw-bold" title="${p.po_description || ''}">${p.po_label}</td>
                                            <td>${parseFloat(p.direct_attainment).toFixed(2)}</td>
                                            <td>${parseFloat(p.indirect_attainment).toFixed(2)}</td>
                                            <td class="fw-bold text-success">${parseFloat(p.overall_attainment).toFixed(2)}</td>
                                        </tr>
                                    `;
                                });
                                poHtml += '</tbody></table>';
                                poContainer.html(poHtml);
                            } else {
                                poContainer.html('<p class="p-3 text-muted">No CO-PO mapping configured.</p>');
                            }

                            // Display suggestions
                            if (response.suggestions) {
                                displaySuggestions(suggContainerId, response.suggestions);
                            }
                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error('Error fetching comprehensive attainment:', textStatus, errorThrown);
                            coContainer.html('<p class="p-3 text-danger">Error loading comprehensive attainment data.</p>');
                        });
                }

                // Track loaded tabs to load data on-demand (lazy-load) and prevent connection floods
                const loadedTabs = {};

                function loadTabData(targetPaneId) {
                    if (loadedTabs[targetPaneId]) return;
                    loadedTabs[targetPaneId] = true;

                    if (targetPaneId === '#overview-tab-pane') {
                        loadAssessmentSnapshot();
                        loadChart('component_performance', 'componentChart', 'component_type', 'attainment_percentage', 'Component Performance', 'bar', true, 'componentChartSuggestions');
                    } else if (targetPaneId === '#outcome-tab-pane') {
                        loadChart('co_attainment', 'coChart', 'co_label', 'co_attainment_percentage', 'CO Attainment', 'bar', true, 'coChartSuggestions');
                        loadChart('po_attainment', 'poChart', 'po_label', 'po_attainment_percentage', 'PO Attainment', 'bar', true, 'poChartSuggestions');
                    } else if (targetPaneId === '#blooms-tab-pane') {
                        loadChart('blooms_performance', 'bloomsChart', 'blooms_label', 'attainment_percentage', "Bloom's Attainment", 'bar', true, 'bloomsChartSuggestions');
                    } else if (targetPaneId === '#copo-matrix-tab-pane') {
                        loadCoPoMatrix('coPoMatrixTableContainer', 'coPoMatrixSuggestions');
                    } else if (targetPaneId === '#comprehensive-tab-pane') {
                        loadComprehensiveAttainment();
                    } else if (targetPaneId === '#advanced-tab-pane') {
                        loadCOSpreadChart();
                        loadQuestionDifficultyTable();
                        if ($('#zeroMarksSectionCard').length) {
                            loadZeroMarksAnalysis();
                        }
                    }
                }

                // Load active tab when document is ready, and lazy-load remaining tabs on switch
                $(document).ready(function() {
                    if (SUB_ID && ASSESSMENT_NUMBER) {
                        // Immediately load the active initial tab (Overview)
                        loadTabData('#overview-tab-pane');

                        // Add listener to load tab data on-demand and redraw charts if tab becomes visible
                        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                            const targetPaneId = $(e.target).attr('data-bs-target');
                            if (targetPaneId) {
                                loadTabData(targetPaneId);
                                $(targetPaneId).find('canvas').each(function() {
                                    const canvasId = $(this).attr('id');
                                    if (canvasId && chartInstances[canvasId]) {
                                        chartInstances[canvasId].resize();
                                    }
                                });
                            }
                        });
                    } else {
                        // Optionally display a message if no subject/assessment selected
                        $('#analysisTabContent').html('<p class="text-center text-muted">Please select a subject and assessment to view the analysis.</p>');
                    }

                    // Enable assessment dropdown only when a subject is selected
                    $('#sub_id').on('change', function() {
                        if ($(this).val()) {
                            $('#assessment_number').prop('disabled', false).prop('required', true);
                            // Optionally trigger form submit here if you want auto-load on subject change
                            //$('#analysisSelectionForm').submit();
                        } else {
                            $('#assessment_number').prop('disabled', true).prop('required', false).val('');
                        }
                    });
                });
    </script>
    HTML;

    // Replace placeholders with PHP values
    return str_replace(
        ['__THRESHOLD__', '__SUB_ID__','__ASSESSMENT__'],
        [$thresholdJson, $subIdJson, $assessmentJson],
        $script
    );
}