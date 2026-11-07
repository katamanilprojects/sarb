# Faculty experience and requirements audit

**Projects:** `jntuacea` and `jntuaceastudents`  
**Reviewed:** 6 November 2026  
**Scope:** Existing faculty workflows, their student-facing counterparts, and the HOD/administrative handoffs needed to complete them. Includes the proposed zero-marks diagnostics in **CIA Analysis & Attainment**, as clarified by the project owner.

## 1. What this review establishes

The system already contains most of the pages needed for its documented faculty work. Its main deficiencies are inconsistent completion rules, report meaning, feature enforcement, and navigation context across older and newer workflows. Adding many new menu items would not address those deficiencies.

This is a source-based faculty workflow review, supplemented by read-only checks of the local database and inspection of both local login screens. It is **not a claim that a faculty participant completed every workflow**, and it is not an authenticated visual or mobile usability certification.

Evidence used:

- Current working files, including existing uncommitted changes; older copies of the projects were excluded.
- The [faculty manual](/Applications/MAMP/htdocs/classattendance.in/docs/user-guides/faculty-user-manual.md), [student manual](/Applications/MAMP/htdocs/classattendance.in/docs/user-guides/student-user-manual.md), workflow documentation, and the **Finalized Decisions** in [the earlier requirements discussion](/Applications/MAMP/htdocs/classattendance.in/answers_required_for_suggestions.md:72). The older analysis below those decisions is not assumed to describe today's code.
- Actual local feature configuration and relevant table definitions, read without modifying records. These describe this local installation, not every customer or production installation.
- PHP syntax checks on **361 application, core, service, and view files: no syntax errors**. A scan found no missing literal local PHP/PDF link targets in the root page controllers. Neither check establishes runtime correctness.
- A database preparation check reproduced the invalid query used by the overall theory CIA entry page. Small synthetic checks reproduced blank-to-zero conversion and the SEE aggregate-evidence classification described below.

No application code, marks, attendance, permissions, or feature settings were changed. No live submission, deletion, publication, password change, or feedback submission was performed.

**Status terminology:** “Present” means the route and relevant implementation were found. “OK at source review” means no specific gap was established in the inspected flow; it does not certify all data combinations or rendered layouts. “Partial” identifies a concrete limitation. “Not OK” identifies a reproducible or directly traceable defect. “Optional/off” is a configuration state, not a missing requirement. Recommended improvements are distinguished from existing requirements.

## 2. Actual feature availability and scope constraints

The local configuration enables the main faculty attendance, diary, correction-request, CIA marks, CIA metadata, CO/PO, OBE analysis, student-feedback, grouped-attendance, lab-rubrics, and Bloom-analysis modules. No explicit action overrides were present in `role_module_permissions` at the time of the read.

The following are globally disabled locally: **lesson planning, SEE marks, AI assistance, CQI action tracking, student attendance risk, credit promotion, results publication, student profiles, certificates, and certificate QR**. Course Master File has its own flag enabled but is effectively unavailable because it depends on lesson planning. Some other module flags are enabled but hidden for particular roles; visibility must be assessed for the actual user, not from the global flag alone. Source: [FeatureManager dependencies and role checks](/Applications/MAMP/htdocs/classattendance.in/jntuacea/services/FeatureManager.php:150).

Consequently:

- Missing SEE/profile/results/CQI links are not evidence of missing implementation.
- Retain the student timetable entry on Home, as previously decided.
- Do not add a direct HOD CIA menu item: retain class/context selection.
- Do not restore optional CQI links unconditionally.
- Do not treat a new faculty-ranking dashboard, messaging service, parent SMS system, leave-management system, or cumulative degree-progress application as an established requirement. The older suggestions do not establish approval for those additions.
- Preserve the compact menus. Repair labels, context, status, and permissions inside existing pages first.

## 3. Findings that deserve action

### F01 — Overall theory CIA entry contains a confirmed database error — high priority

**Faculty experience:** Other Options → CIA → select a theory assessment → **Add Overall** can fail before displaying the entry form.

The Bloom advisory query in [facaddciamarks.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddciamarks.php:105) filters `assessment_components` using `ac.sub_id` and `ac.assessment_number`. The live local table contains only `id`, `assessment_id`, `component_type`, and `sequence_number`. Preparing that exact query returned **Unknown column 'ac.sub_id' in 'where clause'**. The query executes while loading the form, before the visibility condition for the advisory badge.

**Needed:** Join through `internal_assessments` for subject and assessment context; keep an optional advisory from preventing marks entry. Verify the complete Add Overall journey after correction. This is an existing feature needing repair, not a missing page.

### F02 — Detailed CIA entry does not reliably distinguish zero from unentered marks — high priority

[facmarksentry.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facmarksentry.php:92) converts each CSV mark with `floatval()`. A blank, spaces, or text such as `AB` becomes `0`. Marks above the maximum are skipped in the CSV loop, followed by an “uploaded successfully” message. The per-cell writer does not make the whole import one transaction. The manual itself acknowledges the blank-to-zero behavior.

[facviewmarks.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facviewmarks.php:176) also casts absent question values to numbers for display/editing, making missing values look like zero. The initial detailed grid requires numeric entries for every question and offers no separate attempt/absence/choice status.

**Needed:** Preserve “not entered,” reject invalid values with row/question details, preview imports, and report accepted/rejected counts. Commit a validated import together. Do not silently turn an unchosen question or an absent student's paper into performance evidence. This is essential before interpreting zero counts.

### F03 — Detailed-to-final CIA submission lacks an explicit completeness check — high priority

[facciamarkscondensed.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facciamarkscondensed.php:52) loops over enrolled students, sums available marks, defaults missing choice-pair contributions to zero, and offers **Submit Final Marks**. Its hidden form posts these numeric totals to the overall-entry handler. That handler validates numeric ranges, but does not establish that all required detailed evidence exists.

The shared final CIA calculator's ability to distinguish a missing assessment from a real zero does not recover information already lost during this earlier consolidation.

**Needed:** A pre-submission summary inside the existing review page: expected students, complete students, incomplete students, missing required components, recorded absences if supported, and applicable choice coverage. Prevent accidental finalization of incomplete detail, while allowing a legitimate assessed zero. Keep direct-total entry available as an explicitly identified evidence mode.

### F04 — CIA correction controls do not enforce all the rules advertised to faculty — high priority

In [facviewmarks.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facviewmarks.php:23), final-submission state hides the ordinary Edit link, but the `save_marks` handler does not reject a finalized assessment. `edit=1` still controls the edit form. This page also lacks the module read/write guards used by the initial entry page. [addOrUpdateStudentMarks()](/Applications/MAMP/htdocs/classattendance.in/jntuacea/ciamarks.class.php:726) directly updates a score without those checks or a correction snapshot. The common request layer checks authentication, request tokens, and some resource scope; it does not supply this missing finalization/module check.

**Needed:** Enforce editable state and the module's edit permission on the server, validate every submitted score against its actual question, and use an authorized correction path with a reason and reconciliation of finalized totals. Hiding a button is insufficient. Otherwise faculty may see one value in detailed analysis and another in submitted CIA records.

### F05 — CIA navigation loses the selected subject — medium priority

[facciamarks.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facciamarks.php:19) restores selection only from a POST carrying its legacy token. Several child pages return using `facciamarks.php?sub_id=...`; the parent does not read that GET selection. Other save handlers redirect to the parent without a subject. A faculty member must select the same course again after work.

**Needed:** Preserve an authorized subject/assessment selection on Back and after Save; keep a consistent subject/class/year heading. This is a small navigation repair with daily value, not a reason for a new dashboard.

### F06 — A per-CO target is editable, but the reviewed CIA calculation uses a regulation-wide target — high priority for report meaning

[facaddcos.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddcos.php:276) exposes **Target Threshold Limit (%)** for each CO and stores it. The CIA analysis path obtains the threshold through [getTargetAttainmentThreshold()](/Applications/MAMP/htdocs/classattendance.in/jntuacea/coattainment.class.php:62), which reads the regulation's `co_target_percentage`; the reviewed CIA computation does not use each CO's saved `target_threshold_percent`.

**Needed:** Establish and display the intended precedence. If per-CO targets are supported, calculations must use them consistently; otherwise make that field informational or remove its implication that it changes this report. Do not independently change an institution's attainment policy. A faculty member must be able to tell which target actually generated a result.

### F07 — “Student-wise CO Distribution” is an average chart, not a distribution — medium priority

The card in [ciaanalysis2_common_chart.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/ciaanalysis2_common_chart.php:223) says it helps identify outliers and how many students underachieve. However, [loadCOSpreadChart()](/Applications/MAMP/htdocs/classattendance.in/jntuacea/ciaanalysis2_common.php:319) plots `avg_attainment`, one value per CO. [getCOVisualSummary()](/Applications/MAMP/htdocs/classattendance.in/jntuacea/coattainment.class.php:649) returns that average and a count, not student performance bands.

**Needed:** Either rename it accurately or replace it with actual counts in clearly defined bands. The proposed zero-marks section fits here particularly well. An average cannot distinguish “everyone moderately weak” from “some strong students and several students with zero.”

### F08 — Coverage and assessment scope need more consistent treatment — medium/high priority

The theory CIA CO calculation builds its population from available question-mark rows and skips rows without a student ID; it does not construct a complete expected student-question grid. Its response lacks the enrolled/missing coverage fields present in parts of the SEE path. Thus a general banner saying missing coverage is reported is broader than this CIA path actually delivers. Source: [CIA CO population and output](/Applications/MAMP/htdocs/classattendance.in/jntuacea/coattainment.class.php:177).

Separately, the `all` branch in [getQuestionDifficultyDiscrimination()](/Applications/MAMP/htdocs/classattendance.in/jntuacea/learninganalytics.class.php:808) rebuilds the question query without the earlier exclusion of `SEE`. When SEE question records exist, **Overall CIA** can include them in that table. The read-only check found no SEE question marks locally, so this second issue is conditional, not a claim of current local contamination.

**Needed:** Show expected, evaluated, incomplete, and unavailable counts per metric; use the same selected assessment scope everywhere. Do not interpret a missing record as a weak result. Distinguish “average marks %” from “% of students meeting target.”

### F09 — Aggregate SEE marks can be counted as measured CO evidence — repair before enabling this path

[AttainmentCalculator::external()](/Applications/MAMP/htdocs/classattendance.in/jntuacea/core/AttainmentCalculator.php:58) increments `measured_students` when aggregate marks are combined with question-paper CO weights. It assigns the same aggregate percentage to each applicable CO. A synthetic direct-total record with a QP mapping returned `measured_students=1`, `estimated_students=0`, and `QP_METADATA_DISTRIBUTION` despite having no question-level student evidence.

**Needed:** Keep QP-informed allocation classified as an estimate, distinguish its basis, and never use it to claim a student obtained zero on a particular question or demonstrated zero on a particular CO. The module is currently off locally; this is a pre-enablement correctness issue.

### F10 — Report downloads do not consistently preserve the report being viewed — medium priority

The analysis screen supports combined batch selections as comma-separated subject IDs. Its report buttons pass that entire value to download handlers that parse `sub_id` with `intval()`, retaining the first ID. The combined screen can therefore lead to a single-offering export. Sources: [analysis download buttons](/Applications/MAMP/htdocs/classattendance.in/jntuacea/ciaanalysis2_common_chart.php:290), [feedback export parser](/Applications/MAMP/htdocs/classattendance.in/jntuacea/download_feedback_enhanced.php:60), [OBE export parser](/Applications/MAMP/htdocs/classattendance.in/jntuacea/download_obe_analysis.php:61), [Bluebook parser](/Applications/MAMP/htdocs/classattendance.in/jntuacea/download_bluebook.php:45).

The OBE PDF route also explicitly requires submitted SEE marks. A CIA-only course can have on-screen CIA analytics without that analytics dossier download. Marks exports and Bluebook already exist; the gap is a scoped CIA-analysis output, not an absence of all reports.

**Needed:** Make exports reproduce the displayed scope or label them explicitly as separate batch/course files. Offer a CIA-only analysis export without requiring an unrelated optional SEE workflow. Keep the complete dossier's existing readiness rule if the institution requires it.

### F11 — Feature controls are not reflected consistently in nested links and help — medium priority

The principal menus and Other Options already use feature checks. However:

- [facshowattendance.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facshowattendance.php:125) always offers CIA and Bluebook-related actions within an attendance-gated page.
- The analysis footer always offers a feedback download, even though feedback is independently switchable.
- CIA status pages offer metadata/attachment links while those routes use a separately switchable metadata module.
- The faculty Home instructions describe SEE and other options regardless of whether they are enabled.
- F04 establishes a more serious write-enforcement gap in a nested CIA page.

**Needed:** Use the destination's effective module/action permission for each action, including export and edit permission. Hide unavailable optional actions or explain the dependency where that explanation helps. Maintain the same checks on the destination. Test configurations such as attendance-only, CIA without SEE, and read-only CIA.

### F12 — Survey availability is temporarily hardcoded and disagrees with faculty help — medium priority

[studentfeedback.php](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentfeedback.php:31) computes a window and then replaces the end date for all classes with **8 November 2026, 23:59:59**. [Faculty Home](/Applications/MAMP/htdocs/classattendance.in/jntuacea/fachome.php:159) says the survey activates for seven days. The actual form handlers validate enrollment and prior submission but do not enforce that dashboard date window; the inspected feedback service also contains no corresponding date check.

**Needed:** One configurable window rule, including explicit class overrides where needed, used by the dashboard, submission handler, and help text. Label expired surveys **Closed**, not **Completed**, unless the student actually completed them. This prevents faculty giving students an incorrect deadline and separates submission status from availability.

### F13 — Students can see CIA marks, but the route is difficult to discover — medium priority

Student CIA is **not missing**. It is in [studentsubjectatt.php](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentsubjectatt.php:284), after attendance, COs, and diary content. The subject list in [studentsubjects.php](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentsubjects.php:86) uses mouse-clickable table rows and hidden POST forms, without an ordinary visible link/button for opening the subject. The row itself has no keyboard interaction or focus semantics.

**Needed:** An explicit **View attendance & CIA** link/button and a CIA anchor/tab near the top of the subject page. An optional compact CIA status column on the existing subject list would reduce faculty queries. No separate student marks application or extra top-level menu is needed.

### F14 — Approved HOD access has two remaining navigation/access gaps

**Profiles, currently off:** The profile controller allows HOD and contains departmental read-only logic, but it first loads the common bootstrap. [RequestSecurity](/Applications/MAMP/htdocs/classattendance.in/jntuacea/core/RequestSecurity.php:112) rejects HOD from `admin...` routes unless exempted; `adminstudentprofiles.php` is not exempt for HOD. Consequently enabling the feature is not sufficient to make that approved route usable. Source: [profile controller](/Applications/MAMP/htdocs/classattendance.in/jntuacea/adminstudentprofiles.php:17). Correct the shared-route role rule while retaining departmental scope and sensitive-document restrictions.

**Timetable:** Class-row timetable access no longer depends on enrollment, which satisfies part of the earlier decision. However [hodmenu.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/hodmenu.php:9) has no direct management entry, and [hodtimetable.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/hodtimetable.php:6) only redirects to Classes. The approved direct HOD timetable entry is therefore not fully implemented. A class-selection entry within the existing Classes menu can preserve the compact menu design.

### F15 — Lesson-plan review is coupled to an independently disabled CQI module — check before enabling lesson planning alone

[reviewqueue.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/reviewqueue.php:7) requires CQI access for the entire page, even though it contains course-delivery submissions whose per-item permission is lesson planning. Faculty can submit course completion when lesson planning is enabled, while a lesson-plan-only configuration cannot use this queue through its normal guard.

**Needed:** Allow each enabled workflow to reach its own review queue or explicitly declare the necessary dependency. Keep CQI off until requested. A faculty submission must have an accessible receiving workflow.

### F16 — Help and labels need targeted correction — low/medium priority

Both inspected login screens display the same **Student Academic Record Book / Login** identity, without clearly saying Staff or Student. The staff page's username/password controls use placeholders rather than persistent labels. A small **Staff sign-in / Student sign-in** distinction, visible labels, and the correct reset contact would help users; a self-service password-reset system is not established as a requirement.

[Faculty Home](/Applications/MAMP/htdocs/classattendance.in/jntuacea/fachome.php:122) describes grouped CIA import as copying marks, but [facgroupedcia.php](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facgroupedcia.php:45) exposes copying selected metadata/components and attachments. Describe exactly what is copied. The Home page also links its dated CIA PDF rather than surfacing the broader current manual. Do not assume the PDF is wrong solely from its name; verify its contents before replacing it.

These changes reduce training mistakes without adding modules.

## 4. Feature-by-feature faculty assessment

The numbers below match the current manual's 55 faculty features. UI comments assess the controls and structure in source; authenticated rendering, mobile dimensions, keyboard traversal of complete forms, and save/reload behavior still need a test account and representative data. “No additional gap established” deliberately means no invented requirement.

| # | Existing feature and navigation | Content/data assessment | UI/UX assessment and what is missing | Verdict / evidence |
|---|---|---|---|---|
| 1 | Faculty login at the staff portal | Login, errors and account handling exist. | Entry screen loads; distinguish staff from student and add persistent field labels/reset guidance. | Partial; F16; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/index.php:118). |
| 2 | Change Password in the main menu | Existing/current/new/confirm flow and mismatch messages exist. | Directly reachable. No new recovery subsystem justified; visible labels would improve the form. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facchgpwd.php). |
| 3 | Logout in the main menu | Session logout route exists. | Clear placement. No additional missing page established. | OK at source review; [menu](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facmenu.php). |
| 4 | My Weekly Timetable on Home | Faculty selection is forced to the signed-in faculty. | Appropriate contextual entry; no verified reason to duplicate it in the menu. Render with a real timetable before certifying layout. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/faculty_weekly_timetable.php:58). |
| 5 | Mark Attendance → subject/date → periods, diary, students | Assignment/timing checks and a transaction covering diary, attendance and audit exist. | Multi-period entry, bulk selection and confirmation exist. A concise saved-session receipt and leaving-page warning are useful improvements, not established missing modules. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddattendance.php), [writer](/Applications/MAMP/htdocs/classattendance.in/jntuacea/faculty.class.php:182). |
| 6 | Other Options → Update Single Student Attendance | Seven-day limit, present/absent correction and reason are implemented. | A deliberate multi-step selection protects context. Preserve selected context after correction for efficient repeated work. | Present/UX improvement; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facupdatestudentattendance.php:148). |
| 7 | Exceptional attendance | A separate workflow exists for missing student/session attendance. | Appropriate distinction from changing an existing mark. Explain that distinction beside both links. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facexceptionalattendance.php). |
| 8 | Submit New Deletion Request | Existing request-to-HOD workflow; module/write guards exist. | Keep reason, subject/date/period visible. No automatic deletion or new approval tier needed. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facadddelattrequest.php). |
| 9 | View Submitted Requests | Request tracking page exists. | This requirement is not missing. Pending menu badges remain a deferred preference, not an approved necessity. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facshowdelattrequests.php). |
| 10 | Add Diary Only | Separate diary entry without attendance exists. | Existing grouping explains purpose; avoid implying that diary-only means students attended. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facadddairy.php). |
| 11 | Show Attendance → Overall | Subject and optional date-range selection exist. | Existing attendance table satisfies viewing. A low-attendance filter could improve this table; a separate dashboard is not required by the evidence. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facshowattendance.php). |
| 12 | Show Attendance → Detailed Attendance & e-Bluebook | Detailed view and download route exist. | Match download availability to its OBE/export permission; attendance-only customers otherwise see an action they cannot complete. | Partial; F11; [rendering](/Applications/MAMP/htdocs/classattendance.in/jntuacea/moduleshowfacattendance.php:188). |
| 13 | Show Attendance → Show Diary | Subject/date diary retrieval exists. | Clear action in the subject record; hide it consistently if diary viewing is unavailable. | Present with toggle consistency gap; F11. |
| 14 | Show Attendance → CIA record and export | CIA display and export exist, using shared calculation paths in relevant final-ledger flows. | The parent is attendance-gated; CIA users also have the assessment hub. Make cross-module actions obey their own permissions. | Partial; [module](/Applications/MAMP/htdocs/classattendance.in/jntuacea/modulecheckcia.php); F11. |
| 15 | Other Options → COs → import curriculum COs | Import and “already up-to-date/no master COs” outcomes exist. | Useful prerequisite support; show master/offering provenance consistently. No missing CO-import page. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddcos.php:64). |
| 16 | Add COs manually | Description, Bloom level and target fields exist. | Editable target implies an effect not reflected in the reviewed CIA analysis. | Partial; F06. |
| 17 | Edit COs | Editing is on the CO page, not a missing separate page. | Explain which reports change and use one target-precedence rule. | Partial; F06; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddcos.php:87). |
| 18 | CO-PO/PSO Articulation Matrix | Mapping weights and subject context exist. | Appropriate table workflow. Report meaning still depends on valid underlying CO evidence. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facarticulationmatrix.php). |
| 19 | Add lesson-plan lecture | Topic, unit, lecture, CO, Bloom, pedagogy, reference and hours fields exist. | Hidden locally because lesson planning is off; not a missing page. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddlessonplan.php:83). |
| 20 | Import lesson-plan CSV | Template download and import workflow exist. | Contextual CO examples in the template are useful. End-to-end import not exercised. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddlessonplan.php:34). |
| 21 | Edit/delete planned lecture | Existing page/service support these operations with write checks. | Validate returned/reviewed-course states before enabling; no separate page required. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddlessonplan.php:116). |
| 22 | Clear lesson plan | Existing bulk operation, warning and service path. | Keep it deliberate and secondary; not an everyday prominent action. No new feature required. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddlessonplan.php:134). |
| 23 | CIA hub → select course/assessment | Type-specific status/actions are present. | Preserve subject on return. Existing “Added/Not Added” is less useful than complete/partial counts. | Partial; F03/F05; [hub](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facciamarks.php). |
| 24 | CIA → components → question metadata and CO mapping | Templates, questions, maxima and CO/Bloom mapping are implemented. | Keep question/CO setup distinct from marks and respect metadata visibility. Custom-paper rules need representative testing; do not promise universal templates. | Present; [components](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facciacomp.php), [questions](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facciacompques.php). |
| 25 | Question-wise marks entry | Numeric grid exists; zero/missing/attempt states are not distinct. | Required numeric cells make incomplete or optional-question cases unclear. Add explicit state semantics before diagnostics. | Not OK for those cases; F02. |
| 26 | Question-wise CSV import | Template/import exist. Blank coercion and silent skips undermine the success message. | Missing preview and row-level error report matter more than a new upload screen. | Not OK; F02. |
| 27 | Correct detailed marks/metadata | Correction pages exist; final-state/write checks are inconsistent. | A disabled Edit button must agree with direct access and submission behavior. | Not OK; F04. |
| 28 | Review/submit condensed CIA | Available totals and final-submit form exist. | Missing explicit completeness review can finalize missing detail as numeric zero. | Not OK; F03. |
| 29 | Add Overall theory CIA | Direct draft/final entry is implemented but the form-load query is invalid locally. | Repair the current entry page first. | Not OK; F01. |
| 30 | Laboratory totals and rubrics | Separate day-to-day/test entry and rubric assistant; service validates and writes together. | Existing draft/final controls are appropriate. Verify configured criteria/maxima for each customer before enabling rubrics there. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddcialabmarks.php), [service](/Applications/MAMP/htdocs/classattendance.in/jntuacea/services/LabAssessmentService.php). |
| 31 | Date-wise/bulk laboratory marks | Bulk page and detailed day-to-day components exist. | Uses the legacy question-mark writer; apply the same missing/zero and import validation rules as CIA. | Partial; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facbulkd2dmarks.php:60); F02/F04 principles. |
| 32 | Design Thinking and Innovation CIA | Dedicated entry/review pages exist and fetch configured maxima. | No missing page established. Validate actual customer course rules; do not infer correctness from the name or defaults. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facadddticiamarks.php). |
| 33 | PG CIA | Separate component form and shared PG service support draft/final/correction states, correction reasons and snapshots. | More explicit lifecycle than the older question grid; preserve it. Representative PG regulation tests still required. | Present; [form](/Applications/MAMP/htdocs/classattendance.in/jntuacea/pg_cia_form.php), [service](/Applications/MAMP/htdocs/classattendance.in/jntuacea/core/PgCiaService.php:62). |
| 34 | Project CIA | Supervisor/PRC columns and configured total exist; current page splits the total equally. | Adequacy depends on the institution's approved project scheme; no evidence justifies inventing extra review rounds. | Present; policy-specific verification needed; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddprojectciamarks.php:21). |
| 35 | CIA supporting files | Upload/list/delete/download exist; metadata module controls them. | Gate the incoming link too; report the actual limit/file restriction when rejected. | Present/partial; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facciaattachments.php:37); F11. |
| 36 | Grouped attendance | Source/section selection and common-session marking exist. | Useful existing faculty task; a new grouped-attendance page is unnecessary. Test representative shared-course memberships before claiming all cases pass. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facaddgroupedattendance.php). |
| 37 | Grouped CIA copy | Multi-step source/target/item selection exists for metadata and attachments. | Home describes copying marks too; correct that expectation. | Partial content; F16; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facgroupedcia.php). |
| 38 | SEE paper setup | Protected blueprint revision with reason and retained-score handling exists. | Currently hidden correctly. Verify choices and reconsolidation before enabling. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facseecompques.php:50). |
| 39 | SEE question marks | Service preserves explicit zero and clears blank scores to missing records. | Better than CIA's blank coercion, but absence/attempt reason still is not recorded per question. | Optional/off; [service](/Applications/MAMP/htdocs/classattendance.in/jntuacea/services/QuestionMarksService.php:10). |
| 40 | Direct SEE totals | Direct entry route and replacement/evidence services exist. | Aggregate evidence cannot support measured question/CO zero diagnostics. | Optional/off with F09; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facseedirectmarks.php). |
| 41 | Sync published results to SEE | Existing explicit notification and replacement-reason workflow. | Requires the faculty member to enter a numeric notification ID. A scoped selector showing exam title/date is the missing usability control. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facseemarks.php:171). |
| 42 | Import external attainment evidence | Preview/commit and replacement confirmation exist. | Keep clear distinction between official publication and imported attainment evidence. No new import page needed. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/attainmentimport.php:19). |
| 43 | CIA Analysis & Attainment | Overview, CO/PO, Bloom, matrix and Other Insights exist. | Correct targets, distribution label, missing coverage and scope. Add zero diagnostics here. | Partial; F06–F09. |
| 44 | Analysis/accreditation exports | Download routes exist. Complete dossier is SEE-dependent; combined selection is not preserved by integer parsers. | Missing matching-scope/CIA-only output. Clearly show export readiness. | Partial; F10. |
| 45 | Subject feedback report | Year/subject filters, response metrics, distributions, comments and export controls exist. | A separate survey report is already provided; zero examination marks should not be inserted into its rating distribution. Match availability/help to real survey rules. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facviewfeedback.php), [component](/Applications/MAMP/htdocs/classattendance.in/jntuacea/modulefeedback.php); F12. |
| 46 | Faculty feedback summary | Existing subject/faculty view selector and academic-year filtering. | This is student evaluation summarized for faculty; consider a clearer label than “Self-Appraisal” if users expect a faculty-authored appraisal form. This label suggestion is optional. | Present; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facviewfeedback.php:122). |
| 47 | Diary/lesson-plan reconciliation | Mapping, planned/conducted counts and coverage display exist. | The progress view is not missing; it is in an optional module currently off. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/faclessonplanreconciliation.php:180). |
| 48 | Course-completion submission | Draft/final submission and review status exist. | Ensure the HOD can receive the submission with the same enabled feature combination. | Optional/off, partial workflow; F15. |
| 49 | Propose improvement action | CQI proposal form exists in reconciliation. | Keep optional. Do not force it as a prerequisite for ordinary attendance/CIA. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/faclessonplanreconciliation.php:433). |
| 50 | Revise/complete improvement action | Queue has revised/completed/closed states and evidence fields. | Use when enabled; no claim of a missing follow-up system. Check independent-module access in F15. | Optional/off; [queue](/Applications/MAMP/htdocs/classattendance.in/jntuacea/reviewqueue.php:23). |
| 51 | Action Taken Report | Existing action-card PDF link. | Report should reflect the saved review stage; generated PDF layout was not inspected here. | Optional/off; [queue link](/Applications/MAMP/htdocs/classattendance.in/jntuacea/reviewqueue.php:25). |
| 52 | Optional AI drafts | Opt-in/source review, queue and human-review implementation exist. | Provider and worker operation not tested. AI must not supply missing zero reasons or infer which question was attempted. | Optional/off; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/aiassistance.php). |
| 53 | Bluebook/Course Master File | Both export services exist; Master File depends on lesson planning. | Respect different purposes and effective availability. Do not insert new analytics into the fixed operational Bluebook by default. | Present/conditional; [Bluebook](/Applications/MAMP/htdocs/classattendance.in/jntuacea/download_bluebook.php), [Master File](/Applications/MAMP/htdocs/classattendance.in/jntuacea/download_coursemasterfile.php); F10/F11. |
| 54 | Knowledge Base/manual | Home links to the knowledge base and a CIA-specific PDF; broader manual exists in docs. | Surface current, feature-relevant help. Avoid teaching disabled features as if available. | Partial content; F16. |
| 55 | Correct subject display details | `facupdsubject.php` exists; the manual explicitly says it is not in standard Other Options. | Do not call this an accidentally orphaned page or expose it by default. If customer policy permits it, supply an authorized contextual route and clear code-change consequences. | Restricted/conditional by documented intent; [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/facupdsubject.php). |

## 5. Student portal assessment

| Existing feature | Navigation/content/UI/UX verdict | Missing or required correction, and why |
|---|---|---|
| Login/password/logout | Present; both login screens inspected. Student identity and navigation are separate in code. | Staff/student portal distinction and persistent labels; F16. No evidence that another login page is needed. |
| Home and enrolled classes | Class cards, identity, current schedule and subject entry exist. | Keep primary actions obvious. Do not add unrelated KPIs simply because Home can hold them. [Home](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studenthome.php). |
| Weekly timetable | Home entry and full timetable page exist. | Keep the previously agreed Home placement. This is not a missing menu item. [Home link](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studenthome.php:256). |
| Subject register and attendance | Subject drill-down, period records and attendance summary are implemented. | Explicit keyboard-accessible “View attendance & CIA” action; F13. Verify mobile table behavior with a signed-in account. |
| Diary and CO viewing | Present in the subject page with independent feature visibility checks. | Hidden CO content can be intentional: student CO/PO visibility is off in the local setting. Do not report that as absent implementation. [subject page](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentsubjectatt.php:233). |
| Internal marks | Component evidence and final CIA/pending reason are present. | Discovery and placement, rather than a missing marks page; F13. Preserve zero versus pending status. |
| CO feedback/CES/faculty feedback | Dashboard leads to integrated CES and per-faculty forms. CES includes CO ratings; separate CO capture is not simply missing because the dashboard has two main columns. | One deadline rule and accurate Closed/Completed states; F12. Explain when CO setup blocks availability. |
| Feedback completion and anonymity | Completion badges and duplicate checks exist. CES offers anonymity choice; faculty feedback handling is separate. | Explain each form's actual identity behavior. Do not promise absolute anonymity for all records/exports without a dedicated audit. [CES](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/student_ces_feedback.php:89), [dashboard](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentfeedbacksubjects.php). |
| Published results | Dedicated page with publication grouping, grade rows and GPA handling exists. | Optional/off locally. A cumulative degree tracker is a separate possible requirement, not a proven missing part of the existing results page. [results](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentresults.php). |
| Profile/documents | Profile links directly to Document Vault. | Optional/off locally. No need for a duplicate Documents menu item merely to make the feature count look complete. [profile](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentprofile.php:72). |
| Certificate applications/status | Request page, status listing and issued-document links exist. | Optional/off; no missing request-tracking page established. Generated certificate layouts were not tested. [page](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studentcertificates.php). |
| Attendance risk | Service and Home card exist. | Optional/off. If enabled later, validate displayed thresholds against actual policy; do not describe an informational risk category as a new official decision. [card](/Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/studenthome.php:183). |

## 6. Faculty handoffs to HOD and administration

| Requirement already discussed | Current evidence and verdict |
|---|---|
| HOD published-results access | `hodresults.php` exists with pass-percentage, grade-card and backlog views, and a guarded Academic Records link. Optional/off; not missing. [page](/Applications/MAMP/htdocs/classattendance.in/jntuacea/hodresults.php:180). |
| HOD class CIA ledger | Linked from Academic Records and retains class selection. The old “orphaned” finding is obsolete. [link](/Applications/MAMP/htdocs/classattendance.in/jntuacea/hodshowattendance.php:107). |
| Rename combined HOD Attendance area | Now **Academic Records** in the menu and hub. Implemented; do not propose an additional assessment menu as an already agreed requirement. [menu](/Applications/MAMP/htdocs/classattendance.in/jntuacea/hodmenu.php:19). |
| HOD timetable independent of enrollment | Timetable forms are outside the conditional student-mapping button in the class list. Implemented in that respect; direct-management navigation remains partial, F14. [class list](/Applications/MAMP/htdocs/classattendance.in/jntuacea/hodviewclasses.php:99). |
| HOD departmental profile access when enabled | Controller implementation exists, but common role routing blocks it; F14. Do not enable profiles to fix this. |
| Academic Section enrollment responsibility | Shared enrollment/upload routes and Academic Section menu entries are present. The previous claim that only Admin has the entry points is outdated. [menu](/Applications/MAMP/htdocs/classattendance.in/jntuacea/academicsectionmenu.php:25). Full admission writes were not exercised. |
| Admin/Academic Section gazette links | Both attendance pages now link to the gazette. Implemented navigation; not a missing report button. [Admin](/Applications/MAMP/htdocs/classattendance.in/jntuacea/adminshowallclsattendance.php:168), [Academic Section](/Applications/MAMP/htdocs/classattendance.in/jntuacea/academicsectionshowallclsattendance.php:165). |
| Shared campus timetable overview | Menu entries exist for both roles. Implemented navigation; no new duplicate page needed. [Admin menu](/Applications/MAMP/htdocs/classattendance.in/jntuacea/adminmenu.php:56), [Academic Section menu](/Applications/MAMP/htdocs/classattendance.in/jntuacea/academicsectionmenu.php:80). |
| Faculty resets | Admin retains the link; Academic Section's menu now exposes student reset, not faculty reset. Verify every direct handler as part of a separate permission regression test; menu changes alone are not proof of complete enforcement. |
| Course review/CQI | Guarded Home links now exist. Their disappearance in this installation is intentional configuration. Resolve independent course-review access before enabling lesson planning alone; F15. |

## 7. The proposed zero-marks feature

### Is it correct?

**Yes—as assessment diagnostics within CIA Analysis & Attainment.** Faculty can use it to identify questions needing review and students who may need support. It becomes misleading if it claims why a zero occurred without evidence, treats every optional question as compulsory, or equates one zero question with failure of an entire CO.

The current live `student_marks` table has only `id`, `stu_id`, `question_id`, and `marks_obtained`. It has no attempt-status or zero-reason column. The application already stores many numeric zeros, but their presence alone does not establish their cause or academic meaning. Existing records must not be retrospectively labeled “did not attempt” or “wrong answer.”

### Required distinctions

| Situation | Correct presentation | Count as a demonstrated zero? |
|---|---|---|
| Evaluated answer awarded 0, with attempt explicitly recorded | **Attempted — zero awarded** | Yes, for applicable questions. “Wrong” is too narrow: no credit could have several evaluation reasons. |
| Compulsory/selected question explicitly recorded as unanswered and awarded 0 | **Not attempted — zero awarded** | Yes, under the institution's assessment rules. Keep the reason distinct. |
| Existing numeric zero with no reliable status | **Recorded zero — reason not recorded** | Show as a recorded-zero count, with limitations. Do not infer an attempt or academic failure. |
| Alternative question not selected | **Not selected (choice)** | No. Exclude it from the performance denominator. |
| Student explicitly absent for the assessment | **Absent** | Show separately. Whether official attainment includes absences is a policy decision; do not silently change that policy. |
| Required score not entered/evaluated | **Pending mark** | No. Show incomplete coverage. |
| No legacy score and applicability is unknown | **No mark recorded — applicability unknown** | No. A missing row alone cannot distinguish pending from an unselected alternative. |
| No question mapping/evidence for a CO | **Not assessed / evidence unavailable** | No. It is not a zero. |
| Aggregate/direct marks or grade-derived estimate only | **Question/CO diagnostic unavailable from this evidence** | An exact aggregate zero may be reported as an aggregate zero; it does not prove individual question/CO zeros. |

The attempt/status model can be a companion record rather than necessarily a column in this table. The essential requirement is explicit, auditable semantics shared by entry, import, reporting, and export. Choice selection is also evidence: choosing the higher stored mark is not proof of which alternative was attempted, especially when both contain zero.

### What to show at each level

| Level | Recommended measure | Correct denominator and interpretation |
|---|---|---|
| Question/subquestion | Number of distinct students with recorded zero; positive scores; known unattempted; known absences; pending; unselected choices | For verified applicable/evaluated questions, zero % = applicable zero-award students ÷ applicable evaluated students. For legacy ambiguous data, label the weaker metric “recorded zeros among recorded marks” and disclose uncertain applicability; do not call it a failure rate. |
| CO within one assessment | **Any applicable question with zero** and **Total assessed CO score is zero** as separate columns | Count distinct students per CO. The second measure requires complete relevant evidence and a positive applicable maximum. One zero mapped question is not enough. Show incomplete/no-evidence counts beside both. |
| Assessment/component | Distinct students with any relevant zero; students with total zero; incomplete records | Do not add question-level student counts: the same student can appear in several questions. Distinguish student counts from zero-score cells. |
| Course across CIA 1/CIA 2 | Side-by-side assessment summaries and recurring support needs | Preserve individual assessment results; never hide a zero in one assessment simply because another has a higher score. Apply existing rules separately to official weighted totals. |
| Combined batches | Summary plus per-batch detail, only for compatible papers/mappings | Confirm question identity, maxima, COs and assessment scope. Include offering/batch context; do not combine solely because labels both say “Q1.” |
| Student drill-down | Own-course student list with question, mark/max, CO, known status and completeness | Assigned faculty/HOD scope. Provide a link to the existing authorized correction flow. It is an academic support list, not a label that the student is incapable. |
| HOD/department | Course/assessment counts with evidence coverage and drill-down | Reuse the existing scoped analysis route. Do not rank teachers using raw zero counts across different papers/cohorts. |
| PO/PSO | Link back to contributing CO diagnostics if useful | Do not invent “students got zero in PO1”: POs are mapped aggregate outcomes, not individual marked questions in this workflow. |

For multi-CO questions, use the same approved marks-allocation rule as the attainment calculation. A student may legitimately occur under more than one mapped CO, so totals across CO rows are not a unique-student total. Define “any zero” and “all assessed evidence zero” independently of rounding; use stored marks, not a rounded chart label that turns a small positive mark into zero.

### Where it fits in the existing screen

1. **Overview:** A compact evidence strip: enrolled / complete / incomplete / students with recorded zero. Make the recorded-zero item open the detailed section. No extra main-menu item.
2. **Other Insights:** Rename to **Question & Student Diagnostics**, or keep the tab name and insert **Zero-score breakdown** here. Place the question table beside/below the existing Question Difficulty Overview. Replace the misleading CO distribution chart with a real distribution.
3. **Outcome Analysis (CO/PO):** Add CO summary columns for any-zero and fully-assessed-total-zero, plus coverage. A link opens the same diagnostics filtered by CO; avoid two independent calculations.
4. **PDF/CSV:** Include the same summary and definitions in the CIA/OBE analysis output. Offer an authorized student-detail export separately from the shareable summary. Carry subject, assessment, batch, data cutoff, policy/evidence basis, and coverage into the export.
5. **CQI:** Only when already enabled, allow a faculty member to use a verified finding as evidence for an existing improvement action. The diagnostic must remain usable when CQI and AI are off.

Do not put this into the student satisfaction/feedback rating report, the student-feedback submission forms, or the operational Bluebook by default. Those have different purposes. The existing OBE analytics PDF's learning-analytics section is the natural report location; a CIA-only export is needed for installations that do not use SEE.

### Example presentation — illustrative numbers, not project findings

For a **compulsory** question with reliable status records:

| Question | CO | Max | Evaluated | Awarded zero | Positive | Absent | Pending |
|---|---|---:|---:|---:|---:|---:|---:|
| Q2(a) | CO2 | 5 | 54 | 9 (16.7%) | 45 | 4 | 2 |

Display: **“9 of 54 evaluated students were awarded zero on Q2(a). Four students were absent and two marks are pending.”**

If reasons were recorded, expand the nine into “5 attempted — zero awarded; 4 not attempted — zero awarded.” Otherwise display **“Reason not recorded for these 9 zeros.”** Never manufacture that split.

For a choice question, additionally show Not Selected and unresolved applicability; do not use the compulsory-question example's denominator unchanged.

### Safe implementation order within the present requirements

**First:** Repair F02–F04 and define applicability/absence rules. Keep historical data unchanged; flag uncertain legacy records rather than bulk-converting zeros.

**Then:** Provide a limited, factual **recorded-zero inspection** view using existing records. Clearly distinguish compulsory questions from optional questions whose selection is unknown. No automatic causal text, remedial decisions, or false precision about attempt rates.

**Next:** Add explicit attempt/absence/choice states to entry and CSV templates, validate them, and make the richer report available only where those states are reliable. If desired, a Superadmin-controlled diagnostic subfeature can default off; it should depend on CIA/OBE reporting, not on SEE, AI, or CQI. Hide only the diagnostic feature, not existing assessment data.

**Finally:** Add CO-level rollups and exports from the same tested calculation. Faculty should be able to reconcile a report count back to the authorized student records.

Acceptance examples: genuine zero preserved; blank remains pending; absent separated; optional unselected question excluded; both options zero without selection remains ambiguous; one zero plus one positive within a CO does not become CO-total-zero; missing mapped evidence remains incomplete; a multi-CO question does not duplicate unique-student totals; direct/grade evidence cannot create question diagnostics; combined-batch exports match scope; read-only users cannot correct marks; disabling diagnostics closes direct access too.

## 8. What is actually missing, and what to do first

**Repair existing work first:** overall theory entry (F01), zero/missing handling and finalization (F02–F04), and target/report meaning (F06–F09). These affect the faculty member's ability to trust and finish an assessment.

**Add small missing parts to existing pages:** an assessment completeness panel; a real question/CO diagnostic section; matching-scope CIA analysis exports; persistent subject context; visible student attendance/CIA links; and a single configurable survey-window control with matching enforcement. These directly complete or clarify current requirements.

**Before optional modules are enabled:** verify HOD profile routing, lesson-plan review without CQI, aggregate SEE evidence labels, and independent feature/action combinations. Preserve the present enabled/disabled choices.

**Do not claim unsupported completeness:** authenticated desktop/mobile layout, real browser back/reload behavior, large-class editing usability, generated PDF pagination, all regulation configurations, and concurrent submissions remain unverified in this audit. These are focused acceptance checks, not assertions that the corresponding features are broken.

The faculty zero-marks suggestion is worth implementing after its evidence rules are made explicit. The strongest version is a transparent diagnostic inside the existing CIA Analysis & Attainment report, backed by trustworthy entry states and clear counts, rather than another standalone report with inferred explanations.
