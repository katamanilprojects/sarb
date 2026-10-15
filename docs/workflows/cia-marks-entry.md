# Continuous Internal Assessment (CIA) Marks Entry & Calculation

This document outlines the workflows, calculation rules, and database pipelines for entering and calculating internal assessment marks for Undergraduate (Theory & Lab), Postgraduate (PG), and Project courses.

---

## 1. Assessment Categories & Entry Interfaces

The application supports four distinct evaluation pipelines based on the subject's curriculum structure:

| Assessment Type | Entry Controller | Database Table | Component Breakdown |
|---|---|---|---|
| **UG Theory** | `facaddciamarks.php` | `internal_assessment_marks` | Descriptive (Subjective), Objective/Quiz, Assignment |
| **UG Laboratory** | `facaddcialabmarks.php` | `uglab_internal_assessment_marks` | Day-to-Day performance, Internal Lab Examination |
| **Postgraduate (PG)** | `facaddpgciamarks.php` | `pg_internal_assessment_marks` | Mid Test, Assignment, Seminar presentation |
| **UG Project** | `facaddprojectciamarks.php` | `ugproject_internal_assessment_marks` | Project Phase Reviews (Review-1, Review-2, etc.) |

---

## 2. Marks Entry Lifecycle (UG Theory Example)

```mermaid
sequenceDiagram
    autonumber
    actor Faculty
    participant UI as facaddciamarks.php
    participant CIA as cia.class.php
    participant DB as internal_assessment_marks

    Faculty->>UI: Select Subject & Assessment Number (Mid-1 or Mid-2)
    UI->>CIA: getStudentsBySubjectId(subject_id)
    CIA->>DB: Query enrolled students & existing marks
    DB-->>CIA: Return roster with current scores
    CIA-->>UI: Render marks entry grid
    
    Faculty->>UI: Input Subjective, Objective, and Assignment marks
    Faculty->>UI: Click Submit
    
    loop For each student in roster
        UI->>CIA: addInternalAssessmentMarks(student_id, sub_id, mid_no, subjective, objective, assignment)
        CIA->>CIA: Calculate total_marks = (subjective + objective + assignment)
        alt Existing record exists
            CIA->>DB: UPDATE internal_assessment_marks SET ...
        else New record
            CIA->>DB: INSERT INTO internal_assessment_marks (...)
        end
    end
    
    CIA-->>UI: Return ['status' => 1]
    UI-->>Faculty: Success notification
```

---

## 3. Regulation Calculation Rules & Condensed Views

Under autonomous academic regulations (e.g., R15, R19, R20, R23), final internal marks are computed across Mid-1 and Mid-2 using specific weightage formulas:

### 3.1 Autonomous Regulatory Calculation Rules & Dynamic Settings
Under autonomous academic regulations (e.g., R15, R19, R20, R23), final internal marks are computed across Mid-1 and Mid-2 using specific weightage formulas retrieved dynamically via `SettingsService` (`academic_settings` table):
- **Mid-1 Total**: $\text{Mid}_1$ out of 30.
- **Mid-2 Total**: $\text{Mid}_2$ out of 30.
- **Combined Internal Calculation**:
  $$\text{Final Internal} = (W_{\text{best}} \times \max(\text{Mid}_1, \text{Mid}_2)) + (W_{\text{worst}} \times \min(\text{Mid}_1, \text{Mid}_2))$$
  - Under R19/R20/R23: $W_{\text{best}} = 0.80$ and $W_{\text{worst}} = 0.20$ (or $0.75 / 0.25$ depending on specific regulation configuration).
  - Configurable without code edits via `superadminacademicsettings.php`.

### 3.2 Condensed Views & Course File Services Separation

The platform enforces a strict separation between operational and accreditation course file generation:

- **Official 14-Page Operational Course File (`Services\EBluebookPDFService.php` - STRICTLY IMMUNE)**:
  - Downloaded via `download_bluebook.php` (`MOD_OBE_ANALYSIS`).
  - **Section 1**: Course Bio & Student Nominal Roll.
  - **Section 2A**: Lecture Lesson Plan (Estimated Diary).
  - **Section 2B**: Diary of Lecturer Classes (4-column statutory format).
  - **Section 2C**: Course Delivery Compliance & Deviation Report (NBA 2.2 milestone dates, deviations, compensatory classes, and signatures).
  - **Section 3**: Student Attendance Register.
  - **Section 4**: Continuous Internal Assessment (CIA) Marks Register with component breakdowns.
  - *Architectural Rule*: `EBluebookPDFService.php` is strictly immutable to preserve operational audit conformance.

- **Autonomous Course Master File (`Services\CourseMasterFilePDFService.php` - DEDICATED SERVICE)**:
  - Downloaded via `download_coursemasterfile.php?sub_id=X` (`MOD_COURSE_MASTER_FILE`).
  - Generates comprehensive 7-section master dossier:
    1. Institutional Cover Page with Autonomous College Credentials.
    2. Course Syllabus & Bloom-tagged Course Outcomes (CO1–CO5).
    3. Lesson Plan Schedule (Unit, topic, proposed date, pedagogy, Bloom level).
    4. Class Diary Execution Ledger with student presence counts.
    5. Lesson Plan Delivery Reconciliation & Date Variance Audit.
    6. DAAC Course Audit Sign-off Certificate with scoring ($\ge 85\%$).
    7. Consolidated CIA Marks Award Sheet.

- **Condensed Marks Grids**:
  - **`facciamarkscondensed.php`**: Renders side-by-side Mid-1, Mid-2, and final weighted aggregate scores.
  - **`facuglabciamarkscondensed.php`**: Consolidates Day-to-Day and Lab Exam marks into final lab internal score.
  - **`download_cls_cia.php`**: Tabular class CIA marks summary report.

---

## 4. Assessment Question & CO Tagging with Bloom's Cognitive Advisory

For NBA accreditation, faculty configure question papers with cognitive domain tagging:
- **`facciacomp.php`**: Configures assessment components (Mid-1 Subjective, Objective, etc.).
- **`facciacompques.php`**: Defines question labels (Q1a, Q1b, Q2), maximum marks, and Bloom's cognitive level (`blooms_level_id`).
- **`facquestionco.php`**: Maps each question to Course Outcomes (`course_outcomes`).
- **Real-Time Bloom HOTS/LOTS Advisory Indicator (`MOD_BLOOM_ANALYSIS`)**:
  - Live client-side calculation in `facquestionco.php` and `facaddciamarks.php`.
  - Calculates mark percentage for Higher Order Thinking Skills (L4-Analyze, L5-Evaluate, L6-Create) vs Lower Order Thinking Skills (L1-Remember, L2-Understand, L3-Apply):
    $$\text{HOTS \%} = \frac{\sum \text{Marks}_{\text{L4--L6}}}{\sum \text{Marks}_{\text{Total}}} \times 100$$
  - Displays dynamic ambient badge: Green ($\ge 40\%$) or Yellow ($< 40\%$).
  - Submissions are **strictly non-blocking** unless `academic_settings.bloom_hots_lots_enforce = 1`.
- **`facmarksentry.php`**: Enables granular question-by-question marks entry stored in `student_marks`.

---

## 5. Continuous Laboratory Evaluation: Dual-Mode Rubrics Engine (`MOD_LAB_RUBRICS`)

In `facaddcialabmarks.php`:
- **Direct Manual Entry (Primary & Unchanged)**: Direct input of `day_to_day_marks[]` (Max 15M) and `internal_test_marks[]` (Max 15M) remains fully operational and default.
- **Rubrics Assistant Modal (Additive Tool)**:
  - Renders when `MOD_LAB_RUBRICS` is enabled.
  - Fetches criteria from `lab_rubric_criteria` (or custom overrides in `lab_subject_rubrics`).
  - Standard tripartite criteria: Conduction (5M), Observation/Record (5M), Viva Voce (5M).
  - Client-side auto-summing calculates criteria total and populates `day_to_day_marks[]`.
  - When submitted, criteria breakdowns persist into `lab_rubric_evaluations` linked to `uglab_internal_assessment_marks.id`.

---

## 6. File Attachments Subsystem (`facciaattachments.php`)

Faculty can attach digital documentation to each assessment (question papers, answer keys, assignment prompts):
- Files are saved in `uploads/cia_attachments/`.
- Stored in `cia_attachments`:
  - `subject_id`, `assessment_number`, `file_title`, `file_path`, `uploaded_at`.
- Managed via `Faculty::addCIAAttachment()` and `Faculty::deleteCIAAttachment()`.

---

## 7. Semester End Examination (SEE) Evaluation Pipeline

Managed via `SEEAssessment` (`seeassessment.class.php`) and stored in `external_assessment_marks`:
- **Modes of Entry**:
  - **Mode A (`DETAILED`)** via `facseecompques.php` & `facseemarksentry.php`: Granular entry of compulsory Question 1 (covering all COs) and elective choices (Units 1-5), supporting question-level direct external attainment.
  - **Mode B (`DIRECT`)** via `facseedirectmarks.php`: Direct entry of consolidated university marks out of maximum marks (70 or 35).
- **Consolidated External Review (`facseemarkscondensed.php`)**: Side-by-side ledger displaying scored external marks and status.
