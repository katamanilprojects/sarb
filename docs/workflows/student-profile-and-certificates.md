# Student Biographical Dossier, Document Vault, Custody Ledger & Statutory Certificates Workflow

This document details the lifecycle for student biographical profiling, cloud document vault storage, physical original certificates custody governance, and self-service statutory certificate issuance.

---

## 1. Overview & Policy Rationale

Autonomous engineering colleges and university constituent colleges are legally accountable for maintaining statutory student identity records and safely managing original student certificates submitted at the time of admission:
1. **Biographical Dossier**: Centralizes personal, parental, demographic, category/quota, and contact records.
2. **Cloud Document Vault**: Provides students a secure digital vault to upload PDF/JPG scans of prerequisite certificates (Aadhaar, SSC, Intermediate/Diploma, Entrance Rank Cards, Caste, Income).
3. **Physical Custody Ledger**: Under autonomous university rules, original certificates are retained in college custody until degree completion. When students require original certificates temporarily (e.g., for Passport verification, Visa interviews, or Education Loans), a formal audited custody ledger tracks checkout, due date, return, or permanent clearance.
4. **Statutory Certificate Generation**: Automates the request, administrative review, and generation of official institutional certificates:
   - **Bonafide / Study Certificate**
   - **Conduct Certificate**
   - **Custodial Certificate** (formally certifying all original certificates held in institutional custody)
   - **Transfer Certificate (TC)**

---

## 2. Architecture & Workflow Sequence

```mermaid
sequenceDiagram
    autonumber
    actor Student
    participant StuProfUI as studentprofile.php
    participant StuCertUI as studentcertificates.php
    participant Service as StudentProfileService.php
    participant DB as MariaDB (student_*, users)
    actor AdminStaff as Academic Section / Admin
    participant AdminProfUI as adminstudentprofiles.php
    participant CustodyUI as admincustodyledger.php
    participant PrintUI as student_print_certificate.php

    Note over Student,StuProfUI: Phase 1: Biographical Dossier & Vault Upload
    Student->>StuProfUI: Fill personal details, parents, Aadhaar, blood group, address
    StuProfUI->>Service: saveProfile(student_id, data)
    Service->>DB: INSERT/UPDATE student_profiles
    Student->>StuProfUI: Upload digital certificate scans (PDF/JPG)
    StuProfUI->>Service: uploadDocument(student_id, doc_type, file)
    Service->>DB: INSERT INTO student_documents (verification_status = 'PENDING')

    Note over AdminStaff,AdminProfUI: Phase 2: Administrative Verification & Custody Intake
    AdminStaff->>AdminProfUI: Review student profile & inspect uploaded scans
    AdminStaff->>Service: verifyDocument(doc_id, status, remarks)
    Service->>DB: UPDATE student_documents SET verification_status = 'VERIFIED'
    AdminStaff->>CustodyUI: Physical Intake of original certificates (e.g. 10th Marks Card)
    CustodyUI->>Service: addCustodialRecord(student_id, doc_name, cert_number, barcode)
    Service->>DB: INSERT INTO student_custodial_records (status = 'IN_CUSTODY')

    Note over AdminStaff,CustodyUI: Phase 3: Temporary Certificate Checkout & Return
    Student->>AdminStaff: Request original certificate for Passport Verification
    AdminStaff->>CustodyUI: Temporarily return certificate (specify return_due_date, purpose)
    CustodyUI->>Service: temporarilyReturnCertificate(record_id, due_date, purpose)
    Service->>DB: UPDATE student_custodial_records SET status = 'TEMPORARILY_RETURNED'
    Student->>AdminStaff: Return original certificate after verification
    AdminStaff->>CustodyUI: Mark returned to college custody
    CustodyUI->>Service: markCertificateReturned(record_id)
    Service->>DB: UPDATE student_custodial_records SET status = 'IN_CUSTODY'

    Note over Student,StuCertUI: Phase 4: Statutory Certificate Application
    Student->>StuCertUI: Apply for Certificate (e.g., Custodial or Bonafide Certificate)
    StuCertUI->>Service: requestCertificate(student_id, cert_type, purpose)
    Service->>DB: INSERT INTO student_certificate_requests (status = 'SUBMITTED')

    Note over AdminStaff,AdminProfUI: Phase 5: Approval & Tamper-Evident Issuance
    AdminStaff->>AdminProfUI: Review Certificate Request
    AdminStaff->>Service: updateCertificateStatus(req_id, 'APPROVED', cert_number, approver_id)
    Service->>DB: UPDATE student_certificate_requests SET status = 'APPROVED', cert_number = ?, issued_at = NOW()

    Note over Student,PrintUI: Phase 6: Student View & High-Fidelity Print
    Student->>PrintUI: Open approved certificate
    PrintUI->>Service: getCertificateRenderData(req_id)
    Service->>DB: Query profile, custody records, and issuance details
    PrintUI-->>Student: Render official printable certificate with Watermark, Security Hash & Signatures
```

---

## 3. Step-by-Step Implementation

### Step 1: Biographical Dossier (`studentprofile.php`)
- **Demographics**: Father's name, Mother's name, Date of Birth, Gender, Blood Group, Aadhaar Number, Category/Quota (OC, BC-A, BC-B, SC, ST, EWS, Management, NRI), Religion, Nationality.
- **Academic Coordinates**: Admission Date, Hall Ticket / Roll Number, Permanent Address, Current Address, Emergency Contact.
- **Verification Workflow**: Academic Section officers can mark profiles as `VERIFIED` with formal administrative notes.

### Step 2: Cloud Document Vault
- Supported document types:
  - `AADHAAR`: National Identity Card scan.
  - `SSC_MARKS`: Class 10 / Secondary School Certificate.
  - `INTER_MARKS`: Class 12 / Intermediate / Diploma Certificate.
  - `ENTRANCE_RANK_CARD`: EAMCET / ECET / ICET Rank Card.
  - `CASTE_CERT`: Community / Category Certificate.
  - `INCOME_CERT`: Statutory Income Certificate for fee reimbursement.
- File security: Stored under sanitized file hashes in `uploads/student_docs/` with file mime validation and direct PHP execution blocking.

### Step 3: Physical Custody Ledger (`admincustodyledger.php`)
- Records physical documents surrendered upon admission:
  - Document name & serial/certificate number.
  - Custody barcode/rack/box locator for physical archives.
  - Lifecycle state machine:
    - `IN_CUSTODY`: Secured in college records room.
    - `TEMPORARILY_RETURNED`: Checked out with strict `return_due_date` and purpose log.
    - `PERMANENTLY_RETURNED`: Released permanently upon graduation or formal TC issuance.

### Step 4: Statutory Certificate Engine (`StudentProfileService`) & Cryptographic QR Verification (`MOD_CERTIFICATE_QR`)
- Supported certificate categories:
  1. **Bonafide / Study Certificate**: Certifies current enrollment, academic year, regulation, and course duration.
  2. **Conduct Certificate**: Attests to moral character, discipline, and campus conduct.
  3. **Custodial Certificate**: Formally enumerates every original document held in university custody, allowing students to present proof of custody to embassies, passport authorities, and financial institutions without withdrawing their physical originals.
  4. **Transfer Certificate (TC)**: Formal institutional departure certificate.
- **Tamper-Evident Security & Cryptographic QR Verification (`viewcertificate.php`)**:
  - Unique Institutional Certificate Number (e.g., `JNTUACEA/CUST/2026/0042`).
  - Cryptographic Verification Token derived from SHA-256 hash:
    $$\text{Token} = \text{hash}\Big(\text{"sha256"}, \text{certificate\_no} \mathbin{\Vert} \text{roll\_no}\Big)$$
  - Scannable dynamic QR Code printed directly on certificates and custodial receipts pointing to `viewcertificate.php?cert_no=<NO>&token=<HASH>`.
  - Public verification engine validates authenticity via `StudentProfileService::verifyCertificateByToken()` and displays official verified banner with student name, roll number, issue date, and authority details.
  - Background university seal watermark and formal signature blocks.

---

## 4. Student Attendance Risk Gauge (`MOD_STUDENT_ATTENDANCE_RISK`)

Integrated directly into `jntuaceastudents/studenthome.php` powered by `StudentAttendanceService::getStudentAttendanceRiskMetrics()`:
- **Aggregate Attendance Standings**: Real-time display of total conducted periods ($T$), attended periods ($P$), and overall aggregate percentage ($A = \frac{P}{T} \times 100$).
- **Safe Margin Metric**: When $A \ge 75.0\%$, calculates how many upcoming lecture periods the student can afford to miss without breaching the $75\%$ regulatory floor:
  $$\text{Safe Margin} = \max\left(0, \left\lfloor \frac{P - 0.75 \times T}{0.75} \right\rfloor\right)$$
- **Recovery Periods Needed**: When $A < 75.0\%$, calculates consecutive future class periods the student must attend without absence to restore attendance back to $\ge 75.0\%$:
  $$\text{Recovery Periods} = \max\left(0, \left\lceil \frac{0.75 \times T - P}{0.25} \right\rceil\right)$$
- **Regulatory Standing Badges**: Renders Green Badge ($\ge 75\%$, Safe Zone), Warning Yellow Badge ($65.0\% \le A < 75.0\%$, Condonation Bracket), or Red Alert Badge ($< 65.0\%$, Detention Risk).

---

## 5. Database Schema Reference

| Table Name | Description | Key Foreign Keys |
| :--- | :--- | :--- |
| `student_profiles` | Demographic & biographical student records | `student_id` &rarr; `students(id)` |
| `student_documents` | Cloud digital document scans & verification statuses | `student_id` &rarr; `students(id)`, `verified_by` &rarr; `users(id)` |
| `student_custodial_records` | Physical original certificate custody tracking | `student_id` &rarr; `students(id)`, `received_by` &rarr; `users(id)` |
| `student_certificate_requests` | Self-service certificate applications & approvals | `student_id` &rarr; `students(id)`, `processed_by` &rarr; `users(id)` |
