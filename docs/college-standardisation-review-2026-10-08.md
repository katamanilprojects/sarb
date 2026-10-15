# JNTUACEA: standardisation review and development sequence

Date: 8 October 2026. Scope: `jntuacea`, `jntuaceastudents`, the supplied schema, the 7 October accreditation migration, and the configured local MariaDB database. `ignore_old` was excluded. This is an inspection and proposed plan; application code and database records were not changed.

## Recommendation

Keep the two user-facing portals and standardise their shared academic rules, permissions, data contracts, and reporting. Build a college-specific operating system around the college's actual approval chains and forms. An incremental refactor is appropriate; a wholesale rewrite or a generic ERP expansion is not justified by this inspection.

The first milestone should be **a trusted academic core**: consistent attendance and marks, enforceable record-level access, verifiable approvals, and reproducible reports. Add optional AI narration and recommendations after that core is demonstrated against college-approved examples.

## 1. What was verified

- Inspected first-party PHP entry points, services, documentation, recent Git history and current uncommitted changes, shared schema, and migration. Large modules were reviewed selectively; this was not a line-by-line audit of every file or third-party package.
- PHP syntax checks passed for all 271 first-party PHP files: 232 staff-side and 39 student-side. Approximately 85,090 PHP lines in total, excluding dependencies and uploads. Syntax checks do not establish functional correctness.
- Connected directly to the configured **local** MariaDB 10.4.27 database, without invoking application authentication or application write services. Used read-only transactions and aggregate/metadata queries; no personal records were included in this report.
- The schema dump contains 72 tables and 93 foreign keys. The local database contains 77 tables and 95 foreign keys. The five additional tables are exactly the migration's rubric and CQI tables. Existing table column names match the dump; this does not prove every default, index, or constraint is identical.
- All 12 migration module keys and the three new setting keys are present. There are 27 registered feature modules, one seeded default rubric template, and three default criteria. There is no migration history table. Presence supports that the migration effects exist; replay and rollback were not tested.
- Exact local counts: 3,841 users; 161 faculty records; 3,659 distinct student usernames represented by 10,543 class-enrollment rows; 271 classes; 1,785 subject offerings; 2,165,933 attendance rows. Student enrollment rows are historical class memberships, not 10,543 distinct people.
- No duplicate attendance keys, duplicate student-subject mappings, or duplicate username/class enrollment groups were found. Three diary groups share the same subject/date/hour across faculty; verify whether these represent legitimate co-teaching before changing them.
- 168 offerings lack a curriculum link, and 20 classes lack a batch link. These are across all history; determine current-semester and migration relevance before treating them as defects.
- Password aggregates: 3,830 bcrypt hashes and 11 legacy MD5 hashes. A migration path exists; close out the remaining legacy accounts as part of identity standardisation.
- Staff has Git history plus substantial existing uncommitted changes. The student folder has no `.git` directory and is not under a parent Git repository. Preserve and baseline both applications together before refactoring.

The local database may be a restored or development copy. These results do not establish production deployment state, production usage, or backup reliability. No browser-based role acceptance tests, restore exercise, dependency vulnerability assessment, or migration replay were performed.

## 2. Existing capability and readiness

| Area | Existing implementation | Local evidence / remaining validation |
|---|---|---|
| Academic setup | Programs, regulations, departments, specialisations, batches, classes, curriculum and settings | Substantial foundation; confirm authoritative program/regulation scope and version history |
| Teaching operations | Faculty mappings, student mappings, timetable, grouped attendance, diary, permissions and deletion requests | Large operational dataset; prioritise consistency, correction safeguards and access scope |
| CIA | Theory, lab, PG, project, temporary marks, question/CO mappings and analysis | Existing data; confirm rule variations and completion/finalisation semantics |
| Feedback | CO, course-end, faculty and questionnaire feedback | Existing responses; define confidentiality, response coverage and who sees individual versus aggregate data |
| OBE | Course CO/PO calculations, Bloom analysis, batch governance and reports | Mixed maturity; simulated macro attainment and department/program scoping need correction |
| Lesson plans and delivery audits | Planning, diary reconciliation, course-completion audit and PDF generation | Module globally disabled; no lesson plans or completion audits in this database |
| CQI / ATR | Action proposals, HOD review fields and report integration | No actions recorded; review/closure cycle is incomplete in inspected PHP routes |
| Results and SEE | Result CSV import, publishing, student grade cards and SEE sync | Publishing and SEE modules globally disabled; no exam-results rows |
| Student administration | Profile, document vault, custody records, certificate requests and verification | Profile/certificate modules globally disabled; corresponding local tables empty |
| Rubrics | Default template and per-student evaluation path | Seed present; no subject rubric assignments or evaluations |

Empty tables, especially for disabled modules, are evidence of unvalidated adoption in this copy, not proof that implementation is absent or unused elsewhere.

## 3. Findings to address first

### A. Report truth and academic policy

**A1. Simulated attainment can appear as measured attainment.** `getBatchMacroAttainment()` calculates each PO's attained score as `target * 0.82`, then propagates that value to PEO/mission summaries. It is called by the batch dashboard and longitudinal analytics. Replace it with approved evidence-based calculations, or clearly mark it as a simulation and exclude it from official outputs. Missing evidence must yield “not available,” with coverage metadata, rather than a synthetic achievement.

Evidence: [BatchOBEService.php:710](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/BatchOBEService.php:710).

**A2. A course file can imply approval when no audit exists.** The DAAC certificate renderer defaults missing faculty sign-off to `SUBMITTED` and HOD status to `APPROVED`, supplies a statement of verified 100% coverage, and prints an unconditional conformity finding. Require actual approval records, reviewer identity, timestamps and approved evidence. Until then, render a draft or pending page without certification language.

Evidence: [CourseMasterFilePDFService.php:639](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/CourseMasterFilePDFService.php:639).

**A3. Attendance conclusions can disagree between portals.** Student risk calculations hardcode 75%/65%, count approved permissions as attended, and use a legacy joining-date fallback. The gazette loads configurable thresholds, also checks per-subject requirements, counts only `P` as attended and uses a different joining-date fallback. Establish one attendance calculation contract for permissions, joining dates, denominators, zero data, precision, subject applicability, and date cutoffs. Distinguish advisory risk from official eligibility explicitly.

Evidence: [StudentAttendanceService.php:131](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuaceastudents/services/StudentAttendanceService.php:131), [academicsection.class.php:386](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/academicsection.class.php:386).

**A4. Policy scope is weaker than the college model requires.** Settings are uniquely identified by regulation text and key, with fallback to R23. R20 settings span two regulation IDs; R21 has only the three newly added keys locally. Consequently, missing settings can silently inherit a different regulation. Explicitly approve which defaults may be inherited. Academic decisions should resolve program, regulation ID, course type, cohort and effective period, and record the policy version used. Preserve the college's real distinctions without building a general-purpose rules product.

Evidence: [SettingsService.php:200](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/SettingsService.php:200), local settings metadata.

**A5. Batch/program analysis needs department-specific context.** `computePOPSOArticulationMatrix()` derives a department from a program join using `LIMIT 1`; programs span specialisations/departments. It can load subjects across a batch while selecting outcomes for one inferred department. Accept explicit department/specialisation scope and test shared courses, sections and grouped subjects. Separate measured PO/PSO outcomes from PEO evidence; the college must approve which alumni/employer or other longitudinal evidence supports PEO assessment.

Evidence: [BatchOBEService.php:796](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/BatchOBEService.php:796).

### B. Permissions and workflow integrity

**B1. Feature toggles are not complete RBAC.** FeatureManager supports global switches and faculty/student/HOD visibility, but unknown modules are allowed by default and enabled modules grant admin/academic-section access without separate capability configuration. It does not itself establish ownership of a subject, class, request or document. Keep module availability separate from action permission and record scope. Check both in the service that performs the operation, not only in menus or header includes.

Concrete paths needing attention include reconciliation accepting a posted subject ID without a visible faculty-mapping check, attendance deletion processing by request ID without a department predicate, and staff document downloads without a document-specific scope check. Some routes already check ownership correctly; standardise that pattern across all routes and exports.

Evidence: [FeatureManager.php:274](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/FeatureManager.php:274), [faclessonplanreconciliation.php:29](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/faclessonplanreconciliation.php:29), [hod.class.php:1193](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/hod.class.php:1193), [viewdocument.php](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/viewdocument.php).

**B2. Correction approval is not one atomic operation.** Attendance request approval updates status before deleting diary and attendance rows, without one surrounding transaction or a pending-state predicate. The method can report success independently of downstream deletion. Add scoped authorization, CSRF validation at the controller, allowed transitions, concurrency protection, a single transaction and retained before/after evidence. The inspected deletion POST handler and reconciliation POST handler lack CSRF-token validation.

Evidence: [hod.class.php:1193](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/hod.class.php:1193), [hodviewdelrequests.php:14](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/hodviewdelrequests.php:14).

**B3. Student login discards its verification error.** After Turnstile validation, `$err` is reset to an empty string before credential processing. Failed verification therefore does not prevent that processing. Preserve the verification result and unify session security, rate limiting and password-reset handling across both portals. Staff throttling is session-based; use a shared persistent control for reliable throttling. Replace predictable reset passwords with temporary credentials or reset tokens and forced change.

Evidence: [student index.php:48](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuaceastudents/index.php:48).

**B4. Some new feature switches do not control their named workflow.** `MOD_ATTENDANCE_DELETE_REQUESTS` is registered but has no PHP reference; the existing flow uses `MOD_ATT_REQUESTS`. The DAAC variance and diary-lag setting keys are seeded but have no PHP consumers in the inspected code. Reconcile each module key with its menu, route, mutation guard, report and dependent features. Do not equate a registered switch with a complete feature.

### C. New-module and maintenance gaps

**C1. Rubric upsert and validation are incomplete.** Evaluation inserts use `ON DUPLICATE KEY UPDATE`, but the evaluation table has no unique key for mark/criterion. Repeated submissions can accumulate duplicate breakdowns. Establish stable criterion identity, validate each score and total server-side, authorise the selected subject/student and write the assessment plus breakdown atomically. Honour the rubric module's write permission as well as the CIA module's permission.

Evidence: [facaddcialabmarks.php:88](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/facaddcialabmarks.php:88), migration and live indexes.

**C2. CQI is currently a proposal log, not a completed improvement cycle.** Faculty proposals and status display exist, but the inspected PHP has no mutation of HOD review status or completion/effectiveness capture. The table has index names beginning `fk_` but no actual foreign keys. Add a scoped review queue, revision path, action owner, due date, execution evidence, follow-up measurement and closure. Validate CO ownership and derive attainment figures from trusted calculations rather than posted fields. A proposed action is not an action taken.

**C3. Results need an explicit authority and attempt model.** The result importer defaults missing credits to 3, grade conversion is hardcoded, and SEE sync sets maximum marks to 70 and picks the latest imported matching result across notifications. Approve rules for regular/supplementary attempts, revaluation, withheld/absent results, approved publication, corrected results and course-specific maxima. Use staging, validation and controlled publication. Documentation's grade mapping differs from the service mapping; resolve it against actual college regulations before release.

Evidence: [ExamResultsService.php:277](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/ExamResultsService.php:277), [ExamResultsService.php:322](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/ExamResultsService.php:322).

**C4. Timetable conflict checks are commented out in the management route.** The insert service does not replace those checks. Confirm required exceptions for groups/co-teaching and enforce classroom, faculty and student-group overlap rules with effective dates.

Evidence: [hodmanage_timetable.php:47](/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/hodmanage_timetable.php:47).

**C5. Operational standards differ across portals.** Credentials, authentication, FeatureManager, profile and results services have separate implementations. Some differences are appropriate read/write boundaries; common rules need one versioned implementation. Staff creates separate connections per object and does not set `utf8mb4` in its connection constructor; student code shares a connection and sets the charset. Shared document rows resolve files by trying two application-local upload folders, contradicting fully independent deployment. Certificate numbering uses `COUNT + 1`, which races under concurrent requests. Audit calls also mix faculty-record IDs and user IDs, requiring a stable actor convention. Composer permits PHP 7.4 although the code uses PHP 8 features; no first-party automated test suite, CI configuration or Composer lockfile was found in the inspected source.

## 4. Target structure

Preserve the staff and student interfaces. Give them a common, versioned academic core containing:

1. **Identity and policy checks:** authenticated actor, assigned roles/capabilities, department/class/subject scope, temporary delegation and audit actor ID.
2. **Academic records:** distinguish the person from class enrollment; curriculum master from subject offering; teaching session from student attendance; exam attempt from a published result; proposed action from approved/completed action.
3. **College policy:** approved and versioned attendance, assessment, grading, attainment, publication and correction rules, with explicit effective dates.
4. **Workflow services:** validate scope and state, persist one atomic operation, retain evidence, publish status and produce an audit event.
5. **Reporting services:** one metric definition consumed by screens, spreadsheets, PDFs and later AI. Store official report snapshots with source cutoff, policy version, filters and reviewer status.
6. **Private document storage:** stable storage identifiers and authenticated downloads available to both portals without sibling-folder assumptions.

Start by extracting shared services or distributing a versioned package in both deployments. A separate network API is optional when deployment needs justify it. A modular PHP application is sufficient; a new frontend or microservice estate is not a prerequisite.

## 5. Development sequence and completion criteria

| Step | Work | Completion criterion |
|---|---|---|
| 1. Establish a baseline | Preserve current changes; version both portals; record dependencies, schema and module state; add numbered migrations and a release checklist | A fresh isolated copy and a backup restore reproduce the approved baseline |
| 2. Map actual college work | Walk through one representative semester with faculty, HOD, Academic Section and Principal; collect forms, spreadsheets, rules, approval owners and exceptions | A college-approved workflow/permission/report register, including which paper steps can be retired |
| 3. Fix trust defects | Address synthetic attainment, unearned certification, verification reset, deletion integrity, missing scope and CSRF checks | Focused regression tests cover each defect; missing evidence never implies approval or attainment |
| 4. Unify academic calculations | Attendance first, then CIA/SEE/grading and CO/PO; define missing-data and rounding rules; version policies | Student, faculty, HOD, admin, PDF and spreadsheet outputs agree on the same approved examples |
| 5. Standardise daily work | Build role-specific pending-work lists, import/pre-fill from trusted masters, draft/final states, controlled corrections and visible status | One department completes the selected workflow with no duplicate transcription; time spent is measured |
| 6. Complete approvals and reports | HOD delivery/CQI review, results publication, certificate processing and report snapshots; pilot disabled modules individually | Requester can track progress; reviewer can act; official output has traceable inputs and approval |
| 7. Expand proven workflows | Pilot another department and applicable UG/PG/grouped-course variations; migrate missing links with review | Cross-department acceptance, operational monitoring and recovery exercise pass |
| 8. Pilot optional AI | Introduce reviewed narratives over approved reports and policies | Evaluation passes, users can inspect evidence, and the application remains fully usable with AI disabled |

Avoid committing to a calendar estimate before workflow owners and accepted rules are identified. Implement one complete vertical workflow at a time instead of changing every module simultaneously.

### First implementation batch

1. Baseline both portals and introduce migration tracking without modifying historical academic data.
2. Stop synthetic OBE and default-approved certification from appearing as official evidence.
3. Fix the login verification reset; centralise authentication/CSRF helpers.
4. Add service-level faculty/subject and HOD/department checks to attendance, corrections, marks, reconciliation and downloads.
5. Make attendance corrections atomic, state-checked and auditable.
6. Create the common attendance calculation and migrate both portals and the gazette to it.
7. Add a small acceptance dataset approved by the college: permission days, late joining, no records, threshold boundaries, electives/groups, co-teaching, corrections and rollback failures.
8. Deliver a faculty “today and pending” view and HOD exception queue using those services.

Do not silently repair historical mappings or diary rows. Produce a proposed correction list and preserve the source history.

## 6. What each stakeholder should receive

| Stakeholder | Operational view | Reports / analysis |
|---|---|---|
| Faculty | Today's classes; unmarked sessions; draft marks; diary/plan gaps; review feedback; actions due | Attendance register; CIA and question/CO/Bloom analysis; provisional/final attainment; course file and ATR with real evidence |
| HOD | Department review queue; teaching/marks completion; correction requests; timetable exceptions; CQI follow-up | Coverage/compliance, course comparison with cohort context, attainment gaps, action effectiveness and department summaries |
| Principal/Admin | College exceptions and bottlenecks, scoped delegation, overdue approvals | College/department trends, completeness and data freshness, attendance/academic eligibility, intervention outcomes and accountability |
| Academic Section | Master-data checks; publication batches; certificate/custody queue; approved exceptions | Validated gazettes, result/revaluation registers, credit/promotion evidence, certificate/custody registers and backlog |
| Student | Own attendance and marks with explanation; published results; document/certificate status; correction requests where permitted | Personal trends, recovery guidance and decision reasons; no other student's private records |
| Superadmin | Capability assignments, module/dependency state, approved policy versions and release health | Permission changes, policy changes and administrative audit history |

For each report define owner, audience, data scope, numerator/denominator, source tables, cutoff, missing-data treatment, rounding, policy version, approval/publication and export format. Display completeness beside performance: missing SEE results must not look like zero achievement, and unmarked classes must not be presented as student absence.

## 7. College-specific workflow discovery

Code cannot reveal every manual step. For each workflow record: trigger, person responsible, inputs, existing re-entry, validation, exception, approver, final output, deadline, status visible to the requester and evidence retained.

Start with teaching-to-course-file and shortage-to-resolution, because these reuse substantial existing data. Then examine results-to-SEE, feedback-to-CQI, and certificate/custody requests. Only add further areas such as mentoring, leave, substitutes, committee minutes, exam duty or no-dues routing after the college confirms the actual process and workload. Do not add a broad ERP module catalogue merely because other colleges use one.

## 8. Optional AI after standardisation

### Good first uses

| Task | Trusted input | AI role | Human responsibility |
|---|---|---|---|
| Faculty analysis narrative | Validated CIA/CO/Bloom metrics with coverage | Explain patterns and draft discussion | Verify interpretation and approve |
| HOD/Principal brief | Scoped department/college snapshots | Summarise exceptions and trends | Prioritise action |
| ATR drafting | Gap, approved action, execution evidence and follow-up result | Draft an evidence-linked action-taken report | Confirm what actually happened |
| CQI planning | Verified gaps, assessment design and faculty context | Suggest feasible interventions | Select owner, action and target |
| Teaching-plan assistance | Approved syllabus, timetable and actual diary | Draft plan or propose diary/topic matches | Review and save mappings |
| Feedback synthesis | Appropriately aggregated/anonymised feedback | Group themes and draft responses | Protect confidentiality and approve conclusions |
| Policy questions | Approved regulation documents and circular versions | Answer with clause/page citations | Route uncertain or exceptional decisions to the authority |

Compute attendance percentages, eligibility, marks, SGPA, attainment and promotion rules deterministically. AI can explain those outputs. It should not invent missing figures, certify compliance, alter academic records, approve requests or issue documents.

### Nightly local processing

Recommended sequence:

`consistent backup → isolated restored analysis database → validated metric snapshots → selected AI jobs → output validation → reviewer drafts → controlled publication`

- Treat the analysis copy as read-only after restoration. Give the inference worker curated, minimal report payloads instead of unrestricted SQL access or a raw database dump in its prompt.
- Keep generated results in a separate output store or application job/artifact tables, not in the source backup. Re-authorise access when a user opens or publishes an artifact; shared caches must preserve department/student scope.
- Record backup/snapshot ID and freshness, report scope, data-completeness flags, policy version, model identifier/digest, prompt version, generation time, evidence references and reviewer decision.
- Schedule only opted-in jobs; deduplicate by snapshot, scope and job version. Add retries, timeouts, cancellation, workload limits, failure visibility and an overnight completion target. Do not regenerate every report for every user each night when inputs are unchanged.
- Separate a proposed intervention from an ATR claiming completed work. An ATR requires execution evidence and an authorised human confirmation.
- Use structured output plus numeric/evidence checks and a review screen. Schema-valid output alone does not prove factual accuracy. Test prompt injection in uploaded documents and ensure it cannot change permissions or execute actions.
- Measure unsupported claims, altered numbers, omissions, citation correctness, reviewer acceptance/edit time, department-scope leakage, throughput and hardware memory use. Pilot on a representative, faculty-reviewed evaluation set before enabling regular publication.

### Gemma and Ollama

Gemma 4 and Ollama are viable candidates for a local pilot. Choose the size and quantisation only after checking the allotted CPU/GPU, RAM/VRAM, storage, document sizes and nightly workload. Benchmark representative college reports, including English/Telugu needs if applicable. Model download size is not the total memory needed at inference time; context and concurrency add memory.

Google documents Gemma 4 variants and quantisation tradeoffs in its [model overview](https://ai.google.dev/gemma/docs/core). Ollama lists supported local variants in its [Gemma 4 library](https://ollama.com/library/gemma4), supports [JSON-schema structured outputs](https://docs.ollama.com/capabilities/structured-outputs), and documents disabling cloud features in its [FAQ](https://docs.ollama.com/faq). For a local-only college deployment, configure cloud features off and restrict the inference service to the approved host/network.

Start with prompts, college templates and retrieval from approved documents. Fine-tuning can be evaluated later if a measured problem persists and there is a reviewed training set. No model installation, scheduled job or AI access to college data was performed during this inspection.

## 9. Inputs needed for implementation planning

1. Confirm which database/code release is authoritative for production and whether this local copy is current.
2. Provide the college's approved regulations, circulars and current report/form samples, including signatures and approval chains.
3. Nominate a faculty, HOD, Academic Section and Principal/Admin representative and identify the three workflows consuming the most faculty time.
4. Confirm operational rules for corrections, locking, publication, feedback confidentiality, co-teaching and delegation.
5. For the later AI pilot, provide the proposed machine specifications and required nightly outputs.

Success should be measured as less duplicate entry, fewer reconciliation errors, shorter approval time, reproducible reports, visible request status and a complete audit trail. AI narrative quality is a later measure built on that foundation.
