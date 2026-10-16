# Database Schema Reference

This document provides an exhaustive, field-by-field reference of all **90 tables** (821 columns), data types, nullability, keys, and defaults extracted directly from the system database (`u182589698_jntuaceasarb`).

---

## Functional Domain Index

The 90 tables in the database are organized into 12 functional domains:
1. [Identity, Access & Audit Logging](#1-identity-access--audit-logging) (8 tables)
2. [Academic Programs, Batches & Structure](#2-academic-programs-batches--structure) (10 tables)
3. [Enrollment & Course Allotment](#3-enrollment--course-allotment) (2 tables)
4. [Daily Attendance, Lesson Plans & Course Audit](#4-daily-attendance-lesson-plans--course-audit) (7 tables)
5. [Timetable Management](#5-timetable-management) (3 tables)
6. [Continuous Internal & External Assessment (CIA/SEE) & Marks](#6-continuous-internal-assessment-cia--marks) (12 tables)
7. [Outcome-Based Education (OBE) & Attainment](#7-outcome-based-education-obe--attainment) (13 tables)
8. [Physical Infrastructure, Surveys & Student Feedback](#8-physical-infrastructure-surveys--student-feedback) (7 tables)
9. [Autonomous Academic Settings & Parameters](#9-autonomous-academic-settings--parameters) (4 tables)
10. [Institutional Governance: Cohort Batches, Results Publication & Student Dossier](#10-institutional-governance-cohort-batches-results-publication--student-dossier) (12 tables)
11. [Accreditation: Laboratory Evaluation Rubrics & CQI Action Tracking](#11-accreditation-laboratory-evaluation-rubrics--cqi-action-tracking) (5 tables)
12. [Trusted Core Snapshots, Review History, Migrations & Optional AI Infrastructure](#12-trusted-core-snapshots-review-history-migrations--optional-ai-infrastructure) (7 tables)

---

## 1. Identity, Access & Audit Logging
Core user credentials, identity mapping across students and faculties, organizational departments, and transaction audit trails.

### 1.1 `users`
Master user accounts storing authentication credentials, contact details, and role assignments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | UNI | AUTO_INCREMENT | Unique user identifier (auto-increment surrogate key) |
| `username` | `varchar(20)` | No | PRI | - | Primary login identifier (student roll number, faculty username, dept code) |
| `password` | `varchar(255)` | No | - | - | Hashed password (BCrypt or upgraded legacy hash) |
| `name` | `varchar(100)` | No | - | - | Full legal name of the user |
| `mobile` | `varchar(20)` | Yes | - | NULL | User mobile contact number |
| `email` | `varchar(60)` | Yes | - | NULL | User email address |
| `role` | `varchar(20)` | No | - | - | Access control role: 'superadmin', 'admin', 'academic_section', 'hod', 'faculty', 'student' |
| `status` | `int(5)` | No | - | - | 1 = Active, 0 = Inactive / Suspended |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

> **Unique Keys**: (`id`)

### 1.2 `faculties`
Faculty member profiles linking login accounts to academic departments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Faculty profile entity ID |
| `username` | `varchar(30)` | No | MUL | - | Foreign key referencing users.username |
| `designation` | `varchar(30)` | No | - | - | Academic designation (Professor, Associate Professor, Assistant Professor, Adhoc Lecturer) |
| `facultyid` | `int(5)` | No | MUL | - | Optional foreign key referencing users.id |
| `dept_id` | `int(11)` | No | MUL | - | Foreign key referencing departments.id |
| `sort_order` | `int(11)` | No | MUL | 0 | Custom display sequence within the department (`idx_faculties_dept_sort`) |
| `status` | `varchar(5)` | No | - | - | 1 = Active (eligible for timetable/allotment), 0 = Relieved/Inactive |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `facultyid` &rarr; `users(id)`, `username` &rarr; `users(username)`, `dept_id` &rarr; `departments(id)`

### 1.3 `students`
Student enrollment profiles linking roll numbers to academic cohorts and classes.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Student profile entity ID |
| `username` | `varchar(20)` | No | MUL | - | Roll number / Hall ticket number referencing users.username |
| `class_id` | `int(5)` | No | MUL | - | Foreign key referencing classes.id |
| `status` | `int(5)` | No | - | - | 1 = Active (enrolled), 0 = Detained / Inactive |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `date_of_joining` | `date` | Yes | - | NULL | Official date of institutional admission |

> **Foreign Keys**: `class_id` &rarr; `classes(id)`, `username` &rarr; `users(username)`

### 1.4 `departments`
Academic departments across the institution.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Department ID |
| `username` | `varchar(30)` | No | - | - | HOD login username in users table |
| `dept_shortname` | `varchar(20)` | No | - | - | Short departmental acronym (e.g., EEE, ECE, CSE, MECH, CIVIL, CHEM) |
| `dept_fullname` | `varchar(100)` | No | - | - | Full official departmental title |
| `status` | `int(5)` | No | - | - | 1 = Active, 0 = Archived / Inactive |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

### 1.5 `activity_logs`
System-wide administrative audit trail capturing administrative transactions.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Log entry ID |
| `user_id` | `int(11)` | No | MUL | - | Foreign key referencing users.id |
| `action` | `varchar(255)` | No | - | - | Action classification (e.g., LOGIN, PASSWORD_RESET, ENROLL_STUDENT) |
| `details` | `text` | Yes | - | NULL | Descriptive audit details or JSON payload |
| `timestamp` | `datetime` | Yes | - | current_timestamp() | - |

> **Foreign Keys**: `user_id` &rarr; `users(id)`

### 1.6 `fac_activity_logs`
Dedicated legacy audit log tracking faculty operations (attendance marking, diary entries, deletion requests).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Log entry ID |
| `user_id` | `int(11)` | No | MUL | - | Foreign key referencing faculties.id |
| `action` | `varchar(255)` | No | - | - | Faculty action code (e.g., ATTENDANCE_MARKED, DIARY_POSTED, DELETION_SUBMITTED) |
| `details` | `text` | Yes | - | NULL | Context details (subject_id, date, hour count, students affected) |
| `timestamp` | `datetime` | Yes | - | current_timestamp() | - |

> **Foreign Keys**: `user_id` &rarr; `faculties(id)`

### 1.7 `audit_logs`
Primary unified forensic and governance audit trail capturing authenticated user actions across all roles via `DBCredentials::dbActivityLog()`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | No | PRI | AUTO_INCREMENT | Immutable audit record ID |
| `user_id` | `int(11)` | Yes | MUL | NULL | Authenticated user ID referencing `users.id` |
| `username` | `varchar(60)` | Yes | - | NULL | Login username at time of action |
| `role` | `varchar(30)` | No | MUL | unknown | Active session role (`superadmin`, `admin`, `academic_section`, `hod`, `faculty`, `student`) |
| `action` | `varchar(64)` | No | MUL | - | Semantic action code (e.g., `ADD_TIMETABLE_ENTRY`, `CHANGE_ROLE_PERMISSION`, `ATTENDANCE_STUDENT_UPDATE`) |
| `entity_type` | `varchar(64)` | Yes | MUL | NULL | Target domain entity type (`TIMETABLE`, `FEATURE_PERMISSION`, `ATTENDANCE`, etc.) |
| `entity_id` | `varchar(64)` | Yes | MUL | NULL | Target entity primary key or composite identifier |
| `target_user_id` | `int(11)` | Yes | - | NULL | Optional secondary user ID affected by the operation |
| `status` | `varchar(20)` | No | - | SUCCESS | Execution outcome (`SUCCESS`, `FAILED`, `DENIED`) |
| `details` | `text` | Yes | - | NULL | Detailed context narrative or JSON metadata |
| `ip_address` | `varchar(45)` | No | - | 0.0.0.0 | Client IPv4/IPv6 address |
| `user_agent` | `varchar(255)` | Yes | - | NULL | Requesting browser/client User-Agent string |
| `created_at` | `timestamp` | No | MUL | current_timestamp() | Event creation timestamp |

### 1.8 `role_module_permissions`
Granular action-level role-based access control (RBAC) permissions per feature module managed via `FeatureManager::savePermission()`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `role` | `varchar(30)` | No | PRI | - | Target role (`faculty`, `hod`, `admin`, `academic_section`, `student`) |
| `module_key` | `varchar(50)` | No | PRI | - | Feature module key referencing `system_feature_modules.module_key` |
| `action` | `varchar(20)` | No | PRI | - | Controlled operation (`view`, `edit`, `approve`, `export`) |
| `is_allowed` | `tinyint(1)` | No | - | 0 | 1 = Permitted, 0 = Denied |
| `updated_by` | `int(11)` | Yes | - | NULL | SuperAdmin `users.id` who modified the permission |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

> **Primary Key**: (`role`, `module_key`, `action`)


## 2. Academic Programs, Batches & Structure
Hierarchical academic scaffolding: terms, degree programs, regulations, specializations, admitted cohorts (batches), class cohorts, subjects, and master curriculum catalogs.

### 2.1 `academic_years`
Academic year terms (e.g., 2023-2024, 2024-2025).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Academic year ID |
| `acad_year` | `varchar(20)` | No | UNI | - | Academic year formatted label (e.g., 2024-2025) |
| `status` | `int(11)` | No | - | - | - |

> **Unique Keys**: (`acad_year`)

### 2.2 `programs`
Degree programs offered by the institution (e.g., B.Tech, M.Tech, MCA).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Program ID |
| `program_code` | `varchar(5)` | No | UNI | - | Unique degree program code (e.g., UG, PG) |
| `prog_shortname` | `varchar(20)` | No | - | - | Short code (e.g., B.Tech, M.Tech, MCA) |
| `prog_fullname` | `varchar(50)` | No | - | - | Full degree name (e.g., Bachelor of Technology, Master of Technology) |
| `program_level` | `enum('UG','PG','PHD')` | No | - | UG | Degree level classification |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`program_code`)

### 2.3 `regulations`
Academic regulations governing curriculum, statutory degree ceilings, evaluation schemes, attendance rules, and graduation criteria.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Regulation ID |
| `regulation` | `varchar(4)` | No | - | - | Regulation code string (e.g., R20, R23, R25, R26) |
| `prog_id` | `int(11)` | No | MUL | - | Foreign key referencing programs.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `start_year` | `int(4)` | Yes | - | NULL | Academic implementation year of regulation |
| `is_active` | `tinyint(1)` | No | - | 1 | 1 = Active, 0 = Superseded / Inactive |
| `normal_duration_years` | `tinyint(3) unsigned` | No | - | 4 | Standard degree program duration in years (e.g., 4 for UG, 2 for PG) |
| `max_duration_years` | `tinyint(3) unsigned` | No | - | 8 | Maximum statutory graduation ceiling before seat forfeiture (e.g., 8 for UG, 4 for PG) |
| `gap_year_extension_years` | `tinyint(3) unsigned` | No | - | 0 | Additional years allowed for Student Entrepreneur in Residence (e.g., 2 for UG) |
| `total_semesters` | `tinyint(3) unsigned` | No | - | 8 | Standard prescribed semesters (e.g., 8 for UG, 4 for PG) |
| `total_degree_credits` | `decimal(5,1)` | No | - | 160.0 | Mandatory credits required for degree award (e.g., 163.0 for B.Tech R23/R26, 75.0 for M.Tech R25) |
| `lateral_entry_credits` | `decimal(5,1)` | No | - | 0.0 | Mandatory credits for Lateral Entry Scheme (e.g., 120.0 for B.Tech LES) |
| `honors_credits` | `decimal(5,1)` | No | - | 0.0 | Additional specialized credits for B.Tech Honors degree (e.g., 15.0) |
| `minor_credits` | `decimal(5,1)` | No | - | 0.0 | Additional interdisciplinary credits for B.Tech Minors degree (e.g., 12.0) |
| `has_lateral_entry` | `tinyint(1)` | No | - | 0 | 1 = Lateral Entry Scheme supported, 0 = Direct entry only |
| `has_honors` | `tinyint(1)` | No | - | 0 | 1 = Honors degree pathway enabled, 0 = Disabled |
| `has_minors` | `tinyint(1)` | No | - | 0 | 1 = Minor degree pathway enabled, 0 = Disabled |
| `has_gap_year` | `tinyint(1)` | No | - | 0 | 1 = Entrepreneurial Gap Year facility available, 0 = Disabled |
| `has_internal_improvement`| `tinyint(1)` | No | - | 0 | 1 = Internal evaluation re-registration permitted (e.g., M.Tech R25 max 3 subjects), 0 = No |
| `effective_admitted_batch` | `varchar(50)` | Yes | - | NULL | Official admitted batch applicability (e.g., '2023-24 onwards') |
| `les_effective_batch` | `varchar(50)` | Yes | - | NULL | Lateral Entry batch applicability (e.g., '2024-25 onwards') |

> **Foreign Keys**: `prog_id` &rarr; `programs(id)`

### 2.4 `specialization`
Specialization branches/disciplines tied to departments and degree programs.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Specialization ID |
| `spec_code` | `varchar(20)` | No | - | - | Branch code (e.g., 01, 02, 03, 04, 05) |
| `spec_shortname` | `varchar(50)` | No | - | - | Branch short code (e.g., ECE, CSE, EEE, ME, CE) |
| `spec_fullname` | `varchar(100)` | No | - | - | Full specialization title (e.g., Computer Science and Engineering) |
| `dept_id` | `int(5)` | No | MUL | - | Foreign key referencing departments.id |
| `prog_id` | `int(5)` | No | MUL | - | Foreign key referencing programs.id |
| `status` | `tinyint(1)` | No | MUL | 1 | 1 = Active, 0 = Inactive / Discontinued |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `dept_id` &rarr; `departments(id)`, `prog_id` &rarr; `programs(id)`

### 2.5 `student_batches`
Admitted student cohorts adhering to specific academic regulations across their degree lifecycle.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Batch entity ID |
| `program_id` | `int(5)` | No | MUL | - | Foreign key referencing programs.id |
| `regulation_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `batch_name` | `varchar(50)` | No | - | - | Admitted cohort label (e.g., 2023-2027) |
| `admission_year`| `int(4)` | No | - | - | Year of student admission |
| `graduation_year`| `int(4)` | No | - | - | Expected year of degree completion |
| `is_active` | `tinyint(1)` | No | - | 1 | 1 = Active cohort, 0 = Graduated / Inactive |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |

> **Unique Keys**: (`program_id`,`batch_name`)
> **Foreign Keys**: `program_id` &rarr; `programs(id)`, `regulation_id` &rarr; `regulations(id)`

### 2.6 `classes`
Academic classes/cohorts defining a group of students in an academic year, semester, section, and specialization.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Class cohort ID |
| `acad_year` | `varchar(10)` | No | MUL | - | Academic year string (e.g., 2024-2025) |
| `classname` | `varchar(60)` | No | - | - | Human-readable class label (e.g., CSE - III-I - Sec A) |
| `yearsem` | `varchar(20)` | No | - | - | Year and semester numeral (e.g., 11=I-I, 12=I-II, 21=II-I, 41=IV-I) |
| `section` | `varchar(10)` | No | - | '' | Optional section identifier (e.g., 'A', 'B', '1', '2' or empty) |
| `start_date` | `date` | No | - | - | Semester instructional start date |
| `end_date` | `date` | No | - | - | Semester instructional end date |
| `status` | `int(11)` | No | - | 1 | 1 = Current / Active, 0 = Archived |
| `spec_id` | `int(5)` | No | MUL | - | Foreign key referencing specialization.id |
| `timing_id` | `int(11)` | No | - | 1 | Class timing slot group ID referencing class_timings.timing_id |
| `reg_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `batch_id` | `int(11)` | Yes | MUL | NULL | Optional foreign key referencing student_batches.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `spec_id` &rarr; `specialization(id)`, `reg_id` &rarr; `regulations(id)`, `batch_id` &rarr; `student_batches(id)`

### 2.7 `subjects`
Curriculum subjects/courses taught within an academic class offering.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Subject offering ID |
| `subject_sno` | `varchar(3)` | No | - | - | Ordering serial number within the syllabus |
| `subcode` | `varchar(20)` | No | - | - | Course catalog code (e.g., 20A05501T) |
| `sub_shortname` | `varchar(20)` | No | - | - | Subject short acronym (e.g., CN, OS, DBMS, DAA) |
| `sub_fullname` | `varchar(100)` | No | - | - | Full descriptive subject title (e.g., Computer Networks) |
| `group_name` | `varchar(10)` | No | - | '' | Group identifier for batch divisions/electives (e.g., '', 'A', 'B', '1', '2') |
| `sub_type` | `varchar(20)` | No | - | - | Course classification: Theory, Lab, Project, Mandatory Course |
| `class_id` | `int(5)` | No | MUL | - | Foreign key referencing classes.id |
| `curr_sub_id` | `int(11)` | Yes | MUL | NULL | Foreign key referencing curriculum_subjects.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`class_id`,`subcode`,`group_name`)
> **Foreign Keys**: `class_id` &rarr; `classes(id)`, `curr_sub_id` &rarr; `curriculum_subjects(id)`

### 2.8 `curriculum_subjects`
Central syllabus subject catalog managed by Academic Section, decoupled from active academic years and class instances.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Central curriculum subject ID |
| `prog_id` | `int(11)` | No | MUL | - | Foreign key referencing programs.id |
| `reg_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `spec_id` | `int(11)` | No | MUL | - | Foreign key referencing specialization.id |
| `yearsem` | `varchar(30)` | No | - | - | Year and semester string (e.g., II Yr - I Sem) |
| `subject_sno` | `int(3)` | No | - | - | Ordering serial number within the syllabus (1..20) |
| `subcode` | `varchar(25)` | No | MUL | - | Catalog course code (e.g., 23A05301T) |
| `sub_fullname` | `varchar(150)` | No | - | - | Full descriptive course title |
| `sub_shortname` | `varchar(30)` | No | - | - | Course short title / acronym |
| `sub_type` | `varchar(30)` | No | - | - | Course type code (Theory, Lab, Mandatory Course, Skill Oriented, etc.) |
| `course_category` | `varchar(20)` | No | - | '' | Statutory curriculum category code (e.g., PC, PE, OE, BS, ES, HM, SEC, PR, QT, MC, CV, IN, DS) |
| `lecture_hours` | `decimal(3,1)` | No | - | 0.0 | Weekly lecture contact hours ($L$) |
| `tutorial_hours` | `decimal(3,1)` | No | - | 0.0 | Weekly tutorial contact hours ($T$) |
| `pr_hours` | `decimal(3,1)` | No | - | 0.0 | Weekly practical contact hours ($PR$) |
| `practical_hours` | `decimal(3,1)` | No | - | 0.0 | Weekly laboratory/field practical contact hours ($P$) |
| `credits` | `decimal(3,1)` | No | - | 0.0 | Total course credits ($C = L + T + 0.5 \times \max(P, PR)$) |
| `status` | `tinyint(1)` | No | - | 1 | 1 = Active in syllabus, 0 = Inactive / Deprecated |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `cie_max_marks` | `decimal(5,2)` | No | - | 30.00 | Maximum Continuous Internal Assessment (CIE) marks (e.g., 30 for UG, 40 for PG) |
| `see_max_marks` | `decimal(5,2)` | No | - | 70.00 | Maximum Semester End Examination (SEE) marks (e.g., 70 for UG, 60 for PG) |
| `total_marks` | `decimal(5,2)` | No | - | 100.00 | Total evaluated marks (CIE + SEE) |
| `has_see` | `tinyint(1)` | No | - | 1 | 1 = Has external university examination, 0 = Internal assessment only |
| `elective_track` | `varchar(50)` | Yes | - | NULL | Optional elective vertical pool / track label (e.g., PE-1, PE-2, Track 1 - AI/ML, Minor Track) |
| `delivery_mode` | `enum('CONVENTIONAL','BLENDED','ONLINE_MOOC')` | No | - | CONVENTIONAL | Course delivery mode |
| `prerequisites` | `varchar(255)` | Yes | - | NULL | Optional prerequisite course codes or eligibility notes |

> **Unique Keys**: (`reg_id`,`spec_id`,`yearsem`,`subcode`)
> **Foreign Keys**: `prog_id` &rarr; `programs(id)`, `reg_id` &rarr; `regulations(id)`, `spec_id` &rarr; `specialization(id)`

### 2.9 `regulation_course_categories`
Master course categories defined by SuperAdmin for each regulation, establishing statutory credit distribution, percentage limits, and Board of Studies (BoS) compliance ceilings.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Category record ID |
| `reg_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `category_code` | `varchar(20)` | No | - | - | Category short code (e.g., HM, BS, ES, PC, PE, OE, SEC, PR, QT, MC, CV, IN, DS) |
| `category_name` | `varchar(100)` | No | - | - | Full formal category name (e.g., Professional Core, Basic Sciences, Quantum Technologies) |
| `min_allocation_pct` | `decimal(5,2)` | Yes | - | 0.00 | Minimum statutory curriculum percentage allocation |
| `max_allocation_pct` | `decimal(5,2)` | Yes | - | 0.00 | Maximum statutory curriculum percentage allocation |
| `target_credits` | `decimal(5,1)` | Yes | - | 0.0 | Prescribed target credit ceiling for the degree regulation |
| `description` | `varchar(255)` | Yes | - | NULL | Statutory context, regulatory clause, and curriculum guidelines |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

> **Unique Keys**: (`reg_id`,`category_code`)
> **Foreign Keys**: `reg_id` &rarr; `regulations(id)`

### 2.10 `regulation_course_types`
Master course delivery types and examination evaluation schemes defined per regulation by SuperAdmin, governing marks distribution and assessment rules.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Course type record ID |
| `reg_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `type_code` | `varchar(30)` | No | - | - | Course type code (e.g., Theory, Lab, Integrated, Project Work, Skill Enhancement) |
| `type_name` | `varchar(100)` | No | - | - | Descriptive course type title |
| `evaluation_scheme` | `enum('THEORY','LAB','INTEGRATED','PROJECT','AUDIT_NON_CREDIT','OTHER')` | No | - | THEORY | Canonical assessment evaluation scheme |
| `cie_max_marks` | `decimal(5,2)` | Yes | - | 30.00 | Default Continuous Internal Assessment (CIE) maximum marks |
| `see_max_marks` | `decimal(5,2)` | Yes | - | 70.00 | Default Semester End Examination (SEE) maximum marks |
| `total_marks` | `decimal(5,2)` | Yes | - | 100.00 | Total marks for the course type |
| `has_see` | `tinyint(1)` | No | - | 1 | 1 = Conducts end examination, 0 = Internal only |
| `is_credit_course` | `tinyint(1)` | No | - | 1 | 1 = Counts toward degree credits, 0 = Non-credit audit course |
| `description` | `varchar(255)` | Yes | - | NULL | Question paper pattern and internal evaluation scheme description |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

> **Unique Keys**: (`reg_id`,`type_code`)
> **Foreign Keys**: `reg_id` &rarr; `regulations(id)`


## 3. Enrollment & Course Allotment
Junction entities mapping instructors and students to curriculum courses.

### 3.1 `faculty_sub`
Junction table assigning instructors to teach specific subjects.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Mapping ID |
| `faculty_id` | `int(5)` | No | UNI | - | Foreign key referencing faculties.id |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`faculty_id`,`sub_id`)
> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `sub_id` &rarr; `subjects(id)`

### 3.2 `student_sub`
Junction table enrolling individual students into specific subjects (supports core and elective enrollments).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Enrollment mapping ID |
| `stu_id` | `int(5)` | No | UNI | - | Foreign key referencing students.id |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`stu_id`,`sub_id`)
> **Foreign Keys**: `stu_id` &rarr; `students(id)`, `sub_id` &rarr; `subjects(id)`


## 4. Daily Attendance, Lesson Plans & Course Audit
Daily lecture period attendance marking, faculty syllabus diary coverage, lesson planning, end-of-course reconciliation, deletion/correction workflows, and condonation rules.

### 4.1 `attendance`
Daily period-by-period student attendance tracking records.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Attendance record ID |
| `stu_id` | `int(5)` | No | UNI | - | Foreign key referencing students.id |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `date` | `varchar(10)` | No | UNI | - | Calendar date of attendance (YYYY-MM-DD) |
| `hour` | `int(11)` | No | UNI | - | Period / hour number (1 to 7) |
| `status` | `varchar(5)` | No | - | - | Attendance mark: 'P' = Present, 'A' = Absent |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`stu_id`,`sub_id`,`date`,`hour`)
> **Foreign Keys**: `stu_id` &rarr; `students(id)`, `sub_id` &rarr; `subjects(id)`

### 4.2 `diary`
Faculty daily teaching diary documenting syllabus content covered during each conducted period.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Diary record ID |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `faculty_id` | `int(5)` | No | UNI | - | Foreign key referencing faculties.id |
| `date` | `varchar(10)` | No | UNI | - | Date of class conducted |
| `hour` | `int(11)` | No | UNI | - | Period / hour number (1 to 7) |
| `diary` | `varchar(255)` | No | MUL | - | Detailed description of topic / syllabus unit taught |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `lesson_plan_id` | `int(11)` | Yes | MUL | NULL | Optional foreign key referencing lesson_plans.id |

> **Unique Keys**: (`sub_id`,`faculty_id`,`date`,`hour`)
> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `sub_id` &rarr; `subjects(id)`, `lesson_plan_id` &rarr; `lesson_plans(id)`

### 4.3 `attendance_delete_requests`
Formal faculty requests to revoke erroneously marked attendance periods, subject to HOD review and approval.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Request ID |
| `faculty_id` | `int(11)` | No | MUL | - | Foreign key referencing faculties.id |
| `subject_id` | `int(11)` | No | MUL | - | Foreign key referencing subjects.id |
| `date` | `date` | No | - | - | Attendance date to be deleted |
| `hour` | `int(11)` | No | - | - | Period / hour number to be deleted |
| `reason` | `text` | No | - | - | Faculty justification for the deletion request |
| `status` | `enum('Pending','Approved','Rejected')` | Yes | - | Pending | Request status: 'pending', 'approved', 'rejected' |
| `request_date` | `timestamp` | No | - | current_timestamp() | Timestamp when faculty submitted the request |
| `approval_date` | `timestamp` | Yes | - | NULL | Timestamp when HOD acted upon the request |

> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `subject_id` &rarr; `subjects(id)`

### 4.4 `attendance_rules`
Regulatory attendance evaluation rules (Satisfactory, Condonation, Shortage, Detained criteria thresholds).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Rule ID |
| `reg_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `criteria` | `enum('overall_percentage','subject_wise_percentage')` | Yes | - | overall_percentage | Attendance category ('Satisfactory', 'Condonation', 'Shortage', 'Detained') |
| `operator1` | `enum('>','>=','<','<=','==','!=')` | No | - | - | Primary comparison operator ('>=', '>', '<=', '<') |
| `value1` | `int(5)` | No | - | - | Primary percentage threshold (e.g., 75.00, 65.00) |
| `operator2` | `enum('<','<=','>','>=','==','!=')` | No | - | - | Secondary comparison operator (optional, e.g., '<', '<=') |
| `value2` | `int(5)` | No | - | - | Secondary percentage threshold (optional, e.g., 74.99) |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `reg_id` &rarr; `regulations(id)`

### 4.5 `student_permissions`
Exemption permissions granting condonation or on-duty attendance credit for institutional/medical reasons.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Permission record ID |
| `stu_id` | `int(11)` | No | MUL | - | Foreign key referencing students.id |
| `class_id` | `int(11)` | No | MUL | - | Foreign key referencing classes.id |
| `date` | `date` | No | MUL | - | Permission date |
| `hour` | `int(11)` | No | MUL | - | Permission period / hour |
| `permission_type` | `varchar(50)` | No | - | - | Type code (OD, MEDICAL, SPORTS, EVENT) |
| `reason` | `text` | No | - | - | Permission justification (Sports, Medical, NCC, NSS, Academic Conference) |
| `document_path` | `varchar(255)` | Yes | - | NULL | Uploaded supporting sanction letter |
| `granted_by_hod_id` | `int(11)` | No | - | - | Approving HOD user ID |
| `created_at` | `timestamp` | No | - | current_timestamp() | Submission timestamp |

### 4.6 `lesson_plans`
Course delivery schedule and lecture plan mapping each planned lecture to target Course Outcomes, Bloom levels, and pedagogical methodology.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Lesson plan entry ID |
| `sub_id` | `int(5)` | No | MUL | - | Foreign key referencing subjects.id |
| `unit_number` | `int(2)` | No | - | - | Syllabus Unit (1 to 5) |
| `lecture_number`| `int(3)` | No | - | - | Sequential lecture number (1..50+) |
| `planned_topic` | `varchar(255)`| No | - | - | Specific syllabus concept or topic planned |
| `co_id` | `int(11)` | No | MUL | - | Target Course Outcome referencing course_outcomes.id |
| `bloom_level` | `varchar(20)` | No | - | L3-Apply | Target Bloom taxonomy cognitive level |
| `pedagogy` | `varchar(50)` | No | - | Chalk & Talk | Pedagogy: Chalk & Talk, PPT/LCD, Coding Demo, Video, Flipped |
| `reference_material`| `varchar(255)`| Yes | - | NULL | Textbook / Chapter / Web reference citation |
| `planned_hours` | `int(2)` | No | - | 1 | Duration planned in lecture periods |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |

> **Unique Keys**: (`sub_id`,`lecture_number`)
> **Foreign Keys**: `sub_id` &rarr; `subjects(id)`, `co_id` &rarr; `course_outcomes(id)`

### 4.7 `course_completion_audits`
End-of-course syllabus reconciliation, unit milestone completion dates, pedagogical deviations, compensatory classes, and faculty/HOD audit sign-off (NBA Criterion 2.2).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Audit record ID |
| `sub_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `faculty_id` | `int(11)` | No | MUL | - | Foreign key referencing faculties.id |
| `total_planned_lectures` | `int(4)` | No | - | 0 | Total lectures planned in lesson plan |
| `total_actual_conducted` | `int(4)` | No | - | 0 | Total actual lectures conducted from diary |
| `total_compensatory_classes` | `int(4)` | No | - | 0 | Number of extra/compensatory classes conducted |
| `syllabus_completion_pct` | `decimal(5,2)` | No | - | 0.00 | Computed syllabus coverage percentage |
| `unit1_completion_date` | `date` | Yes | - | NULL | Actual completion date of Unit 1 |
| `unit2_completion_date` | `date` | Yes | - | NULL | Actual completion date of Unit 2 |
| `unit3_completion_date` | `date` | Yes | - | NULL | Actual completion date of Unit 3 |
| `unit4_completion_date` | `date` | Yes | - | NULL | Actual completion date of Unit 4 |
| `unit5_completion_date` | `date` | Yes | - | NULL | Actual completion date of Unit 5 |
| `deviations_reason` | `text` | Yes | - | NULL | Justification for syllabus pacing deviations or missed lectures |
| `compensatory_actions` | `text` | Yes | - | NULL | Remedial/compensatory actions taken to cover shortfall |
| `topics_beyond_syllabus` | `text` | Yes | - | NULL | Advanced industry or research topics covered beyond curriculum |
| `faculty_signoff_status` | `enum('DRAFT','SUBMITTED','APPROVED')` | No | - | DRAFT | Faculty submission lifecycle state |
| `faculty_signoff_at` | `datetime` | Yes | - | NULL | Timestamp of faculty compliance signoff |
| `hod_approval_status` | `enum('PENDING','APPROVED','REJECTED')` | No | - | PENDING | HOD departmental review status |
| `hod_approval_at` | `datetime` | Yes | - | NULL | Timestamp of HOD approval |
| `hod_remarks` | `text` | Yes | - | NULL | Department head review feedback or compliance notes |
| `reconciliation_mapping`| `longtext` | Yes | - | NULL | Serialized JSON mapping between diary entries and lesson plans |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |
| `hod_reviewed_by` | `int(11)` | Yes | - | NULL | HOD `users.id` who reviewed/approved the compliance audit |

> **Unique Keys**: (`sub_id`)
> **Foreign Keys**: `sub_id` &rarr; `subjects(id)`


## 5. Timetable Management
Hour schedules, class bell timings, effective date interval schedules, and CSV dump imports.

### 5.1 `class_timings`
Master timing templates configuring hour schedules and bell timings.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | UNI | AUTO_INCREMENT | Row identifier |
| `timing_id` | `int(11)` | No | MUL | - | Timing slot group identifier (e.g., 1, 2) |
| `hour` | `varchar(10)` | No | - | - | Period numeral (1 through 7) |
| `hour_desc` | `varchar(30)` | No | - | - | Descriptive label (e.g., I Period, II Period, Lunch Break) |
| `start_time` | `time` | No | - | - | Period start time (e.g., 09:00:00) |
| `end_time` | `time` | No | - | - | Period end time (e.g., 09:50:00) |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`id`)

### 5.2 `class_timing_schedule`
Effective date schedules binding classes to specific class timing templates over date intervals.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Schedule binding ID |
| `class_id` | `int(11)` | No | MUL | - | Foreign key referencing classes.id |
| `timing_id` | `int(11)` | No | MUL | - | Foreign key referencing class_timings.timing_id |
| `from_date` | `date` | No | MUL | - | Start date of effective timing schedule |
| `to_date` | `date` | Yes | MUL | NULL | End date of effective timing schedule |
| `created_by` | `int(11)` | Yes | - | NULL | User ID of administrator who scheduled this timing |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `class_id` &rarr; `classes(id)`, `timing_id` &rarr; `class_timings(timing_id)`

### 5.3 `class_timetables`
Weekly class timetable slot allocations managed via `Timetable` and `\College\TimetableWriter`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Timetable slot allocation ID |
| `class_id` | `int(11)` | No | MUL | - | Target class ID in `classes` table |
| `weekday` | `varchar(20)` | No | - | - | Day of the week (`Monday` through `Sunday`) |
| `Hour` | `int(11)` | No | - | - | Period slot ID referencing `class_timings.id` |
| `subject_code` | `varchar(100)` | No | - | - | Subject code or custom activity label |
| `subject_id` | `int(11)` | Yes | MUL | NULL | Foreign key referencing `subjects.id` (NULL for non-subject activities) |
| `building_name` | `varchar(100)` | No | - | - | Assigned campus building name |
| `class_hall_name` | `varchar(100)` | No | - | - | Assigned classroom or laboratory hall name |


## 6. Continuous Internal Assessment (CIA) & Marks
Assessment cycle headers, multi-component marks for UG, PG, Lab, and Project courses, attachment repositories, and staging tables.

### 6.1 `internal_assessments`
Continuous Internal Assessment (CIA) cycle headers configured per subject.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Assessment ID |
| `sub_id` | `int(11)` | No | MUL | - | Foreign key referencing subjects.id |
| `assessment_number` | `varchar(10)` | No | - | - | Assessment cycle number (1 = Mid-1, 2 = Mid-2) |
| `created_at` | `timestamp` | No | - | current_timestamp() | Timestamp of assessment configuration |

> **Foreign Keys**: `sub_id` &rarr; `subjects(id)`

### 6.2 `internal_assessment_marks`
UG Theory Continuous Internal Assessment (CIA) marks storage across subjective, objective, and assignment components.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle number (1 = Mid-1, 2 = Mid-2) |
| `subjective_marks` | `decimal(4,2)` | No | - | - | Subjective descriptive exam marks obtained |
| `objective_marks` | `decimal(4,2)` | No | - | - | Objective test / online quiz marks obtained |
| `assignment_marks` | `decimal(4,2)` | No | - | - | Assignment / term paper marks obtained |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`

### 6.3 `pg_internal_assessment_marks`
PG Theory Continuous Internal Assessment (CIA) marks storage.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle number (1 = Mid-1, 2 = Mid-2) |
| `marks` | `decimal(4,2)` | No | - | - | PG internal assessment marks obtained |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.4 `uglab_internal_assessment_marks`
UG Laboratory Continuous Internal Assessment marks storage.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle number (1 = Continuous Lab Evaluation) |
| `day_to_day_marks` | `decimal(4,2)` | No | - | - | Day-to-day lab performance and record marks |
| `internal_test_marks` | `decimal(4,2)` | No | - | - | End-semester internal laboratory examination marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.5 `ugproject_internal_assessment_marks`
UG Project Work Continuous Internal Assessment marks storage.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Project review cycle number (1 = Review 1, 2 = Review 2) |
| `component1_marks` | `decimal(4,2)` | No | - | - | Project presentation and defense marks |
| `component2_marks` | `decimal(4,2)` | No | - | - | Project dissertation, methodology, and progress marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.6 `cia_attachments`
Scanned answer scripts, question papers, and evaluation rubrics attached to CIA assessments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Attachment ID |
| `subject_id` | `int(11)` | No | - | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | - | - | Assessment cycle number (1 or 2) |
| `file_title` | `varchar(100)` | No | - | - | - |
| `file_path` | `varchar(255)` | No | - | - | Stored server file path in uploads directory |
| `updated_on` | `timestamp` | No | - | current_timestamp() | - |

### 6.7 `temp_internal_assessment_marks`
Staging table for bulk CSV/Excel import of UG Theory internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle (1 or 2) |
| `subjective_marks` | `varchar(5)` | Yes | - | NULL | Staged subjective marks |
| `objective_marks` | `varchar(5)` | Yes | - | NULL | Staged objective marks |
| `assignment_marks` | `varchar(5)` | Yes | - | NULL | Staged assignment marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.8 `temp_pg_internal_assessment_marks`
Staging table for bulk CSV/Excel import of PG Theory internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle (1 or 2) |
| `marks` | `varchar(5)` | Yes | - | NULL | Staged PG internal marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.9 `temp_uglab_internal_assessment_marks`
Staging table for bulk CSV/Excel import of UG Laboratory internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle (1 or 2) |
| `day_to_day_marks` | `varchar(5)` | Yes | - | NULL | Staged day-to-day evaluation marks |
| `internal_test_marks` | `varchar(5)` | Yes | - | NULL | Staged internal lab test marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.10 `temp_ugproject_internal_assessment_marks`
Staging table for bulk CSV/Excel import of UG Project internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment review cycle (1 or 2) |
| `component1_marks` | `varchar(5)` | Yes | - | NULL | Staged component 1 marks |
| `component2_marks` | `varchar(5)` | Yes | - | NULL | Staged component 2 marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.11 `temp_joiningdates`
Staging table for bulk student date of joining reconciliation imports.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `sno` | `int(11)` | No | - | - | - |
| `htno` | `varchar(15)` | No | UNI | - | Student Hall Ticket Number / Roll Number |
| `name` | `varchar(100)` | No | - | - | - |
| `doj` | `varchar(30)` | No | - | - | - |
| `gender` | `varchar(10)` | No | - | - | - |

> **Unique Keys**: (`htno`)

### 6.12 `external_assessment_marks`
Final Consolidated and Direct Semester End Examination (SEE) marks storage for direct attainment computation.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Record identifier |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `entry_mode` | `enum('DETAILED','DIRECT')` | No | - | DETAILED | Mode A (`DETAILED` Question 1 + Choice) or Mode B/C (`DIRECT` Ledger / Synced / Imported) |
| `external_marks`| `decimal(12,5)` | No | - | - | Final normalized/scaled scored marks out of `max_marks` |
| `max_marks` | `decimal(12,5)` | No | - | 70.00000 | Configured maximum external assessment marks (typically 70 or 60) |
| `q1_marks` | `decimal(5,2)` | Yes | - | NULL | Marks scored in compulsory Question 1 (Mode A) |
| `choice_marks` | `decimal(5,2)` | Yes | - | NULL | Total marks scored from resolved Either/Or choices (Mode A) |
| `part_a_marks` | `decimal(5,2)` | Yes | - | NULL | Composite split course Part A marks |
| `part_b_marks` | `decimal(5,2)` | Yes | - | NULL | Composite split course Part B marks |
| `submitted_by` | `int(11)` | Yes | - | NULL | User ID who submitted or synced the marks |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Submission and modification timestamp |
| `source_notification_id` | `int(11)` | Yes | - | NULL | Originating `exam_notifications.id` when synced via Mode C |
| `raw_marks` | `decimal(12,5)` | Yes | - | NULL | Unscaled gross external marks prior to normalization |
| `raw_max_marks` | `decimal(12,5)` | Yes | - | NULL | Unscaled gross maximum marks prior to normalization |
| `normalisation_json` | `longtext` | Yes | - | NULL | JSON provenance of scaling, choice selection, or grade-band estimation |
| `source_batch_id` | `bigint(20) unsigned` | Yes | - | NULL | Originating `attainment_import_batches.id` when imported via `attainmentimport.php` |
| `evidence_basis` | `varchar(60)` | No | - | LEGACY | Provenance tag (`LEGACY`, `DETAILED_QP`, `DIRECT_ENTRY`, `PUBLISHED_RESULT_SYNC:...`, `IMPORT_DIRECT`, `IMPORT_SCALED`, `GRADE_ESTIMATE:...`) |
| `is_current` | `tinyint(1)` | No | - | 1 | 1 = Active consolidated record, 0 = Invalidated pending reconsolidation after blueprint revision |

> **Unique Keys**: (`student_id`,`subject_id`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`


## 7. Outcome-Based Education (OBE) & Attainment
NBA compliance framework: Course Outcomes, Program Outcomes (POs/PSOs), Blooms taxonomy tagging, and question-level marks.

### 7.1 `course_outcomes`
Course Outcomes (CO1 through CO6) formulated per subject offering or tied to master curriculum subjects in Outcome-Based Education (OBE).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Course Outcome ID |
| `sub_id` | `int(11)` | Yes | MUL | NULL | Foreign key referencing subjects.id (NULL if curriculum master CO) |
| `co_number` | `int(11)` | No | - | - | CO sequence numeral (1 to 6) |
| `co_description` | `text` | No | - | - | Detailed learning outcome statement |
| `curr_sub_id` | `int(11)` | Yes | MUL | NULL | Foreign key referencing curriculum_subjects.id (for curriculum master COs) |
| `bloom_level` | `varchar(20)` | No | - | L3-Apply | Target Bloom taxonomy level (L1 to L6) |
| `target_threshold_percent` | `decimal(5,2)` | No | - | 60.00 | Attainment benchmark threshold percentage (typically 60%) |

> **Foreign Keys**: `sub_id` &rarr; `subjects(id)`, `curr_sub_id` &rarr; `curriculum_subjects(id)`

### 7.2 `po_pso`
Program Outcomes (PO1 to PO12) and Program Specific Outcomes (PSO1 to PSO4) defined per regulation, specialization, and optional batch cohort.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Outcome statement ID |
| `acad_year` | `varchar(20)` | No | MUL | - | Academic year string (e.g., 2024-2025) |
| `regulation` | `varchar(10)` | Yes | - | NULL | Regulation string (e.g., R20, R23) |
| `specid` | `int(11)` | Yes | MUL | NULL | Foreign key referencing specialization.id |
| `batch_id` | `int(11)` | Yes | MUL | NULL | Optional foreign key referencing `student_batches.id` |
| `po_pso` | `varchar(255)` | Yes | - | NULL | Outcome type classification ('PO' or 'PSO') |
| `orderid` | `int(11)` | Yes | - | NULL | Sequential ordering index (1 to 12 for PO, 1 to 4 for PSO) |
| `code` | `varchar(10)` | Yes | - | NULL | Outcome code (e.g., PO1, PO2, PSO1, PSO2) |
| `description` | `varchar(500)` | Yes | - | NULL | Full text of the graduate attribute or competency statement |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `reg_id` | `int(11)` | Yes | MUL | NULL | Foreign key referencing regulations.id |
| `target_score` | `decimal(3,2)` | No | - | 2.00 | Target attainment score out of 3.0 scale |
| `effective_from_year` | `int(4)` | No | MUL | 2020 | Starting academic cohort year of validity |

> **Unique Keys**: (`acad_year`,`regulation`,`specid`,`code`)
> **Foreign Keys**: `specid` &rarr; `specialization(id)`

### 7.3 `co_po_mapping`
Course Outcome to Program Outcome / PSO articulation matrix weighting correlations.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Articulation mapping ID |
| `co_id` | `int(11)` | No | MUL | - | Foreign key referencing course_outcomes.id |
| `po_id` | `int(11)` | No | MUL | - | Foreign key referencing po_pso.id |
| `weightage` | `decimal(5,2)` | Yes | - | 1.00 | Correlation weight (1, 2, or 3) |

> **Foreign Keys**: `co_id` &rarr; `course_outcomes(id)`, `po_id` &rarr; `po_pso(id)`

### 7.4 `blooms_levels`
Bloom's Revised Taxonomy cognitive domain levels for examination question tagging.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Bloom level ID |
| `blooms_level` | `varchar(255)` | No | - | - | Cognitive level code (L1, L2, L3, L4, L5, L6) |
| `blooms_label` | `varchar(50)` | No | - | - | Cognitive domain descriptor (Remember, Understand, Apply, Analyze, Evaluate, Create) |

### 7.5 `assessment_components`
Question paper sections or test partitions configured within an internal or external assessment cycle.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Component ID |
| `assessment_id` | `int(11)` | No | MUL | - | Foreign key referencing internal_assessments.id |
| `component_type` | `enum('Subjective','Objective','Assignment','Day-to-Day','Internal Exam','Subjective-1','Objective-1','Subjective-2','Objective-2','Activity','SEE-Theory','SEE-Split-A','SEE-Split-B','SEE-Lab')` | No | - | - | Component classification across CIA and SEE blueprints |
| `sequence_number` | `int(11)` | No | - | 1 | Component ordering sequence |

> **Foreign Keys**: `assessment_id` &rarr; `internal_assessments(id)`

### 7.6 `assessment_questions`
Individual test questions tagged with maximum marks and Bloom taxonomy levels.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Question ID |
| `component_id` | `int(11)` | No | UNI | - | Foreign key referencing assessment_components.id |
| `question_label` | `varchar(10)` | No | UNI | - | Question label (e.g., Q1(a), Q1(b), Q2, Q3) |
| `question_type` | `varchar(20)` | No | - | - | Question type (`Compulsory`, `Choice`, etc.) |
| `marks` | `decimal(12,5)` | No | - | - | Maximum marks allocated to this question |
| `blooms_level_id` | `int(11)` | No | MUL | - | Foreign key referencing blooms_levels.id |

> **Unique Keys**: (`component_id`,`question_label`)
> **Foreign Keys**: `component_id` &rarr; `assessment_components(id)`, `blooms_level_id` &rarr; `blooms_levels(id)`

### 7.7 `question_co_mapping`
Maps each assessment question directly to the target Course Outcome (CO) it evaluates.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mapping ID |
| `question_id` | `int(11)` | No | MUL | - | Foreign key referencing assessment_questions.id |
| `co_id` | `int(11)` | No | MUL | - | Foreign key referencing course_outcomes.id |

> **Foreign Keys**: `question_id` &rarr; `assessment_questions(id)`, `co_id` &rarr; `course_outcomes(id)`

### 7.8 `student_marks`
Question-level marks obtained by each student used for direct CO attainment calculations.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `stu_id` | `int(11)` | No | MUL | - | Foreign key referencing students.id |
| `question_id` | `int(11)` | No | MUL | - | Foreign key referencing assessment_questions.id |
| `marks_obtained` | `decimal(12,5)` | No | - | - | Numerical score awarded to the student on this question |

> **Foreign Keys**: `stu_id` &rarr; `students(id)`, `question_id` &rarr; `assessment_questions(id)`

### 7.9 `curriculum_course_outcomes`
Master catalog Course Outcomes (CO1–CO6) defined at the `curriculum_subjects` level and dual-synced to active course offerings (`course_outcomes`).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Master curriculum CO ID |
| `curr_sub_id` | `int(11)` | No | UNI | - | Foreign key referencing `curriculum_subjects.id` |
| `co_number` | `int(11)` | No | UNI | - | CO sequence number (1 to 6) |
| `co_description` | `text` | No | - | - | Master Course Outcome statement |
| `bloom_level` | `varchar(20)` | No | - | L3-Apply | Target Bloom cognitive level |
| `target_threshold_percent` | `decimal(5,2)` | No | - | 60.00 | Target attainment threshold percentage |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`curr_sub_id`, `co_number`)
> **Foreign Keys**: `curr_sub_id` &rarr; `curriculum_subjects(id)` ON DELETE CASCADE

### 7.10 `curriculum_co_po_mapping`
Master CO-to-PO/PSO correlation matrix linked to `curriculum_course_outcomes`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Master mapping ID |
| `curr_co_id` | `int(11)` | No | UNI | - | Foreign key referencing `curriculum_course_outcomes.id` |
| `po_id` | `int(11)` | No | UNI | - | Foreign key referencing `po_pso.id` |
| `weightage` | `decimal(5,2)` | No | - | 1.00 | Correlation weight (1, 2, or 3) |

> **Unique Keys**: (`curr_co_id`, `po_id`)
> **Foreign Keys**: `curr_co_id` &rarr; `curriculum_course_outcomes(id)` ON DELETE CASCADE, `po_id` &rarr; `po_pso(id)` ON DELETE CASCADE

### 7.11 `attainment_policies`
Regulation- and subject-type-scoped attainment profiles (maxima, grade bands, choice groups, estimation policy) managed via `AttainmentPolicyService`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(10) unsigned` | No | PRI | AUTO_INCREMENT | Policy profile ID |
| `regulation_id` | `int(11)` | No | UNI | - | Foreign key referencing `regulations.id` |
| `subject_type` | `varchar(40)` | No | UNI | - | Subject type key (`all`, `Theory`, `Lab`, `Project`, etc.) |
| `config_json` | `longtext` | No | - | - | JSON policy configuration (`max_marks`, `grade_bands`, `choice_groups`, `grade_estimate_policy`) |
| `version` | `int(10) unsigned` | No | - | 1 | Monotonically incrementing version counter |
| `updated_by` | `int(11)` | No | - | - | SuperAdmin `users.id` who saved the profile |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`regulation_id`, `subject_type`)

### 7.12 `subject_attainment_overrides`
Course-offering-specific attainment policy overrides taking precedence over `attainment_policies`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `subject_id` | `int(11)` | No | PRI | - | Primary key referencing `subjects.id` |
| `config_json` | `longtext` | No | - | - | JSON override configuration |
| `version` | `int(10) unsigned` | No | - | 1 | Override version counter |
| `updated_by` | `int(11)` | No | - | - | User ID who configured the override |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

### 7.13 `attainment_import_batches`
Staged and committed external CSV evidence imports (`attainmentimport.php`) managed by `AttainmentImportService`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | No | PRI | AUTO_INCREMENT | Import batch ID |
| `subject_id` | `int(11)` | No | MUL | - | Target `subjects.id` |
| `owner_user_id` | `int(11)` | No | MUL | - | Authenticated `users.id` who uploaded the CSV |
| `source_hash` | `char(64)` | No | - | - | SHA-256 hash of raw uploaded CSV content |
| `policy_hash` | `char(64)` | No | - | - | SHA-256 hash of resolved attainment policy at preview time |
| `payload_json` | `longtext` | No | - | - | Validated preview rows, summary counts, and normalization metadata |
| `state` | `enum('DRAFT','COMMITTED','CANCELLED')` | No | MUL | DRAFT | Import batch lifecycle state |
| `committed_at` | `datetime` | Yes | - | NULL | Timestamp when batch was committed to marks tables |
| `created_at` | `timestamp` | No | - | current_timestamp() | Preview creation timestamp |


## 8. Physical Infrastructure, Surveys & Student Feedback
Campus buildings, examination halls, subject questionnaires, Course Outcome indirect surveys, Course End Surveys (CES), and Faculty Appraisals.

### 8.1 `buildings`
Physical campus buildings and academic blocks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Building ID |
| `building_name` | `varchar(100)` | No | UNI | - | Official building or academic block name |
| `status` | `tinyint(4)` | Yes | - | 1 | 1 = Active / Usable, 0 = Inactive |
| `created_at` | `timestamp` | No | - | current_timestamp() | - |
| `updated_at` | `timestamp` | No | - | current_timestamp() | - |

> **Unique Keys**: (`building_name`)

### 8.2 `halls`
Classrooms, seminar halls, and drawing halls located within buildings.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Hall ID |
| `building_id` | `int(11)` | No | UNI | - | Foreign key referencing buildings.id |
| `hall_name` | `varchar(100)` | No | UNI | - | Classroom / Hall room number or designation |
| `status` | `tinyint(4)` | Yes | - | 1 | 1 = Active / Usable, 0 = Under Maintenance / Inactive |
| `created_at` | `timestamp` | No | - | current_timestamp() | - |
| `updated_at` | `timestamp` | No | - | current_timestamp() | - |

> **Unique Keys**: (`building_id`,`hall_name`)
> **Foreign Keys**: `building_id` &rarr; `buildings(id)`

### 8.3 `subject_questionnaire_questions`
Subject feedback survey questionnaire items configured by administrators/faculty.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Question ID |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `question_number` | `int(11)` | No | UNI | - | Display ordering index (1, 2, 3...) |
| `question_text` | `text` | No | - | - | Survey question statement |
| `created_by_user_id` | `int(11)` | No | MUL | - | Foreign key referencing users.id |
| `created_by_role` | `enum('faculty','hod','admin')` | No | - | - | Role string of authoring user |
| `created_at` | `timestamp` | No | - | current_timestamp() | Timestamp of question creation |

> **Unique Keys**: (`subject_id`,`question_number`)
> **Foreign Keys**: `subject_id` &rarr; `subjects(id)`, `created_by_user_id` &rarr; `users(id)`

### 8.4 `student_questionnaire_responses`
Student responses to subject survey questions.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Response ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `question_id` | `int(11)` | No | UNI | - | Foreign key referencing subject_questionnaire_questions.id |
| `rating` | `int(11)` | No | - | - | Rating score awarded (1 to 5 Likert scale) |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Timestamp of survey submission |

> **Unique Keys**: (`student_id`,`subject_id`,`question_id`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`, `question_id` &rarr; `subject_questionnaire_questions(id)`

### 8.5 `student_co_feedback`
Student indirect attainment survey ratings evaluated per Course Outcome (CO).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Feedback ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `class_id` | `int(11)` | No | MUL | - | Foreign key referencing classes.id |
| `co_id` | `int(11)` | No | UNI | - | Foreign key referencing course_outcomes.id |
| `rating` | `int(11)` | No | - | - | Student satisfaction rating (1 to 5 Likert scale) |
| `feedback_date` | `timestamp` | No | - | current_timestamp() | Timestamp of indirect CO feedback submission |

> **Unique Keys**: (`student_id`,`subject_id`,`co_id`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`, `class_id` &rarr; `classes(id)`, `co_id` &rarr; `course_outcomes(id)`

### 8.6 `student_ces_feedback`
Course End Survey (CES) responses evaluating curriculum, pedagogy, evaluation, learning resources, and outcome mastery across 16 questions and qualitative feedback.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Survey response ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `ces_q1` | `tinyint(2)` | No | - | - | Domain 1: Syllabus coverage and clarity (1-5 scale) |
| `ces_q2` | `tinyint(2)` | No | - | - | Domain 1: Course objectives relevance (1-5 scale) |
| `ces_q3` | `tinyint(2)` | No | - | - | Domain 1: Balance between theory and applications (1-5 scale) |
| `ces_q4` | `tinyint(2)` | No | - | - | Domain 1: Modernity and industry relevance of content (1-5 scale) |
| `ces_q5` | `tinyint(2)` | No | - | - | Domain 2: Instructor teaching effectiveness and pace (1-5 scale) |
| `ces_q6` | `tinyint(2)` | No | - | - | Domain 2: Encouragement of critical thinking and interaction (1-5 scale) |
| `ces_q7` | `tinyint(2)` | No | - | - | Domain 2: Effective use of ICT / pedagogical tools (1-5 scale) |
| `ces_q8` | `tinyint(2)` | No | - | - | Domain 2: Timely doubt clarification and mentoring (1-5 scale) |
| `ces_q9` | `tinyint(2)` | No | - | - | Domain 3: Fairness and transparency of internal evaluation (1-5 scale) |
| `ces_q10` | `tinyint(2)` | No | - | - | Domain 3: Quality and cognitive depth of test questions (1-5 scale) |
| `ces_q11` | `tinyint(2)` | No | - | - | Domain 3: Constructive feedback provided on assessments (1-5 scale) |
| `ces_q12` | `tinyint(2)` | No | - | - | Domain 4: Availability of textbooks, reference notes, and LMS material (1-5 scale) |
| `ces_q13` | `tinyint(2)` | No | - | - | Domain 4: Adequacy of lab facilities, software, or computing resources (1-5 scale) |
| `ces_q14` | `tinyint(2)` | No | - | - | Domain 4: Academic support for slow and advanced learners (1-5 scale) |
| `ces_q15` | `tinyint(2)` | No | - | - | Domain 5: Enhancement of knowledge, technical skills, and problem solving (1-5 scale) |
| `ces_q16` | `tinyint(2)` | No | - | - | Domain 5: Confidence in applying concepts to practical/professional scenarios (1-5 scale) |
| `useful_aspects` | `text` | Yes | - | NULL | Part-C Qualitative: Most useful aspects / topics of the course |
| `improvement_topics` | `text` | Yes | - | NULL | Part-C Qualitative: Topics requiring additional depth or pedagogical improvement |
| `suggestions` | `text` | Yes | - | NULL | Part-C Qualitative: General constructive suggestions for course improvement |
| `is_anonymous` | `tinyint(1)` | No | - | 0 | Anonymity flag: 0 = Identified, 1 = Anonymous submission |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Timestamp of CES survey submission |

> **Unique Keys**: (`student_id`,`subject_id`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`

### 8.7 `student_faculty_feedback`
Comprehensive student appraisal of teaching faculty across 19 instructional and pedagogical criteria plus qualitative comments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Faculty appraisal ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `faculty_id` | `int(11)` | No | UNI | - | Foreign key referencing faculties.id |
| `fac_q1` | `tinyint(2)` | No | - | - | Criterion 1: Subject knowledge and depth of explanation (1-5 scale) |
| `fac_q2` | `tinyint(2)` | No | - | - | Criterion 2: Clarity of communication and lecture delivery (1-5 scale) |
| `fac_q3` | `tinyint(2)` | No | - | - | Criterion 3: Punctuality and regularity in conducting classes (1-5 scale) |
| `fac_q4` | `tinyint(2)` | No | - | - | Criterion 4: Syllabus completion according to academic schedule (1-5 scale) |
| `fac_q5` | `tinyint(2)` | No | - | - | Criterion 5: Blackboard / presentation organization and legibility (1-5 scale) |
| `fac_q6` | `tinyint(2)` | No | - | - | Criterion 6: Encouragement of student questions and discussions (1-5 scale) |
| `fac_q7` | `tinyint(2)` | No | - | - | Criterion 7: Ability to illustrate concepts with real-world examples (1-5 scale) |
| `fac_q8` | `tinyint(2)` | No | - | - | Criterion 8: Fairness, impartiality, and objectivity in internal grading (1-5 scale) |
| `fac_q9` | `tinyint(2)` | No | - | - | Criterion 9: Accessibility and willingness to guide outside regular hours (1-5 scale) |
| `fac_q10` | `tinyint(2)` | No | - | - | Criterion 10: Effective classroom control and discipline (1-5 scale) |
| `fac_q11` | `tinyint(2)` | No | - | - | Criterion 11: Speed of lecture and pacing adapted to student grasp (1-5 scale) |
| `fac_q12` | `tinyint(2)` | No | - | - | Criterion 12: Timely return of evaluated scripts and assignments (1-5 scale) |
| `fac_q13` | `tinyint(2)` | No | - | - | Criterion 13: Provision of supplementary learning resources and references (1-5 scale) |
| `fac_q14` | `tinyint(2)` | No | - | - | Criterion 14: Use of modern educational technology and ICT tools (1-5 scale) |
| `fac_q15` | `tinyint(2)` | No | - | - | Criterion 15: Motivation provided towards research, projects, and careers (1-5 scale) |
| `fac_q16` | `tinyint(2)` | No | - | - | Criterion 16: Respectful and approachable conduct towards students (1-5 scale) |
| `fac_q17` | `tinyint(2)` | No | - | - | Criterion 17: Attention and assistance given to struggling students (1-5 scale) |
| `fac_q18` | `tinyint(2)` | No | - | - | Criterion 18: Inspiring enthusiasm and curiosity in the subject (1-5 scale) |
| `fac_q19` | `tinyint(2)` | No | - | - | Criterion 19: Overall evaluation and instructor effectiveness (1-5 scale) |
| `faculty_strengths` | `text` | Yes | - | NULL | Qualitative: Key strengths and commendable qualities of the faculty |
| `improvement_areas` | `text` | Yes | - | NULL | Qualitative: Specific areas suggested for faculty teaching improvement |
| `additional_comments` | `text` | Yes | - | NULL | Qualitative: Additional remarks, observations, or commendations |
| `is_anonymous` | `tinyint(1)` | No | - | 1 | Anonymity flag: Default 1 (Strict student anonymity preserved for faculty appraisal) |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Timestamp of faculty feedback submission |

> **Unique Keys**: (`student_id`,`subject_id`,`faculty_id`)
> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`


## 9. Autonomous Academic Settings & Parameters
Centralized autonomous regulatory policy engine, parameter versioning, thresholds, and audit logging for institutional governance.

### 9.1 `academic_settings`
Centralized repository of academic regulations and policy configuration parameters (CIA/SEE weightages, condonation/detention thresholds, attainment benchmarks, promotion, honors, minors, MOOCs, project dissertation rules). Dual-scoped by `reg_id` (foreign key) and `regulation_code`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Setting parameter ID |
| `reg_id` | `int(11)` | Yes | - | NULL | Foreign key referencing regulations.id for regulation-level scoping |
| `regulation_code` | `varchar(10)` | No | MUL | - | Regulation identifier (e.g., R20, R23, R25, R26) |
| `category` | `enum('CIA','SEE','ATTENDANCE','ATTAINMENT','GRADING','GENERAL','PROMOTION','HONORS','MINOR','MOOCS','PROJECT','ACTIVITIES')` | No | MUL | - | Regulatory policy category |
| `setting_key` | `varchar(64)` | No | - | - | Parameter configuration key (e.g., `see_min_pass_percentage`, `dissertation_total_marks`) |
| `setting_value` | `text` | No | - | - | Serialized setting value (scalar or JSON payload) |
| `data_type` | `enum('STRING','INT','FLOAT','BOOL','JSON')` | No | - | STRING | Type specification for automatic application casting via SettingsService |
| `description` | `varchar(255)` | Yes | - | NULL | Academic regulation context and autonomous policy clause |
| `is_editable` | `tinyint(1)` | No | - | 1 | 1 = Configurable via SuperAdmin UI, 0 = System locked |
| `updated_by` | `int(11)` | Yes | - | NULL | User ID of administrator who performed update |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

> **Unique Keys**: (`regulation_code`,`setting_key`)
> **Foreign Keys**: `reg_id` &rarr; `regulations(id)`

### 9.2 `academic_settings_audit`
Complete immutable audit trail tracking parameter mutations in academic rules, prior values, new values, modifier user IDs, and client IP addresses.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Audit log record ID |
| `setting_id` | `int(11)` | No | MUL | - | Foreign key referencing academic_settings.id |
| `regulation_code` | `varchar(10)` | No | MUL | - | Regulation code of the setting at time of mutation |
| `setting_key` | `varchar(64)` | No | - | - | Modified setting key |
| `old_value` | `text` | Yes | - | NULL | Value prior to modification |
| `new_value` | `text` | No | - | - | Value following modification |
| `changed_by` | `int(11)` | Yes | MUL | NULL | User ID who performed the update |
| `changed_at` | `timestamp` | No | - | current_timestamp() | Mutation timestamp |
| `ip_address` | `varchar(45)` | Yes | - | NULL | Client IP address of requesting user |

> **Foreign Keys**: `setting_id` &rarr; `academic_settings(id)`

### 9.3 `system_feature_modules`
Centralized feature toggle configuration engine enabling granular per-role visibility and global activation across **28 discrete system modules**.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Unique module record ID |
| `module_key` | `varchar(50)` | No | UNI | - | Unique system module identifier (e.g., `MOD_ATTENDANCE`, `MOD_CIA_MARKS`, `MOD_AI_ASSISTANCE`) |
| `module_name` | `varchar(100)` | No | - | - | Human-readable title of the module |
| `category` | `varchar(50)` | No | MUL | - | Functional classification (`ATTENDANCE`, `CIA`, `OBE`, `ACCREDITATION`, `EXAMINATION`, `GENERAL`, etc.) |
| `description` | `text` | Yes | - | NULL | Detailed architectural purpose and impact description |
| `is_enabled_globally` | `tinyint(1)` | No | - | 1 | Master kill switch (1 = Enabled institution-wide, 0 = Disabled everywhere) |
| `faculty_visibility` | `enum('VISIBLE','HIDDEN','READONLY')` | No | - | VISIBLE | Faculty interface access rule |
| `student_visibility` | `enum('VISIBLE','HIDDEN')` | No | - | VISIBLE | Student portal interface access rule |
| `hod_visibility` | `enum('VISIBLE','HIDDEN','READONLY')` | No | - | VISIBLE | Head of Department interface access rule |
| `sort_order` | `int(11)` | No | - | 0 | Display sequence on the administrative feature management page |
| `updated_by` | `int(11)` | Yes | - | NULL | User ID of the SuperAdmin who performed the update |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp (auto-updates) |

> **Unique Keys**: (`module_key`)

### 9.4 `academic_policy_versions`
Immutable ledger of regulation-level academic policy snapshots recorded by `\College\AssessmentPolicy::recordVersion()`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | No | PRI | AUTO_INCREMENT | Policy version snapshot ID |
| `regulation_id` | `int(11)` | No | UNI | - | Foreign key referencing `regulations.id` |
| `policy_hash` | `char(64)` | No | UNI | - | SHA-256 hash of canonicalized regulation settings |
| `settings_json` | `longtext` | No | - | - | Complete JSON snapshot of regulation settings |
| `recorded_by` | `int(11)` | No | - | - | Authenticated `users.id` who triggered the snapshot |
| `created_at` | `timestamp` | No | - | current_timestamp() | Snapshot timestamp |

> **Unique Keys**: (`regulation_id`, `policy_hash`)


## 10. Institutional Governance: Cohort Batches, Results Publication & Student Dossier
Autonomous academic governance architecture anchoring Vision, Mission, PEOs, POs/PSOs, and regulations to permanent student cohorts (e.g. 2025–2029), automating end-semester examination results ingestion, and managing student profiles, digital vaults, physical custodial certificates, and statutory certificate issuance.

### 10.1 `student_batches`
Master permanent cohort definition binding students, regulations, and multi-year OBE attainment (see also Section 2.5).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Cohort Batch ID |
| `program_id` | `int(11)` | No | MUL | - | Degree program (B.Tech, M.Tech, MCA, etc.) |
| `regulation_id` | `int(11)` | No | MUL | - | Governing regulation governing this batch |
| `batch_name` | `varchar(50)` | No | - | - | Institutional batch name (e.g., `2023-2027`) |
| `admission_year` | `int(4)` | No | - | - | Academic entry year (e.g., 2023) |
| `graduation_year` | `int(4)` | No | - | - | Scheduled graduation year (e.g., 2027) |
| `is_active` | `tinyint(1)` | No | - | 1 | 1 = Currently enrolled, 0 = Graduated / Inactive |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |

### 10.2 `vision_mission`
Stores master institutional and departmental Vision & Mission statements. Inherited automatically by cohorts admitted in or after `effective_from_year`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Record ID |
| `dept_id` | `int(11)` | Yes | MUL | NULL | Department ID (NULL = Institutional level) |
| `effective_from_year` | `int(4)` | No | - | 2020 | Effective from entry year |
| `vision_statement` | `text` | No | - | - | Vision statement text |
| `mission_statements` | `text` | No | - | - | JSON object of indexed mission statements `{"M1": "...", "M2": "..."}` |
| `is_active` | `tinyint(1)` | No | - | 1 | 1 = Active, 0 = Superseded |
| `created_by` | `int(11)` | Yes | - | NULL | User ID who drafted/entered statement |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

### 10.3 `peos`
Master Program Educational Objectives defined at the department/program level. Inherited automatically by cohort batches.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | PEO Record ID |
| `dept_id` | `int(11)` | No | MUL | - | Academic department ID |
| `program_id` | `int(5)` | Yes | MUL | NULL | Optional specific degree program ID |
| `peo_code` | `varchar(20)` | No | - | - | PEO identifier (e.g. `PEO1`, `PEO2`) |
| `peo_title` | `varchar(255)` | Yes | - | NULL | PEO headline / short title |
| `peo_description` | `text` | No | - | - | Comprehensive PEO narrative |
| `target_score` | `decimal(3,2)` | No | - | 2.00 | Master target attainment score on 3-point rubric |
| `effective_from_year` | `int(4)` | No | - | 2020 | Effective entry year for curriculum versioning |
| `sort_order` | `int(11)` | No | - | 1 | Sequence order in reports |
| `is_active` | `tinyint(1)` | No | - | 1 | 1 = Active, 0 = Superseded |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

### 10.4 `peo_mission_mapping`
Cross-mapping correlation matrix between master Program Educational Objectives (PEOs) and Mission statements.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Matrix Cell ID |
| `peo_id` | `int(11)` | No | MUL | - | Foreign key to `peos(id)` |
| `mission_key` | `varchar(20)` | No | - | - | Mission key (e.g. `M1`, `M2`) |
| `correlation_level` | `tinyint(1)` | No | - | 0 | 0 = None, 1 = Low, 2 = Medium, 3 = High |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

### 10.5 `po_peo_mapping`
Cross-mapping correlation matrix between master Program Outcomes / Program Specific Outcomes (`po_pso`) and master PEOs (`peos`).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Matrix Cell ID |
| `po_pso_id` | `int(11)` | No | MUL | - | Foreign key to `po_pso(id)` |
| `peo_id` | `int(11)` | No | MUL | - | Foreign key to `peos(id)` |
| `correlation_level` | `tinyint(1)` | No | - | 0 | 0 = None, 1 = Low, 2 = Medium, 3 = High |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

### 10.5.1 `batch_peo_targets`
Optional cohort-specific target score overrides for specific PEOs. Allows a cohort to set a higher/custom attainment threshold without altering master definitions.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Override Record ID |
| `batch_id` | `int(11)` | No | MUL | - | Foreign key to `student_batches(id)` |
| `peo_id` | `int(11)` | No | MUL | - | Foreign key to `peos(id)` |
| `target_score` | `decimal(3,2)` | No | - | 2.00 | Cohort-specific target score |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

### 10.6 `exam_notifications`
Examination session release notifications managing bulk result ingestion, review, and publication.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Notification ID |
| `notification_code` | `varchar(50)` | No | UNI | - | Unique notification code (e.g. `EXAM-2026-BTECH-R20-4-2`) |
| `title` | `varchar(255)` | No | - | - | Examination title |
| `academic_year` | `varchar(20)` | No | - | - | Academic year session (e.g. `2025-2026`) |
| `program_id` | `int(11)` | No | MUL | - | Target degree program referencing `programs.id` |
| `regulation_id` | `int(11)` | No | MUL | - | Target academic regulation referencing `regulations.id` |
| `yearsem` | `varchar(20)` | No | - | - | Semester identifier (e.g., `IV-II`, `III-I`) |
| `month_year` | `varchar(30)` | No | - | - | Examination month and year (e.g., `May/June 2026`) |
| `release_date` | `date` | No | - | - | Date of result announcement |
| `is_published` | `tinyint(1)` | No | - | 1 | 1 = Visible to students, 0 = Staged in draft/review |
| `created_by` | `int(11)` | Yes | - | NULL | Administrator `users.id` who created the notification |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `workflow_status` | `enum('DRAFT','REVIEWED','PUBLISHED')` | No | - | DRAFT | Multi-step publication workflow state |
| `reviewed_by` | `int(11)` | Yes | - | NULL | Reviewer `users.id` who verified imported results |
| `reviewed_at` | `datetime` | Yes | - | NULL | Timestamp when notification was marked `REVIEWED` |
| `exam_type` | `enum('REGULAR','SUPPLEMENTARY','REVALUATION')` | No | - | REGULAR | Examination session classification |

### 10.7 `exam_results`
Granular subject-level student examination grades, marks, fractional grade points, credits earned, and pass/fail statuses.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Result Record ID |
| `notification_id` | `int(11)` | No | MUL | - | Foreign key to `exam_notifications(id)` |
| `htno` | `varchar(30)` | No | MUL | - | Hall Ticket / Roll Number |
| `student_id` | `int(11)` | Yes | MUL | NULL | Enrolled student ID if matched (`students.id`) |
| `subject_code` | `varchar(30)` | No | MUL | - | Course / Subject Code |
| `subject_name` | `varchar(150)` | Yes | - | NULL | Course Title |
| `subject_id` | `int(11)` | Yes | MUL | NULL | Foreign key to `subjects(id)` if matched |
| `internal_marks` | `decimal(5,2)` | Yes | - | NULL | CIA Continuous assessment score |
| `external_marks` | `decimal(5,2)` | Yes | - | NULL | SEE End-semester examination score |
| `total_marks` | `decimal(5,2)` | Yes | - | NULL | Consolidated total marks |
| `grade_letter` | `varchar(5)` | Yes | - | NULL | Letter grade (`O`, `S`, `A+`, `A`, `B`, `C`, `D`, `E`, `F`, `Ab`, `MP`) |
| `grade_points` | `decimal(13,3)` | Yes | - | NULL | Fractional or integer grade points earned |
| `credits_registered`| `decimal(4,2)` | Yes | - | 0.00 | Subject credit allocation |
| `credits_earned` | `decimal(4,2)` | Yes | - | 0.00 | Credits awarded upon passing |
| `result_status` | `enum('PASS','FAIL','ABSENT','WITHHELD','MALPRACTICE')` | No | - | PASS | Final examination result outcome |
| `imported_at` | `timestamp` | No | - | current_timestamp() | CSV import timestamp |

### 10.8 `student_profiles`
Master biographical, parental, entrance, and residential dossier for enrolled students.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Profile ID |
| `roll_no` | `varchar(30)` | No | UNI | - | Foreign key to `users(username)` |
| `dob` | `date` | Yes | - | NULL | Date of birth |
| `gender` | `enum('MALE','FEMALE','OTHER')` | Yes | - | NULL | Gender |
| `blood_group` | `varchar(10)` | Yes | - | NULL | Blood group |
| `aadhar_number` | `varchar(20)` | Yes | - | NULL | 12-digit UIDAI Aadhaar number |
| `father_name` | `varchar(150)` | Yes | - | NULL | Father / Guardian full name |
| `mother_name` | `varchar(150)` | Yes | - | NULL | Mother full name |
| `parent_phone` | `varchar(20)` | Yes | - | NULL | Emergency parental phone |
| `parent_email` | `varchar(150)` | Yes | - | NULL | Parental communication email |
| `admission_category`| `varchar(50)` | Yes | - | NULL | Quota (CONVENOR, ECET, SPOT, NRI, GATE) |
| `rank_obtained` | `int(11)` | Yes | - | NULL | Entrance exam state rank |
| `hall_ticket_admission`| `varchar(50)` | Yes | - | NULL | Entrance hall ticket number |
| `admission_date` | `date` | Yes | - | NULL | Date of admission into the college |
| `permanent_address`| `text` | Yes | - | NULL | Permanent domicile address |
| `current_address` | `text` | Yes | - | NULL | Present communication address |
| `profile_photo_path`| `varchar(255)` | Yes | - | NULL | Student portrait photograph path |
| `is_verified` | `tinyint(1)` | Yes | - | 0 | 1 = Verified by Academic Section |
| `verified_by` | `int(11)` | Yes | - | NULL | Staff user ID who verified profile |
| `verified_at` | `datetime` | Yes | - | NULL | Timestamp of verification |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

### 10.9 `student_documents`
Digital document vault storing verified scans of educational certificates and credentials in private storage (`COLLEGE_DOCUMENT_ROOT`) with soft-delete retention.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Document ID |
| `roll_no` | `varchar(30)` | No | MUL | - | Foreign key to `users(username)` |
| `doc_type` | `varchar(50)` | No | MUL | - | Type (SSC_MEMO, INTER_DIPLOMA_MEMO, ALLOTMENT_ORDER, etc.) |
| `doc_title` | `varchar(150)` | No | - | - | Descriptive document label |
| `file_name` | `varchar(255)` | No | - | - | Original uploaded file name |
| `file_path` | `varchar(255)` | No | - | - | Private storage URI (`private://student_docs/...`) or retained legacy path |
| `file_size` | `int(11)` | Yes | - | 0 | Size in bytes |
| `mime_type` | `varchar(100)` | Yes | - | application/pdf | MIME format (`application/pdf`, `image/png`, `image/jpeg`) |
| `is_original_submitted`| `tinyint(1)` | Yes | - | 0 | 1 = Physical original deposited in college safe custody |
| `custody_status` | `enum('IN_CUSTODY','TEMPORARILY_ISSUED','RETURNED_PERMANENT','NOT_SUBMITTED')` | Yes | - | NOT_SUBMITTED | Physical document custody state |
| `custody_location_ref`| `varchar(100)` | Yes | - | NULL | Storage rack / shelf reference identifier |
| `is_verified` | `tinyint(1)` | Yes | - | 0 | 1 = Inspected and verified by Academic Section |
| `verified_by` | `int(11)` | Yes | - | NULL | Staff user ID who verified document |
| `verified_at` | `datetime` | Yes | - | NULL | Timestamp of verification |
| `remarks` | `text` | Yes | - | NULL | Verification or custody notes |
| `uploaded_at` | `timestamp` | No | - | current_timestamp() | Upload timestamp |
| `deleted_at` | `datetime` | Yes | - | NULL | Soft-deletion timestamp (retains record for audit) |
| `deleted_by` | `int(11)` | Yes | - | NULL | `users.id` who soft-deleted the document |

### 10.10 `student_custodial_records`
Master physical custody ledger tracking the movement and custody of original certificates deposited during admission.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Custody Record ID |
| `roll_no` | `varchar(30)` | No | MUL | - | Foreign key to `users(username)` |
| `document_name` | `varchar(150)` | No | - | - | Name of original document |
| `certificate_serial_no`| `varchar(100)` | Yes | - | NULL | Serial number printed on hardcopy |
| `original_received` | `tinyint(1)` | No | - | 1 | 1 = Original certificate in custody |
| `received_date` | `date` | No | - | - | Date original document deposited |
| `received_by` | `int(11)` | Yes | - | NULL | Staff user ID who received certificate |
| `status` | `enum('IN_CUSTODY','TEMPORARILY_RETURNED','PERMANENTLY_RETURNED')` | No | MUL | IN_CUSTODY | Physical custody status |
| `purpose_of_withdrawal`| `varchar(255)` | Yes | - | NULL | Purpose for temporary loan (Passport, Visa, etc.) |
| `issued_date` | `date` | Yes | - | NULL | Date temporarily issued to student |
| `expected_return_date`| `date` | Yes | - | NULL | Promised date to return to safe custody |
| `actual_returned_date`| `date` | Yes | - | NULL | Date returned back to custody |
| `ack_signature_token` | `varchar(100)` | Yes | - | NULL | Acknowledgment receipt token |
| `remarks` | `text` | Yes | - | NULL | Custody movement notes and rack locations |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

### 10.11 `student_certificate_requests`
Statutory certificate application and generation engine (Custodial, Bonafide, Study & Conduct, TC, No Dues).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Application ID |
| `certificate_no` | `varchar(60)` | Yes | UNI | NULL | Unique institutional certificate tracking number (generated via `certificate_sequences`) |
| `roll_no` | `varchar(30)` | No | MUL | - | Foreign key to `users(username)` |
| `cert_type` | `enum('CUSTODIAL','BONAFIDE','STUDY_CONDUCT','TRANSFER_CERTIFICATE','NO_DUES')` | No | - | - | Certificate classification |
| `purpose` | `varchar(255)` | No | - | - | Reason / Addressed authority |
| `status` | `enum('REQUESTED','APPROVED','REJECTED','GENERATED','ISSUED')` | No | MUL | REQUESTED | Workflow processing status |
| `requested_date`| `date` | No | - | - | Date student applied |
| `approved_by` | `int(11)` | Yes | - | NULL | Staff user ID who approved |
| `approved_at` | `datetime` | Yes | - | NULL | Timestamp of approval |
| `issued_at` | `datetime` | Yes | - | NULL | Timestamp when handed over / generated |
| `custom_fields_json`| `longtext` | Yes | - | NULL | JSON payload with cert-specific parameters |
| `remarks` | `text` | Yes | - | NULL | Verification notes |
| `created_at` | `timestamp` | No | - | current_timestamp() | Request creation timestamp |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `verification_token` | `char(64)` | Yes | UNI | NULL | Opaque 64-character CSPRNG verification token (`uq_certificate_verification_token`) |

### 10.12 `certificate_sequences`
Concurrency-safe atomic sequence counter for statutory certificate numbers (`\College\CertificateNumber::next()`).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `prefix` | `varchar(80)` | No | PRI | - | Certificate prefix string (e.g., `JNTUACEA/BON/2026/`) |
| `last_number` | `int(10) unsigned` | No | - | 0 | Last allocated sequential integer suffix |

---

## 11. Accreditation: Laboratory Evaluation Rubrics & CQI Action Tracking
Tables governing dual-mode continuous laboratory assessment, criteria-level evaluation breakdowns, and closed-loop continuous quality improvement (CQI) remedial action plans for NBA Tier-I Autonomous accreditation.

### 11.1 `lab_rubric_templates`
Master repository of standardized continuous lab assessment rubric templates (e.g. 15-mark tripartite standard: Conduction 5M, Observation 5M, Viva 5M).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Template unique identifier |
| `template_name` | `varchar(100)` | No | - | - | Descriptive name (e.g. 'Standard 15M Lab Rubric') |
| `department_id` | `int(11)` | Yes | - | NULL | NULL = Institutional global default, or foreign key to `departments.id` |
| `total_max_marks` | `decimal(4,2)` | No | - | 15.00 | Total marks scale (standard: 15.00) |
| `is_default` | `tinyint(1)` | No | - | 0 | 1 = Default template for practical laboratory courses |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |

### 11.2 `lab_rubric_criteria`
Individual assessment criteria associated with a rubric template.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Criterion unique identifier |
| `template_id` | `int(11)` | No | MUL | - | Foreign key referencing `lab_rubric_templates(id)` |
| `criterion_name` | `varchar(100)` | No | - | - | Name of criterion (Conduction, Observation/Record, Viva Voce) |
| `criterion_description` | `text` | Yes | - | NULL | Detailed rubric descriptor and scoring guidelines |
| `max_marks` | `decimal(4,2)` | No | - | 5.00 | Maximum mark allocatable to this criterion |
| `sort_order` | `int(11)` | No | - | 1 | Display ordering in rubric assistant modal |

> **Foreign Keys**: `template_id` &rarr; `lab_rubric_templates(id)` ON DELETE CASCADE

### 11.3 `lab_subject_rubrics`
Course-specific customized rubric criteria allowing faculty to override template defaults for specialized lab courses.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Record identifier |
| `subject_id` | `int(11)` | No | MUL | - | Foreign key referencing `subjects(id)` |
| `template_id` | `int(11)` | Yes | MUL | NULL | Originating template identifier |
| `criterion_name` | `varchar(100)` | No | - | - | Course-specific criterion name |
| `max_marks` | `decimal(4,2)` | No | - | 5.00 | Maximum mark for this criterion |
| `sort_order` | `int(11)` | No | - | 1 | Display order |

### 11.4 `lab_rubric_evaluations`
Student-level breakdown of marks obtained across individual criteria during continuous laboratory evaluation.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Evaluation record identifier |
| `uglab_mark_id` | `int(11)` | No | MUL | - | Foreign key referencing `uglab_internal_assessment_marks(id)` |
| `criterion_id` | `int(11)` | Yes | - | NULL | Optional reference to `lab_rubric_criteria(id)` |
| `criterion_name` | `varchar(100)` | No | - | - | Criterion evaluated |
| `marks_obtained` | `decimal(4,2)` | No | - | 0.00 | Score awarded for this criterion |
| `created_at` | `timestamp` | No | - | current_timestamp() | Evaluation timestamp |

> **Foreign Keys**: `uglab_mark_id` &rarr; `uglab_internal_assessment_marks(id)` ON DELETE CASCADE

### 11.5 `course_cqi_actions`
Continuous Quality Improvement (CQI) closing-the-loop remedial action tracking for Course Outcomes exhibiting attainment gaps.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | CQI action identifier |
| `sub_id` | `int(11)` | No | MUL | - | Foreign key referencing `subjects(id)` |
| `co_id` | `int(11)` | No | MUL | - | Course Outcome identifier referencing `course_outcomes(id)` |
| `academic_year` | `varchar(20)` | No | - | - | Academic session (e.g. '2025-2026') |
| `target_benchmark` | `decimal(5,2)` | No | - | 60.00 | Attainment threshold benchmark percentage |
| `attained_percentage` | `decimal(5,2)` | No | - | - | Actual direct/indirect attainment achieved |
| `attainment_gap` | `decimal(5,2)` | No | - | - | Attainment shortfall ($\Delta = \text{Target} - \text{Attained}$) |
| `action_proposed` | `text` | No | - | - | Action-Taken narrative proposed by Course Coordinator |
| `action_type` | `enum('REMEDIAL_CLASS','PEDAGOGICAL_CHANGE','TUTORIAL_HOURS','ASSIGNMENT_REVISION','CONTENT_BEYOND_SYLLABUS')` | No | - | REMEDIAL_CLASS | Remedial action classification |
| `faculty_id` | `int(11)` | No | - | - | Faculty submitting the action plan |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Submission timestamp |
| `hod_review_status` | `enum('PENDING','APPROVED','REVISION_REQUESTED')` | No | - | PENDING | HOD / Program Coordinator review status |
| `hod_remarks` | `text` | Yes | - | NULL | Comments and directions from HOD |
| `reviewed_at` | `datetime` | Yes | - | NULL | Review timestamp |
| `reviewed_by` | `int(11)` | Yes | - | NULL | HOD `users.id` who reviewed the CQI action |
| `due_date` | `date` | Yes | - | NULL | Target completion date for the remedial intervention |
| `execution_evidence` | `text` | Yes | - | NULL | Faculty-recorded evidence of completed remedial intervention |
| `completed_at` | `datetime` | Yes | - | NULL | Timestamp when faculty marked the intervention completed |
| `followup_measure` | `text` | Yes | - | NULL | HOD verification notes and follow-up outcome upon closure |
| `closed_by` | `int(11)` | Yes | - | NULL | HOD `users.id` who formally closed the CQI loop |
| `closed_at` | `datetime` | Yes | - | NULL | Formal CQI closure timestamp |

---

## 12. Trusted Core Snapshots, Review History, Migrations & Optional AI Infrastructure
Tables supporting atomic before/after record snapshots, workflow transition history, reproducible report snapshots, schema migration checksum tracking, and opt-in human-reviewed AI draft assistance.

### 12.1 `academic_record_snapshots`
Forensic before-and-after JSON snapshots recorded atomically whenever attendance sessions, deletion requests, SEE blueprints, or external marks are corrected or replaced.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | No | PRI | AUTO_INCREMENT | Snapshot ID |
| `entity_type` | `varchar(50)` | No | MUL | - | Entity classification (`attendance_session`, `attendance_delete_request`, `see_blueprint`, `external_marks_sync`, etc.) |
| `entity_id` | `varchar(64)` | No | MUL | - | Target entity identifier |
| `department_id` | `int(11)` | No | MUL | - | Owning department ID (`departments.id`) |
| `actor_user_id` | `int(11)` | No | - | - | Authenticated `users.id` who performed the mutation |
| `reason` | `text` | No | - | - | Mandatory justification reason provided by actor |
| `before_json` | `longtext` | No | - | - | JSON serialization of state prior to change |
| `after_json` | `longtext` | No | - | - | JSON serialization of state after change |
| `created_at` | `timestamp` | No | - | current_timestamp() | Snapshot creation timestamp |

### 12.2 `workflow_review_history`
Immutable audit log of state transitions across course completion audits, CQI actions, attendance deletion requests, and examination notifications.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | No | PRI | AUTO_INCREMENT | Review history entry ID |
| `entity_type` | `varchar(40)` | No | MUL | - | Reviewed entity type (`course_completion_audit`, `cqi_action`, `attendance_delete_request`, `exam_notification`) |
| `entity_id` | `int(11)` | No | MUL | - | Target record primary key |
| `actor_user_id` | `int(11)` | No | - | - | Authenticated `users.id` who executed the transition |
| `from_status` | `varchar(30)` | No | - | - | Previous workflow status |
| `to_status` | `varchar(30)` | No | - | - | New workflow status |
| `remarks` | `text` | No | - | - | Reviewer remarks or decision justification |
| `created_at` | `timestamp` | No | - | current_timestamp() | Transition timestamp |

### 12.3 `report_snapshots`
Reproducible report state snapshots storing scope, cutoff timestamp, policy hash, and metric payload (`ReportSnapshotService`).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | No | PRI | AUTO_INCREMENT | Report snapshot ID |
| `report_key` | `varchar(50)` | No | - | - | Report type identifier (`cqi_atr`, `external_co_attainment`, etc.) |
| `owner_user_id` | `int(11)` | No | MUL | - | Authenticated `users.id` who generated the snapshot |
| `department_id` | `int(11)` | Yes | MUL | NULL | Scoped `departments.id` |
| `subject_id` | `int(11)` | Yes | MUL | NULL | Scoped `subjects.id` |
| `cutoff_at` | `datetime` | No | - | - | Data cutoff timestamp |
| `policy_hash` | `char(64)` | No | - | - | SHA-256 hash of active academic/attainment policy |
| `metrics_hash` | `char(64)` | No | - | - | SHA-256 hash of computed metrics payload |
| `scope_json` | `longtext` | No | - | - | JSON scope metadata |
| `metrics_json` | `longtext` | No | - | - | JSON metrics payload |
| `state` | `enum('DRAFT','REVIEWED','PUBLISHED')` | No | - | DRAFT | Snapshot review state |
| `reviewed_by` | `int(11)` | Yes | - | NULL | Reviewer `users.id` |
| `reviewed_at` | `datetime` | Yes | - | NULL | Review timestamp |
| `created_at` | `timestamp` | No | - | current_timestamp() | Creation timestamp |

### 12.4 `ai_providers`
SuperAdmin-configured local (`OLLAMA`) or cloud (`OPENAI_COMPATIBLE`) AI provider endpoints (`superadminaiproviders.php`).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(10) unsigned` | No | PRI | AUTO_INCREMENT | Provider ID |
| `name` | `varchar(100)` | No | - | - | Friendly provider label |
| `kind` | `enum('OLLAMA','OPENAI_COMPATIBLE')` | No | - | - | Protocol adapter type |
| `endpoint` | `varchar(500)` | No | - | - | Base API URL (loopback/private for Ollama; HTTPS for OpenAI-compatible) |
| `model` | `varchar(150)` | No | - | - | Model identifier |
| `credential_env` | `varchar(100)` | Yes | - | NULL | Name of `CEA_AI_...` environment variable holding API key (never raw secret) |
| `is_enabled` | `tinyint(1)` | No | - | 0 | 1 = Enabled for opt-in jobs, 0 = Disabled |
| `timeout_seconds` | `int(11)` | No | - | 45 | HTTP request timeout in seconds (5 to 120) |
| `updated_by` | `int(11)` | No | - | - | SuperAdmin `users.id` |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `version` | `int(10) unsigned` | No | - | 1 | Configuration version counter |

### 12.5 `ai_job_consents`
Per-user, per-task opt-in consent state for optional AI draft generation (`aiassistance.php`).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `user_id` | `int(11)` | No | PRI | - | Authenticated `users.id` |
| `task_key` | `varchar(50)` | No | PRI | - | Task identifier (`CO_ATTAINMENT_ANALYSIS`, `ATR_ACTION_PROPOSAL`) |
| `is_enabled` | `tinyint(1)` | No | - | 0 | 1 = User opted in, 0 = Consent revoked/disabled |

> **Primary Key**: (`user_id`, `task_key`)

### 12.6 `ai_jobs`
Bounded background queue and human-review ledger for opt-in AI draft tasks processed by `tools/ai_worker.php`.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | No | PRI | AUTO_INCREMENT | Job ID |
| `snapshot_id` | `bigint(20) unsigned` | No | - | - | Reference to curated `report_snapshots.id` |
| `owner_user_id` | `int(11)` | No | UNI | - | Requesting `users.id` |
| `task_key` | `varchar(50)` | No | UNI | - | Task key (`CO_ATTAINMENT_ANALYSIS` or `ATR_ACTION_PROPOSAL`) |
| `input_hash` | `char(64)` | No | UNI | - | SHA-256 hash of curated evidence input and provider version |
| `prompt_version` | `varchar(30)` | No | UNI | - | Prompt schema version string |
| `status` | `enum('QUEUED','RUNNING','DRAFT','FAILED','CANCELLED','APPROVED')` | No | MUL | QUEUED | Job execution and review status |
| `attempts` | `int(11)` | No | - | 0 | Execution attempt counter (max 3) |
| `locked_at` | `datetime` | Yes | - | NULL | Worker lease acquisition timestamp |
| `model_id` | `varchar(150)` | Yes | - | NULL | Executed model identifier |
| `model_digest` | `varchar(100)` | Yes | - | NULL | Provider system fingerprint / digest when available |
| `output_json` | `longtext` | Yes | - | NULL | Validated structured JSON draft output citing evidence IDs |
| `error_message` | `text` | Yes | - | NULL | Last failure diagnostic message |
| `reviewed_by` | `int(11)` | Yes | - | NULL | Reviewer `users.id` who approved or rejected the draft |
| `reviewed_at` | `datetime` | Yes | - | NULL | Review timestamp |
| `created_at` | `timestamp` | No | MUL | current_timestamp() | Job creation timestamp |
| `provider_id` | `int(10) unsigned` | Yes | - | NULL | Selected `ai_providers.id` |
| `payload_json` | `longtext` | Yes | - | NULL | Curated PII-free CO summary evidence payload |
| `completed_at` | `datetime` | Yes | - | NULL | Worker completion timestamp |
| `claim_token` | `char(32)` | Yes | - | NULL | Worker lease claim token |
| `review_remarks` | `text` | Yes | - | NULL | Reviewer remarks recorded upon approval/rejection |
| `provider_config_hash` | `char(64)` | Yes | - | NULL | SHA-256 hash of provider configuration at queue time |
| `next_attempt_at` | `datetime` | Yes | - | NULL | Earliest timestamp for retry execution |

> **Unique Keys**: (`owner_user_id`, `task_key`, `input_hash`, `prompt_version`)

### 12.7 `schema_migrations`
Immutable ledger recording applied database baseline and additive migration versions with SHA-256 file checksums (`tools/migrate.php`, `tools/baseline.php`).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `version` | `varchar(100)` | No | PRI | - | Migration or baseline filename (e.g., `20261008_001_trusted_core.sql`) |
| `checksum` | `char(64)` | No | - | - | SHA-256 hash of the SQL migration script |
| `applied_at` | `timestamp` | No | - | current_timestamp() | Timestamp when the migration was recorded |
