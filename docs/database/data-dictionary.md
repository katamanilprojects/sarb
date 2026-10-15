# Database Data Dictionary

This document details enumeration values, status flag semantics, criteria operators, and standardized code representations used across the database.

---

## 1. System Roles (`users.role`)

The `role` column in the `users` table defines access levels and interface routing:

| Role String | Description | Default Landing Page |
|---|---|---|
| `superadmin` | Institutional superuser; full control over academic years, programs, regulations, specs, and classes. | `superadminhome.php` |
| `admin` | Administrative officer; student enrollment, faculty profiles, subject mappings, password management. | `adminhome.php` |
| `academic_section` | Academic section affairs; infrastructure (buildings, halls), regulations, syllabus, institutional attendance audits. | `academicsectionhome.php` |
| `hod` | Head of Department; timetable management, departmental subjects, faculty assignment, student mapping, attendance deletion review. | `hodhome.php` |
| `faculty` | Teaching faculty; daily attendance, diary entries, internal assessment marks entry, deletion requests. | `fachome.php` |
| `student` | Student portal actor (read-only views of attendance and CIA marks). | `studenthome.php` |

---

## 2. Status Flags (`status`)

Most master tables include a `status` integer flag used for soft-deactivation:

| Table | Value | Meaning |
|---|---|---|
| `users` | `1` | **Active**: Allowed to log in. |
| `users` | `0` | **Inactive / Suspended**: Login rejected by `User::authenticate()`. |
| `faculties` | `1` | **Active**: Eligible for subject allotment and timetable scheduling. |
| `faculties` | `0` | **Relieved / Inactive**: Excluded from active faculty dropdowns. |
| `students` | `1` | **Active**: Enrolled and eligible for attendance and marks entry. |
| `students` | `0` | **Detained / Left**: Excluded from daily attendance rolls. |
| `departments` | `1` | **Active Department**. |
| `departments` | `0` | **Archived / Inactive**. |
| `specialization`| `1` | **Active Specialization Branch**. |
| `specialization`| `0` | **Discontinued Branch**. |
| `classes` | `1` | **Current Academic Class**: Displayed in active menus and schedules. |
| `classes` | `0` | **Completed / Archived Cohort**. |
| `subjects` | `1` | **Active Course**: Taught in the current term. |
| `subjects` | `0` | **Archived Course**. |
| `curriculum_subjects` | `1` | **Active Master Subject**: Visible in curriculum syllabus catalogs and class allotment picker. |
| `curriculum_subjects` | `0` | **Archived / Inactive Master Subject**. |
| `student_batches` | `1` | **Active Cohort**: Students currently enrolled in this degree batch. |
| `student_batches` | `0` | **Graduated / Archived Cohort**. |
| `regulations` | `1` | **Active Regulation**: Currently active for curriculum mapping. |
| `regulations` | `0` | **Superseded Regulation**. |
| `academic_settings` | `1` | **Editable Rule**: Configurable via SuperAdmin dashboard. |
| `academic_settings` | `0` | **System Locked Rule**: Core autonomous clause protected from UI mutation. |
| `buildings` | `1` | **Active Facility**. |
| `buildings` | `0` | **Under Renovation / Inactive**. |
| `halls` | `1` | **Usable Hall / Classroom**. |
| `halls` | `0` | **Temporarily Unavailable**. |

---

## 3. Attendance Constants

### 3.1 Attendance Status (`attendance.status`)
- `'P'`: **Present**. Counted towards attended hours.
- `'A'`: **Absent**. Counted towards conducted hours, but not attended.

### 3.2 Period / Hour (`attendance.hour`, `diary.hour`)
Integer value ranging from `1` through `7`, corresponding to the periods configured in `class_timings` for that date.

### 3.3 Attendance Deletion Status (`attendance_delete_requests.status`)
- `'pending'`: Awaiting review by the Department HOD.
- `'approved'`: Approved by HOD; matching records in `attendance` and `diary` have been purged.
- `'rejected'`: Rejected by HOD with comments recorded in `hod_remarks`.

---

## 4. Assessment Component Types (`assessment_components.component_type`)

ENUM values defining the structure of internal assessments:

| Component Type | Description |
|---|---|
| `Subjective` | Traditional descriptive examination (descriptive written answers). |
| `Objective` | Multiple choice, online quiz, or short-answer tests. |
| `Assignment` | Take-home assignments, term papers, or case study evaluations. |
| `Day-to-Day` | Continuous laboratory performance, observation, and record maintenance. |
| `Internal Exam` | Formal end-semester internal lab exam or viva voce. |
| `Subjective-1` | Mid-Term 1 descriptive test component. |
| `Objective-1` | Mid-Term 1 objective test component. |
| `Subjective-2` | Mid-Term 2 descriptive test component. |
| `Objective-2` | Mid-Term 2 objective test component. |
| `Activity` | Co-curricular, seminar, or mini-project assessment activity. |

---

## 5. Bloom's Taxonomy Cognitive Levels (`blooms_levels`)

Used in NBA/OBE question tagging and student marks attainment analysis:

| Level Code | Level Name | Cognitive Domain Target |
|---|---|---|
| `L1` | Remember | Recalling facts, terms, and basic concepts. |
| `L2` | Understand | Demonstrating comprehension of ideas and principles. |
| `L3` | Apply | Solving problems by applying acquired knowledge in new situations. |
| `L4` | Analyze | Examining and breaking information into parts to explore relationships. |
| `L5` | Evaluate | Justifying a stance, decision, or assessing work based on criteria. |
| `L6` | Create | Synthesizing elements into a new pattern, design, or original solution. |

---

## 6. Outcome-Based Education (OBE) Classifications

### 6.1 `po_pso.type`
- `'PO'`: **Program Outcome**. Twelve standard graduate attributes mandated by the National Board of Accreditation (NBA) (PO1 through PO12).
- `'PSO'`: **Program Specific Outcome**. Department-specific graduate competencies (PSO1 through PSO4).

### 6.2 Correlation Levels (`co_po_mapping.correlation_level`)
Numeric weighting indicating how strongly a Course Outcome addresses a Program Outcome:
- `1`: **Slight (Low)** correlation.
- `2`: **Moderate (Medium)** correlation.
- `3`: **Substantial (High)** correlation.
- `NULL` / `0`: No correlation.

---

## 7. Attendance Rule Operators (`attendance_rules`)

Configures regulatory percentage evaluation:

| Field | Typical Values | Meaning |
|---|---|---|
| `criteria` | 'Satisfactory', 'Condonation', 'Shortage', 'Detained' | Evaluation status category |
| `operator1` | `>=`, `>`, `<=`, `<` | Primary condition operator applied against `value1` |
| `value1` | `75.00`, `65.00`, `0.00` | Lower or primary percentage boundary |
| `operator2` | `<`, `<=`, `NULL` | Optional upper boundary operator applied against `value2` |
| `value2` | `74.99`, `64.99`, `NULL` | Optional upper boundary percentage |

*Example Rule*: Condonation range is defined as `operator1 = '>='`, `value1 = 65.00`, `operator2 = '<'`, `value2 = 75.00`.

---

## 8. Student Surveys & Institutional Feedback

### 8.1 5-Point Likert Rating Scale
Standardized rating score used across `student_co_feedback`, `student_ces_feedback`, `student_faculty_feedback`, and `student_questionnaire_responses`:

| Score | Descriptive Label | Indirect CO Attainment Meaning | Faculty Appraisal Meaning |
|---|---|---|---|
| `5` | Strongly Agree / Excellent | Complete mastery of outcome | Outstanding teaching effectiveness |
| `4` | Agree / Very Good | Proficient mastery of outcome | Commendable pedagogical delivery |
| `3` | Neutral / Good | Adequate competency (Threshold) | Satisfactory classroom instruction |
| `2` | Disagree / Fair | Incomplete understanding | Needs pedagogical improvement |
| `1` | Strongly Disagree / Poor | Deficient understanding | Unsatisfactory instruction |

### 8.2 Course Outcome (CO) Attainment Thresholds (`student_co_feedback`)
- **Target Attainment Benchmark**: $\ge 3.0$ on a 5.0 scale (or $\ge 60\%$) signifies that a student has met the learning outcome threshold.
- **Indirect Attainment Level Calculation**:
  - **Level 3**: $\ge 70\%$ of participating students rated $\ge 3.0$.
  - **Level 2**: $60\% - 69\%$ of participating students rated $\ge 3.0$.
  - **Level 1**: $50\% - 59\%$ of participating students rated $\ge 3.0$.
  - **Level 0**: $< 50\%$ of participating students rated $\ge 3.0$.

### 8.3 Course End Survey (CES) 5-Domain Evaluation (`student_ces_feedback`)
The 16 questions (`ces_q1` through `ces_q16`) map into five core NAAC/NBA quality domains:

| Domain Index | Domain Title | Covered Survey Items | Focus Area |
|---|---|---|---|
| **Domain 1** | Curriculum & Syllabus Design | `ces_q1` &ndash; `ces_q4` | Clarity, relevance, theory-application balance, and industry modernization |
| **Domain 2** | Pedagogy & Teaching-Learning Process | `ces_q5` &ndash; `ces_q8` | Instructional pace, interactive discussion, ICT tools, and doubt clarification |
| **Domain 3** | Continuous Assessment & Evaluation | `ces_q9` &ndash; `ces_q11` | Grading fairness, cognitive rigor of exam questions, and feedback timeliness |
| **Domain 4** | Learning Resources & Academic Support | `ces_q12` &ndash; `ces_q14` | Library/LMS resources, computing/lab facilities, and remedial support |
| **Domain 5** | Outcome Attainment & Competency | `ces_q15` &ndash; `ces_q16` | Problem-solving skills, practical confidence, and professional growth |

#### CES Qualitative Fields (Part-C):
- `useful_aspects`: Most beneficial and intellectually stimulating topics or components of the course.
- `improvement_topics`: Topics that students feel require greater depth, alternative teaching methods, or curriculum adjustment.
- `suggestions`: General constructive recommendations for syllabus revision or laboratory alignment.

### 8.4 Student Faculty Appraisal (`student_faculty_feedback`)
Evaluates instructor performance across 19 instructional dimensions:
- `fac_q1` to `fac_q5`: Subject expertise, lecture clarity, punctuality, syllabus schedule adherence, and presentation clarity.
- `fac_q6` to `fac_q10`: Interactive encouragement, real-world examples, grading impartiality, outside-hours mentoring, and classroom control.
- `fac_q11` to `fac_q15`: Lecture pacing, test script return timeliness, supplementary notes, ICT utilization, and career motivation.
- `fac_q16` to `fac_q19`: Approachability, support for struggling students, inspiring student interest, and overall effectiveness.

#### Faculty Appraisal Qualitative Fields:
- `faculty_strengths`: Key teacher qualities, clarity, and pedagogical strengths appreciated by students.
- `improvement_areas`: Specific constructive feedback for instructional improvement.
- `additional_comments`: Unstructured student feedback or commendations.

### 8.5 Anonymity Enforcement (`is_anonymous`)
- **`student_ces_feedback.is_anonymous`**: Defaults to `0` (identified by default, optional student anonymity).
- **`student_faculty_feedback.is_anonymous`**: Defaults to `1` (strict student anonymity). When set to `1`:
  - Student roll numbers and names are completely suppressed from faculty, HOD, and administrative views.
  - PDF reports and Excel exports mask student identities as "Anonymous Student" or aggregate metrics only.
  - Ensures unbiased, honest feedback without fear of academic retaliation.

---

## 9. Continuous Internal Assessment (CIA) Marks Schemas

Different degree levels and subject types store marks in dedicated specialized columns:

| Subject Type | Target Marks Table | Key Columns | Maximum Typical Marks |
|---|---|---|---|
| **UG Theory** | `internal_assessment_marks` | `subjective_marks`, `objective_marks`, `assignment_marks` | 30 marks (e.g., 20 subjective + 10 objective/assignment) |
| **PG Theory** | `pg_internal_assessment_marks` | `marks` | 40 marks |
| **UG Laboratory** | `uglab_internal_assessment_marks` | `day_to_day_marks`, `internal_test_marks` | 30 marks (e.g., 20 day-to-day + 10 lab test) |
| **UG Project** | `ugproject_internal_assessment_marks` | `component1_marks`, `component2_marks` | 50 / 100 marks (Review 1 & Review 2 defenses) |

---

## 10. Lesson Planning & End-of-Course Reconciliation

### 10.1 Lesson Plan Pedagogy Modes (`lesson_plans.pedagogy`)
- `Chalk & Talk`: Traditional board-and-marker or blackboard lecture.
- `PPT/LCD`: Digital slide presentation / projection.
- `Coding Demo`: Live interactive code execution or simulator demonstration.
- `Video`: Multimedia instructional video or NPTEL/virtual lab screening.
- `Flipped`: Flipped classroom collaborative discussion or student seminar.

### 10.2 Lesson Plan Bloom Levels (`lesson_plans.bloom_level`, `course_outcomes.bloom_level`)
- Standardized labels: `L1-Remember`, `L2-Understand`, `L3-Apply`, `L4-Analyze`, `L5-Evaluate`, `L6-Create`.

### 10.3 Course Completion Audit Lifecycle (`course_completion_audits`)
- **Faculty Sign-off Status (`faculty_signoff_status`)**:
  - `DRAFT`: Faculty is in the process of mapping chronological diary entries to planned lectures.
  - `SUBMITTED`: Faculty has submitted the reconciliation report and syllabus completion metrics for HOD review.
  - `APPROVED`: HOD has approved and locked the course delivery compliance audit.
- **HOD Approval Status (`hod_approval_status`)**:
  - `PENDING`: Awaiting review by the department head.
  - `APPROVED`: Formally signed off and integrated into e-Bluebook Section 2C.
  - `REJECTED`: Returned to instructor with remarks for correction.

---

## 11. Semester End Examination (SEE) Evaluation

### 11.1 SEE Entry Modes (`external_assessment_marks.entry_mode`)
- `DETAILED`: Mode A. Itemized question paper entry (compulsory Question 1 covering all COs + resolved either/or choices across Units 1 to 5). Supports direct question-level CO attainment.
- `DIRECT`: Mode B. Ledger entry of consolidated university marks out of maximum marks (typically 70 or 35). Direct attainment calculated proportionately across all course outcomes.
- `SYNCED`: Mode C. Synchronized automatically from official published exam results roster.

### 11.2 SEE Evidence Basis (`external_assessment_marks.evidence_basis`)
- `DETAILED_QP`: Question-wise marks entered per question (Q1 + Q2–Q9) mapped directly to designated Course Outcomes.
- `METADATA_DISTRIBUTION`: SEE Question Paper metadata uploaded (`see_question_paper_questions`). Marks distributed across COs based on question weights $\frac{\text{Marks}(CO)}{\text{TotalQP}}$.
- `DIRECT_SCALE`: Faculty direct entry of consolidated university marks scaled across all COs.
- `RESULT_SCALE`: Synced from published examination results roster (raw external marks or regulation grade band midpoint), distributed across all COs.

### 11.3 3-Tier SEE Attainment Distribution Strategy
As SEE completes the direct attainment cycle, the calculation engine implements three hierarchical tiers:
1. **Tier 1 (Detailed Question-wise Marks)**: When faculty enter itemized marks per question, each score maps directly to its mapped CO.
2. **Tier 2 (QP Metadata Distribution)**: When question paper structure metadata is defined, external scores are distributed in proportion to question CO weights.
3. **Tier 3 (Published Results & Grade Band Midpoint)**: When only published exam results are available:
   - If raw external marks exist: scaled to net max marks (70.0 UG) across all COs.
   - If only letter grade/points exist: resolved via regulation grade band midpoint $(\text{Min\%} + \text{Max\%})/2$ from `regulations.grade_bands_json` across all COs.

## 12. Autonomous Academic Settings Engine & Statutory Framework

### 12.1 Regulatory Policy Categories (`academic_settings.category`)
- `CIA`: Continuous internal assessment calculation rules (theory CIA 30 UG / 40 PG, lab CIA, mid weights 80:20, continuous assessment 10 marks).
- `SEE`: Semester end exam maximum marks (70 UG / 60 PG), question paper patterns (either/or), passing minimum thresholds (35% UG, 40% PG).
- `ATTENDANCE`: Minimum attendance per course (40% UG, 50% PG), aggregate attendance (75%), condonation floor (65%).
- `ATTAINMENT`: Direct/indirect OBE attainment weights (80:20), direct CIA/SEE weights (40:60), target benchmark (60%), NBA level cutoffs (50%, 60%, 70%).
- `GRADING`: Letter grade boundaries (A+/S, A, B, C, D, E, F), 10-point grade scales, aggregate pass percentage (40% UG, 50% PG), class award CGPA cutoffs.
- `GENERAL`: Institutional marks entry grace days (15), industry internship marks (100), comprehensive viva marks (100), co-curricular credits (1 cr), internal improvement subject caps (max 3 theory courses).
- `PROMOTION`: Multi-year academic promotion rules (Year 1 &rarr; 2: attendance; Year 2 &rarr; 3: 40% of credits up to III sem; Year 3 &rarr; 4: 40% of credits up to V sem).
- `HONORS`: B.Tech Honors degree requirements (additional 15 credits, min 7.0 CGPA up to III sem without backlogs, 75% attendance, max 2 subjects/sem from V sem).
- `MINOR`: B.Tech Minor degree requirements (additional 12 credits pursued across 4 Open Electives verticals/tracks).
- `MOOCS`: Credit mobility regulations, SWAYAM/SWAYAM Plus semester credit transfer limit (max 40% of electives), assignment vs exam weights (40:60), min 40% SEE and 50% aggregate.
- `PROJECT`: Project & dissertation rules (PG 300 marks: Review-II 100 CIE + Review-III 100 CIE + Viva-Voce 100 SEE; UG 200 marks: 60 internal + 140 external viva; Turnitin plagiarism threshold $\le 30\%$, publication mandate).
- `ACTIVITIES`: Guidelines and credit weights for student co-curricular activities, conferences, and scientific publications.

### 12.2 Data Types (`academic_settings.data_type`)
- `STRING`: Alphanumeric values and policy labels.
- `INT`: Integer configuration quantities (e.g., questions count, grace days, max subjects).
- `FLOAT`: Decimal weightages and percentages (e.g., `0.80`, `0.20`, `35.00`, `40.00`, `50.00`, `75.00`).
- `BOOL`: Boolean flags (`1` = enabled, `0` = disabled).
- `JSON`: Complex structured policies (e.g., `grade_bands_json`, `class_award_json`).

### 12.3 Statutory Degree Ceilings & Pathways (`regulations`)
| Parameter Column | Description | B.Tech R23 / R26 (UG) | M.Tech R25 (PG) |
|---|---|:---:|:---:|
| `normal_duration_years` | Standard prescribed duration | 4 Years | 2 Years |
| `max_duration_years` | Maximum statutory completion limit | 8 Years | 4 Years |
| `total_semesters` | Prescribed chronological semesters | 8 Semesters | 4 Semesters |
| `total_degree_credits` | Mandatory credits for degree award | **163.0 Credits** | **75.0 Credits** |
| `has_lateral_entry` | Lateral Entry Scheme (LES) support | Enabled (1) | Disabled (0) |
| `lateral_entry_credits` | Credits required for LES degree | 120.0 Credits | N/A |
| `has_honors` | Honors degree specialization pathway | Enabled (1) | Disabled (0) |
| `honors_credits` | Additional credits for Honors | 15.0 Credits | N/A |
| `has_minors` | Interdisciplinary Minors pathway | Enabled (1) | Disabled (0) |
| `minor_credits` | Additional credits for Minors | 12.0 Credits | N/A |
| `has_gap_year` | Student Entrepreneur in Residence | Enabled (1 - max 2 yrs) | Disabled (0) |
| `has_internal_improvement` | Internal evaluation marks re-registration | Disabled (0) | **Enabled (1 - max 3 subjects)** |

### 12.4 Master Course Categories (`regulation_course_categories`)
- **UG Curriculum (B.Tech R23 / R26 - 163.0 Total Target Credits)**:
  - `HM` (Humanities and Social Sciences): 13.0 cr (8.00% – 9.00%)
  - `BS` (Basic Sciences): 20.0 cr (12.00% – 16.00%)
  - `ES` (Engineering Sciences): 23.5 cr (10.00% – 18.00%)
  - `PC` (Professional Core): 54.5 cr (30.00% – 36.00%)
  - `PE` (Professional Electives): 15.0 cr (9.00% – 11.00%)
  - `OE` (Open Electives / Minor Verticals): 12.0 cr (7.00% – 8.50%)
  - `SEC` (Skill Enhancement Courses): 6.0 cr (3.50% – 5.00%)
  - `PR` (Internships & Project Work): 16.0 cr (8.00% – 11.00%)
  - `QT` (Quantum Technologies - Compulsory): 3.0 cr (1.50% – 2.00%)
  - `MC` (Mandatory Courses - Non-Credit): 0.0 cr
- **PG Curriculum (M.Tech R25 - 75.0 Total Target Credits)**:
  - `PC` (Foundational & Professional Core): 24.0 cr (30.00% – 35.00%)
  - `PE` (Professional Electives): 15.0 cr (18.00% – 22.00%)
  - `OE` (Open Electives): 3.0 cr (3.50% – 5.00%)
  - `MC` (Mandatory Credit Courses - Research Methodology & IPR, Quantum Tech): 4.0 cr (4.00% – 6.00%)
  - `SE` (Skill Enhancement Courses): 4.0 cr (4.50% – 6.00%)
  - `CV` (Comprehensive Viva Voce): 2.0 cr (2.00% – 3.00%)
  - `IN` (Short Term Industry Summer Internship): 3.0 cr (3.50% – 5.00%)
  - `DS` (Dissertation / Project Work): 20.0 cr (25.00% – 30.00%)
  - `AC` (Audit Courses - Non-Credit): 0.0 cr

### 12.5 Course Types & Canonical Evaluation Schemes (`regulation_course_types`)
- `THEORY`: Classroom lectures evaluated via mid-term tests and end-semester examinations.
  - UG (R23/R26): 30 CIE (two mids, 80:20 weight) + 70 SEE (either/or).
  - PG (R25): 40 CIE (30 mid + 10 continuous) + 60 SEE (5 either/or $\times$ 12).
- `LAB`: Hands-on practical/experimental laboratory courses.
  - UG: 30 CIE (15 day-to-day + 15 internal test) + 70 SEE (proc 20, exp 30, viva 20).
  - PG: 40 CIE (10 day-to-day + 10 record + 20 test) + 60 SEE (proc 10, exp 25, res 10, viva 15).
- `INTEGRATED`: Theory-cum-Laboratory hybrid course evaluated with dedicated theory and lab components.
- `PROJECT`: Project work, dissertations, seminars, comprehensive viva, and industrial internships.
- `AUDIT_NON_CREDIT`: Non-credit mandatory courses (Environmental Science, Constitution, Technical Paper/IPR).
  - Evaluated internally only (30 marks UG, 40 marks PG, no SEE). Min pass 40% UG / 50% PG.
- `OTHER`: Specialized workshops and institutional non-standard offerings.

### 12.6 Course Credit Calculation & Contact Hours Semantics
Under CBCS and AICTE guidelines, course credits ($C$) are computed deterministically from contact hours:
$$C = L + T + 0.5 \times \max(P, PR)$$
- $1 \text{ hour Lecture } (L) \text{ per week} = 1 \text{ credit}$
- $1 \text{ hour Tutorial } (T) \text{ per week} = 1 \text{ credit}$
- $2 \text{ hours Practical / Lab / Field Work } (P, PR) \text{ per week} = 1 \text{ credit}$ ($0.5 \text{ credit per hour}$)

---

## 13. Class Sectioning and Batch Grouping Semantics

### 13.1 Class Section (`classes.section`)
- Empty string (`''`): Single cohort class without division.
- Non-empty (e.g., `'A'`, `'B'`, `'1'`, `'2'`): Section within the specialization and semester. Standard class display names are generated via `SuperAdmin::generateClassName()` as `<Spec> - <YearSem> - Sec <Section>`.

### 13.2 Subject Group Name (`subjects.group_name`)
- Empty string (`''`): Regular core subject attended by all students in the class.
- Non-empty (e.g., `'A'`, `'B'`, `'1'`, `'2'`): Sub-batch division used for laboratory batches or student elective choice groups.

---

## 14. Feature Toggle Engine (`system_feature_modules`)

### 14.1 Grounded Feature Modules
The 27 system feature keys correspond strictly to verified functional modules:
- `MOD_ATTENDANCE`: Daily Period Attendance Marking & Verification.
- `MOD_ATT_REQUESTS`: Multi-step Attendance Deletion & Correction Requests.
- `MOD_CLASS_DIARY`: Classroom Teaching Diary & Topic Delivery Tracking.
- `MOD_CIA_MARKS`: Continuous Internal Assessment (Mid-1, Mid-2, Lab, PG, Project) Marks Entry.
- `MOD_CIA_METADATA`: CIA Assessment Components, Question-to-CO Mapping & File Attachments.
- `MOD_CO_PO`: Course Outcomes (CO1-CO6) Definition & CO-PO/PSO Articulation Matrix.
- `MOD_OBE_ANALYSIS`: Direct/Indirect Attainment Calculations & 14-page e-Bluebook Export.
- `MOD_LESSON_PLAN`: Unit-wise Lecture Plans & End-of-Course Delivery Reconciliation.
- `MOD_SEE_MARKS`: Semester End Examination (SEE) University Marks Entry.
- `MOD_FEEDBACK`: Student Feedback Surveys (Faculty Appraisal, CO Survey, Course End Survey).
- `MOD_GROUPED_ATT`: Grouped / Multi-Class Elective Attendance Marking.
- `MOD_BATCH_GOVERNANCE`: Batch-Centric Governance, Vision/Mission, PEOs & Macro-Attainment.
- `MOD_RESULTS_PUBLISH`: Examination Results Publication & SEE Marks Auto-Sync.
- `MOD_STUDENT_PROFILE`: Comprehensive Student Biographical Profile & Document Vault.
- `MOD_CERTIFICATES`: Statutory Certificate Generation & Custody Movement Ledger.
- `MOD_SAR_FORM_C3`: NBA SAR Form C-3 Standalone Attainment & Gap Analysis PDF Export.
- `MOD_COURSE_MASTER_FILE`: Autonomous Course Master File Accreditation Dossier (7-section PDF).
- `MOD_ATTENDANCE_CONDONATION_GAZETTE`: Regulatory Attendance Condonation & Detention Gazette.
- `MOD_BLOOM_ANALYSIS`: Real-time Bloom HOTS (L4–L6) vs LOTS (L1–L3) Cognitive Advisory Meter.
- `MOD_PO_PSO_ATTAINMENT`: PO/PSO Program Articulation Matrix (NBA Tables 3.2 & 3.3.2 with CES).
- `MOD_LAB_RUBRICS`: Continuous Laboratory Evaluation Rubrics Engine (Hybrid & Editable).
- `MOD_ATTENDANCE_DELETE_REQUESTS`: Single-Tier HOD Attendance Deletion Governance with Audit Trail.
- `MOD_STUDENT_ATTENDANCE_RISK`: Student Portal Attendance Risk Gauge (Safe Margin & Recovery Hours).
- `MOD_CQI_ACTION_TRACKING`: Continuous Quality Improvement (CQI) Remedial Action Plan Tracker.
- `MOD_CERTIFICATE_QR`: Cryptographic QR Credential Verification (SHA-256 Public Verification).
- `MOD_BATCH_OBE`: Multi-Batch Longitudinal OBE Analytics & Cohort Trajectory Radar Charts.
- `MOD_CREDIT_PROMOTION`: Autonomous Credit-Based Promotion Pipeline Simulation (R23 Sec 18).

### 14.2 Module Categories (`system_feature_modules.category`)
- `ATTENDANCE`: Attendance marking, grouped attendance, diary, deletion requests, condonation gazette.
- `CIA`: Continuous internal assessments, question paper Bloom's taxonomy, lab rubrics.
- `OBE`: Batch governance, PEO-Mission mapping, CO-PO articulation, attainment analytics, SAR Form C-3, CQI actions, multi-batch trends.
- `ACCREDITATION`: Autonomous Course Master File, compliance audits, syllabus verification.
- `EXAMINATION`: Official examination results publication, SEE marks ledger, credit promotion simulation.
- `STUDENTS`: Comprehensive student biographical profile and document vault.
- `STUDENT_PORTAL`: Student dashboard attendance risk gauges, internal marks reflections.
- `ADMINISTRATION`: Statutory certificate generation, physical document custody tracking.
- `GENERAL`: Cryptographic QR verification, institutional settings, system security.
- `CURRICULUM`: Unit-wise lesson planning, course structures, and reconciliation.
- `FEEDBACK`: Faculty appraisal, Course Outcome feedback, and Course End Surveys.
- `PROFILE`: Student biographical profiles, entrance exam ranks, digital document vault.
- `ADMINISTRATION`: Custodial certificate movement, TC, Bonafide, Study/Conduct certificates.
- `CIA`: Internal assessment marks, assessment metadata, question paper rubrics.
- `CURRICULUM`: Lesson plans, end-of-course syllabus reconciliation audits.
- `FEEDBACK`: Multi-tier feedback surveys, CES ratings, faculty appraisals.

### 14.3 Role Visibility Enum States
- **Faculty / HOD Visibility (`faculty_visibility`, `hod_visibility`)**:
  - `VISIBLE`: Module menu links and action buttons are fully displayed and active.
  - `HIDDEN`: Module menu links and action buttons are suppressed from the interface.
  - `READONLY`: Navigation remains visible, but write actions (POST submissions) are disabled with informational notices.
- **Student Visibility (`student_visibility`)**:
  - `VISIBLE`: Feature is accessible in the student portal (`jntuaceastudents/`).
  - `HIDDEN`: Feature and associated menu links are completely suppressed in the student portal.

---

## 15. Batch-Centric Governance & OBE Articulation Semantics

### 15.1 Student Cohort Batch (`student_batches`)
- `batch_name`: Standard cohort title formatted as `<AdmissionYear>-<GraduationYear>` (e.g. `2025-2029`).
- `admission_year`: Calendar year the cohort matriculated into the degree program.
- `graduation_year`: Projected graduation year (typically $\text{admission\_year} + 4$ for B.Tech).
- `is_active`: `1` = Active studying cohort; `0` = Graduated / archived cohort.

### 15.2 Correlation Articulation Weights (`batch_peo_mission_mapping`, `batch_po_peo_mapping`)
- `0`: No correlation / unmapped.
- `1`: Low / slight correlation.
- `2`: Medium / moderate correlation.
- `3`: High / substantial correlation.

### 15.3 Macro-Attainment Statuses
- `ATTAINED`: Target threshold reached ($\ge 75\%$ attainment of target benchmark).
- `PARTIAL`: Moderate progress ($50\% - 74.9\%$ attainment of target benchmark).
- `ACHIEVED`: Institutional mission alignment satisfied.
- `MODERATE`: Partial mission correlation satisfied.

---

## 16. Results Publication & Grade Point Conversion Scales

### 16.1 UGC Standard 10-Point Grade Scale Mapping
| Grade Letter | Grade Meaning | Grade Points | Pass / Fail Classification |
|---|---|:---:|:---:|
| `O` / `S` | Outstanding | 10 | PASS |
| `A+` / `EX` | Excellent | 9 | PASS |
| `A` | Very Good | 8 | PASS |
| `B+` | Good | 7 | PASS |
| `B` | Above Average | 6 | PASS |
| `C` | Average | 5 | PASS |
| `D` / `P` | Pass | 4 | PASS |
| `F` | Fail | 0 | FAIL |
| `AB` / `Ab` | Absent | 0 | ABSENT |
| `MP` | Malpractice | 0 | MALPRACTICE |
| `WH` / `W` | Withheld | 0 | WITHHELD |

### 16.2 Result Status (`exam_results.result_status`)
- `PASS`: Candidate secured qualifying minimum marks and grade points.
- `FAIL`: Candidate failed to achieve the passing threshold ($< 35\%$ SEE or $< 40\%$ aggregate).
- `ABSENT`: Candidate remained absent from the examination session.
- `MALPRACTICE`: Candidate disqualified due to examination malpractice.
- `WITHHELD`: Examination result withheld pending university clearance.

### 16.3 Legacy Results Scheme Compatibility (`resultsold`)
The ingestion pipeline natively normalizes legacy JNTUACEA results database formats:
- **Hall Ticket**: `std_regno`, `stdregno`, `regno`, `htno`, `rollnumber`.
- **Marks**: `im` (internal marks; `-7` = exempt / not applicable), `em` (external marks; `-1` = absent, `-5` = not applicable), `total`.
- **Result & Grade**: `result` (`P` &rarr; `PASS`, `F` &rarr; `FAIL`, `MP` &rarr; `MALPRACTICE`), `grade` (`Ab` &rarr; `ABSENT`), `grade_points`.
- **Credits Registered**: `credits` (in legacy dumps, failed students record `0` credits; tolerated without failing curriculum validation).

### 16.4 CIA vs Uploaded Internal Reconciliation Statuses
- `MATCH`: Exact agreement ($\Delta = 0$) between live portal-calculated CIA and uploaded internal marks (`im`).
- `VARIANCE`: Minor rounding difference ($|\Delta| \le 1.0$), attributable to Mid 1/Mid 2 80:20 integer rounding.
- `DISCREPANCY`: Significant divergence ($|\Delta| > 1.0$), requiring Controller of Examinations review.
- `MISSING_PORTAL_CIA`: Mark present in result roster, but internal evaluation not finalized by faculty in portal.
- `EXEMPT_OR_MISSING`: Subject exempt from internal assessment (`im = -7`).

### 16.5 Semester Grade Point Average (SGPA) Formula
$$\text{SGPA} = \frac{\sum_{i=1}^{n} (C_i \times G_i)}{\sum_{i=1}^{n} C_i}$$
Where $C_i$ represents registered credits for course $i$, and $G_i$ represents numerical grade points earned in course $i$. Courses with grade `F`, `AB`, or `MP` are retained in $\sum C_i$ with $G_i = 0$.

---

## 17. Student Dossier, Vault, Custody & Statutory Certificates

### 17.1 Physical Custody Statuses (`student_custodial_records.status`)
- `IN_CUSTODY`: Original hardcopy certificate resides securely in the college vault.
- `TEMPORARILY_RETURNED`: Original certificate temporarily checked out to student (e.g., passport, visa, higher education interview).
- `PERMANENTLY_RETURNED`: Original certificate handed back permanently upon graduation or formal exit.

### 17.2 Statutory Certificate Types (`student_certificate_requests.cert_type`)
- `CUSTODIAL`: Certificate listing all physical original certificates currently deposited in university safe custody.
- `BONAFIDE`: Official student bonafide certification for bank loans, scholarships, and bus/train concessions.
- `STUDY_CONDUCT`: Study and conduct record indicating enrolled period and behavioral rating.
- `TRANSFER_CERTIFICATE`: Formal institutional exit and transfer document (TC).
- `NO_DUES`: Multi-department clearance verifying clearance of library, laboratory, hostel, and tuition dues.

### 17.3 Certificate Request Processing Statuses (`student_certificate_requests.status`)
- `REQUESTED`: Submitted by student, pending review by Academic Section.
- `APPROVED`: Verified and approved by administrative staff.
- `REJECTED`: Declined with recorded administrative justification remarks.
- `GENERATED`: Official certificate issued with unique serial tracking number.
- `ISSUED`: Physically handed over or downloaded.

---

## 18. Accreditation Rubrics, CQI Remedial Actions & QR Verification

### 18.1 Laboratory Assessment Tripartite Criteria Standard
For 15-mark continuous lab evaluation under `MOD_LAB_RUBRICS`:
- **Conduction & Execution** (Max 5.00M): Experimental setup, circuit wiring, procedure execution, and laboratory safety compliance.
- **Observation & Record** (Max 5.00M): Accurate reading observation, calculation precision, graph plotting, and timely record book submission.
- **Viva Voce & Conceptual Understanding** (Max 5.00M): Oral inquiry into principles, apparatus functions, troubleshooting, and theoretical domain understanding.

### 18.2 CQI Remedial Action Types (`course_cqi_actions.action_type`)
- `REMEDIAL_CLASS`: Additional remedial lectures arranged for struggling students.
- `PEDAGOGICAL_CHANGE`: Adaptation of instructional strategy (active learning, flipped classroom, simulation tools).
- `TUTORIAL_HOURS`: Supplemental problem-solving tutorial sessions scheduled.
- `ASSIGNMENT_REVISION`: Redesign of assignments to target specific Course Outcome weaknesses.
- `CONTENT_BEYOND_SYLLABUS`: Guest lectures, workshops, or industry seminars to bridge curricular gaps.

### 18.3 CQI Review Lifecycle (`course_cqi_actions.hod_review_status`)
- `PENDING`: Action plan submitted by Course Faculty, awaiting HOD / PAC review.
- `APPROVED`: Formally endorsed by Department Head for implementation in subsequent academic session.
- `REVISION_REQUESTED`: Returned to instructor with HOD directions for strengthening the proposed intervention.

### 18.4 Cryptographic Verification Token Architecture
Statutory certificates and custodial receipts encode a tamper-evident SHA-256 validation token:
$$\text{Signature Token} = \text{hash}\Big(\text{"sha256"}, \text{certificate\_no} \mathbin{\Vert} \text{roll\_no}\Big)$$
Scanned via public URL `viewcertificate.php?cert_no=<NO>&token=<HASH>`. Matches verified against `student_certificate_requests` and `student_custodial_records`.

### 18.5 Autonomous Credit Promotion Rules (R23 Section 18)
Advisory simulation evaluated by `ExamResultsService::evaluateCreditPromotionSimulation()`:
- **Year 1 &rarr; Year 2**: Minimum 0% of total credits (admitted cohort progresses, subject to 75% attendance).
- **Year 2 &rarr; Year 3**: Minimum 40% of credits earned in Year 1 (Semesters I & II).
- **Year 3 &rarr; Year 4**: Minimum 40% of credits earned across Years 1 & 2 (Semesters I through IV).
- **Committee Override**: Academic Committee may clear flagged students with auditable override logged to `audit_logs` as `CLEARED BY COMMITTEE`.
