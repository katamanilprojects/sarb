# Batch-Centric OBE Governance & Master Articulation Inheritance Workflow

This document details the multi-year Batch Cohort Lifecycle and Outcome-Based Education (OBE) Governance model designed to fulfill statutory accreditation standards (NBA Criteria 1, 2, and 3).

---

## 1. Overview & Policy Rationale

### 1.1 The Distinction: Batch vs. Academic Year vs. Regulation
In university accreditation governance, confusion often arises when tying Program Outcomes (POs) and Program Educational Objectives (PEOs) strictly to Academic Years or Curriculum Regulations:
- **Regulation (e.g., R20, R23)**: Defines course structure, contact hours, evaluation schemes (CIA/SEE weightages), and syllabus content. A regulation is typically revised every 3–4 years.
- **Academic Year (e.g., 2024-2025)**: Defines the institutional calendar during which classes run simultaneously across 1st, 2nd, 3rd, and 4th years.
- **Batch (Cohort, e.g., 2024–2028)**: The cohort of students who enter together in an admission year and graduate after completing the degree duration (e.g., B.Tech 4 years, M.Tech 2 years, MCA 2 years).

### 1.2 Accreditation Rationale for Normalized Master Definitions & Cohort Inheritance
Institutional curriculum guidelines dictate:
1. **Master Definitions Stored Once**: Vision, Mission, PEOs, POs, and PSOs are defined at the Institution, Department, or Program level. They do not change arbitrarily each year and must not be redundantly stored per cohort.
2. **Cohort Inheritance**: When a student cohort is created (e.g. `2021-2025`, `2025-2029`), it **inherits** the active master Vision, Mission, PEOs, POs/PSOs, and articulation matrices matching its academic department and entry year.
3. **NBA / Curriculum Revisions**: If NBA or Academic Authorities redefine POs or PEOs in a given year (e.g., 2025), a new version is created in the master tables with `effective_from_year = 2025`. Prior batches retain their original standards, while newly admitted cohorts inherit the revised standards.
4. **Macro-Attainment Rollup**: Final program attainment can only be calculated once a batch completes its entire degree timeline (Semesters 1 through 8 for B.Tech). The batch-centric architecture allows seamless tracking of a cohort across all 4 years regardless of intervening calendar shifts.

---

## 2. Full OBE Hierarchy Architecture

The system implements the complete institutional accreditation hierarchy:

```mermaid
flowchart TD
    subgraph MasterStandards["1. Department Master Standards (Stored Once)"]
        VM["vision_mission<br/>(Dept / Institution Level)"] --> PEO["peos<br/>(Program / Dept Level)"]
        PEO --> PEOMAP["peo_mission_mapping<br/>(PEO ➔ Mission Articulation)"]
        POPSO["po_pso<br/>(NBA PO1-PO12 & PSOs)"] --> POPEO["po_peo_mapping<br/>(PO/PSO ➔ PEO Articulation)"]
    end

    subgraph CohortConsumption["2. Cohort Consumption & Overrides"]
        Batch["student_batches<br/>(e.g., 2025-2029)"] -.->|Inherits Master Standards| VM
        Batch -.->|Inherits Master PEOs| PEO
        Batch -.->|Inherits Mappings| PEOMAP
        Batch -.->|Inherits POs & Mappings| POPEO
        BPT["batch_peo_targets<br/>(Optional Cohort Target Overrides)"] --> Batch
    end

    subgraph CourseLevelOBE["3. Course Execution & Attainment Rollup"]
        CO["Course Outcomes (COs)<br/>(course_outcomes per Course)"]
        ARTIC["Course CO-PO Articulation Matrix<br/>(co_po_mapping)"]
        ASSESS["Course Assessments (CIA & SEE)<br/>(cia_marks & exam_results)"]
        COATT["Micro-Attainment: Direct & Indirect CO Attainment<br/>(facciaanalysis2.php)"]
        MACRO["Macro-Attainment: Batch Program Attainment Rollup<br/>(superadminbatchobe.php)"]

        POPSO --> CO
        CO --> ARTIC
        ARTIC --> ASSESS
        ASSESS --> COATT
        COATT --> MACRO
    end
```

---

## 3. Workflow Sequence Diagram

```mermaid
sequenceDiagram
    autonumber
    actor SuperAdmin
    participant BatchUI as superadminbatches.php
    participant OBEUI as superadminbatchobe.php
    participant Service as BatchOBEService.php
    participant DB as MariaDB (student_batches, vision_mission, peos, po_pso)

    Note over SuperAdmin,DB: Phase 1: Master Definition Setup (Stored Once)
    SuperAdmin->>OBEUI: Formulate Department Vision & Mission Statements
    OBEUI->>Service: saveMasterVisionMission(dept_id, vision, mission_points, effective_year)
    Service->>DB: INSERT INTO vision_mission ON DUPLICATE KEY UPDATE
    SuperAdmin->>OBEUI: Formulate Program Educational Objectives (PEO1 to PEO5)
    OBEUI->>Service: saveMasterPEO(dept_id, prog_id, peo_code, title, desc, target, effective_year)
    Service->>DB: INSERT INTO peos ON DUPLICATE KEY UPDATE
    SuperAdmin->>OBEUI: Define PEO-to-Mission Matrix
    OBEUI->>Service: savePeoMissionMatrix(dept_id, matrix)
    Service->>DB: INSERT INTO peo_mission_mapping
    SuperAdmin->>OBEUI: Define PO/PSO-to-PEO Matrix
    OBEUI->>Service: savePoPeoMatrix(dept_id, matrix)
    Service->>DB: INSERT INTO po_peo_mapping

    Note over SuperAdmin,DB: Phase 2: Cohort Creation & Instant Inheritance
    SuperAdmin->>BatchUI: Define new Batch (e.g. 2025-2029, B.Tech, 4 Years)
    BatchUI->>Service: createBatch(program_id, regulation_id, batch_name, admission_year, graduation_year)
    Service->>DB: INSERT INTO student_batches
    Note over Service,DB: Batches instantly inherit master vision, mission, PEOs, POs without creating duplicate rows!

    Note over SuperAdmin,OBEUI: Phase 3: Cohort View & Custom Overrides
    SuperAdmin->>OBEUI: Select Cohort 2025-2029
    OBEUI->>Service: getBatchPEOs(batch_id, dept_id)
    Service-->>OBEUI: Return inherited PEOs + custom target overrides
    SuperAdmin->>OBEUI: Optional: adjust target score for cohort
    OBEUI->>Service: saveBatchPEOTarget(batch_id, peo_id, target_score)
    Service->>DB: INSERT INTO batch_peo_targets

    Note over SuperAdmin,OBEUI: Phase 4: Batch Macro-Attainment Rollup
    SuperAdmin->>OBEUI: Navigate to Macro-Attainment Tab
    OBEUI->>Service: getBatchMacroAttainment(batch_id, dept_id)
    Service->>DB: Aggregate all completed courses for this batch cohort
    Service-->>OBEUI: Return Program PO Attainment, PEO Attainment & Mission Fulfillment %
```

---

## 4. Database Schema Reference

| Table Name | Entity Scope | Primary Purpose | Key Foreign Keys |
| :--- | :--- | :--- | :--- |
| `student_batches` | Cohort | Master cohort records (2021-2025, 2025-2029) | `program_id` &rarr; `programs(id)`, `regulation_id` &rarr; `regulations(id)` |
| `classes` | Section | Section-to-cohort link | `batch_id` &rarr; `student_batches(id)` |
| `vision_mission` | Department / Institution | Master Vision and parsed Mission statements | `dept_id` &rarr; `departments(id)` |
| `peos` | Program / Department | Master Program Educational Objectives | `dept_id` &rarr; `departments(id)`, `program_id` &rarr; `programs(id)` |
| `peo_mission_mapping` | Program / Department | Master PEO-to-Mission correlation matrix | `peo_id` &rarr; `peos(id)` |
| `po_pso` | Regulation / Spec | NBA Graduate Attributes (PO1-PO12) and PSOs | `specid` &rarr; `specialization(id)`, `reg_id` &rarr; `regulations(id)` |
| `po_peo_mapping` | Department | Master PO/PSO-to-PEO correlation matrix | `po_pso_id` &rarr; `po_pso(id)`, `peo_id` &rarr; `peos(id)` |
| `batch_peo_targets` | Cohort Specific | Optional cohort custom target score overrides | `batch_id` &rarr; `student_batches(id)`, `peo_id` &rarr; `peos(id)` |

---

## 5. Program Articulation Matrix (NBA Tables 3.2 & 3.3.2)

To satisfy National Board of Accreditation (NBA) Self Assessment Report (SAR) Criteria 3.2 and 3.3.2, the batch governance engine computes the complete Department PO/PSO Articulation Matrix:

### 5.1 Direct vs. Indirect Attainment Blend Formula
Program Outcomes (PO1–PO12) and Program Specific Outcomes (PSO1–PSO3) are evaluated through a statutory 80:20 composite model:
$$\text{PO Attainment} = (0.80 \times \text{Direct Attainment}) + (0.20 \times \text{Indirect Attainment})$$

1. **Direct Attainment (80% Weightage)**:
   - Aggregated from the weighted average of individual Course Outcome (CO) attainments across all core and elective courses completed by the cohort.
   - Evaluated using course-level CO-PO mapping weights (1 = Slight, 2 = Moderate, 3 = Substantial).
2. **Indirect Attainment (20% Weightage)**:
   - Sourced from graduating cohort exit surveys in `student_ces_feedback`.
   - Normalizes 5-point Likert ratings to an equivalent 0–3 attainment scale:
     $$\text{Indirect PO Score} = \left(\frac{\text{Average CES Rating}}{5.0}\right) \times 3.0$$

### 5.2 Service Method: `BatchOBEService::computePOPSOArticulationMatrix()`
Implemented in `services/BatchOBEService.php`:
```php
public function computePOPSOArticulationMatrix(int $batchId, int $programId, ?int $departmentId = null): array
```
- Validates explicit `batchId`, `programId`, and `departmentId` scope (never inferring programme context implicitly).
- Fetches all courses registered for the batch cohort with their corresponding CO-PO mapping vectors.
- Calculates direct PO/PSO averages strictly from real recorded assessment evidence across academic semesters (no simulated or synthetic fallback scores are generated; unmeasured indirect or PEO metrics explicitly report `Unavailable`).
- Ingests exit survey records from `student_ces_feedback` when available for the cohort.
- Produces the NBA Table 3.2 Course-PO matrix and Table 3.3.2 Final PO/PSO Attainment summary.
- Rendered in `views/popso_department_tab.php` and toggle-guarded by `FeatureManager::isModuleEnabled('MOD_PO_PSO_ATTAINMENT')`.

---

## 6. Longitudinal Multi-Cohort Quality Analytics

For academic governance and institutional quality monitoring, `superadminbatchobe.php` provides longitudinal trajectory analytics tracking multiple successive graduating batches.

### 6.1 Multi-Cohort Trajectory Model
- **Endpoint**: `superadminbatchobe.php`
- **Method**: `BatchOBEService::getLongitudinalCohortAnalytics(int $deptId): array`
- **Analytics Captured**:
  1. **Cohort-over-Cohort PO/PSO Trends**: Direct comparison of measured PO1–PO12 attainment across consecutive batches for the department.
  2. **Evidence Integrity**: Only cohorts with actual recorded course assessment evidence are plotted; unavailable longitudinal or indirect PEO metrics remain explicitly marked as unavailable rather than simulated.
  3. **Continuous Improvement Identification**: Highlights outcomes exhibiting positive growth vs. persistent dips requiring Board of Studies curriculum intervention.

---

## 7. Feature Toggle Governance

| Module Code | Friendly Name | Target Interfaces |
| :--- | :--- | :--- |
| `MOD_BATCH_GOVERNANCE` | Batch-Centric Governance, Vision/Mission, PEOs & Macro-Attainment | `superadminbatches.php`, `superadminbatchobe.php` |
| `MOD_BATCH_OBE` | Multi-Batch Longitudinal OBE Analytics & Cohort Trajectory Radar Charts | `superadminbatchobe.php` |
| `MOD_PO_PSO_ATTAINMENT` | PO/PSO Program Articulation Matrix (NBA Tables 3.2 & 3.3.2) | `views/popso_department_tab.php`, `superadminbatchobe.php` |

