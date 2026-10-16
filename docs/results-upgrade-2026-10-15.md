# Results integration upgrade and next priorities

15 October 2026. Applies to the active staff (`jntuacea`) and student (`jntuaceastudents`) portals. `resultsold` supplied the legacy result-field and sentinel conventions; its authentication and table-selection implementation were not copied.

## Decisions implemented

- The existing alternate staff login remains unchanged, as requested.
- Academic Section creates a draft notification for one program, regulation, semester, academic year, and exam type.
- CSV rows identify a student by admission number and a course by subject code. `AdmissionNumber`, `HTNO`, and legacy `std_regno` are supported, alongside the existing subject/marks aliases.
- Upload → validated preview → draft import → review → publication. Imports are atomic; one invalid row prevents the whole import. A changed preview policy or offering match requires a fresh preview.
- Regular results match the exact offering/year/semester. Supplementary and revaluation notifications remain separate attempts and can resolve a historical academic year within the same program, regulation, and semester only when the student/course offering is unambiguous.
- Publishing a regular notification automatically synchronizes available SEE evidence in the same transaction. If evidence persistence fails, publication rolls back too.
- Existing manual and question-wise SEE marks are preserved. A correction to evidence already sourced from the same notification can refresh that evidence after withdrawal, correction, review, and republication.
- Supplementary/revaluation results do not replace regular OBE evidence. An explicit faculty synchronization also requires a published regular attempt.
- Withdrawing results marks their dependent SEE evidence stale. It does not invalidate independently entered manual/question-wise marks.
- Students can read only their own published results and notification metadata. Per-course lookup uses the exact subject offering, rather than borrowing another course with the same code. Separate exam attempts have separate cards; supplementary/revaluation averages are labeled attempt GPA. Withheld results have a pending GPA.

## Validation and regulation configuration

The importer uses the exact offering's curriculum and attainment overrides. Grade bands come from an explicit assessment profile or that exact regulation ID's `grade_bands_json`; it does not borrow a global grade scale.

- Grade letters, grade points, and pass/non-pass status must agree. The same grade can have different points in different regulations.
- Legacy `em=-1` and grade `AB` normalize absence before computing earned credits; absence cannot retain positive points or earned credits.
- `im=-7` and `em=-5` retain their missing/exempt meanings. Blank/`-` values remain missing. Malformed numbers and unsupported negative values are rejected.
- Uploaded maxima are checked against configured course maxima, rather than overriding them.
- Marks and totals must fit the existing two-decimal result fields. A complete internal/external pair must agree with the total within the supported precision.
- Zero-credit courses remain zero-credit. Missing credits are not silently replaced with three. Failed legacy rows may report zero earned credits while registered credits come from curriculum.
- Raw external marks supply SEE directly. Grade-only SEE estimation requires an explicit configured `grade_strategy` and grade bands. Without that strategy, the result may be published, but synchronization reports pending evidence rather than inventing a mark. Withheld results are not converted to a numeric SEE score.
- Publication reports how many SEE rows were synchronized, preserved, or left pending. Publishing regular results requires permission to edit SEE evidence as well as approve results.

The read-only local inspection found 90 tables, no existing exam notifications/results, and 238 offerings without curriculum links across all history. That historical count does not establish that all 238 need repair. Current offerings need complete, approved maxima/credit/grade configuration before their results can be accepted reliably. No working-database records were changed or policies seeded by this upgrade.

## SQL and file-access repairs

Attendance date filters in the faculty and HOD reporting paths now validate dates and bind them as SQL parameters. The methods also enforce subject scope.

The legacy CO feedback export now authorizes its `subid` explicitly. CIA attachment downloads resolve the database row and authorize its subject before streaming the file. Direct CIA-upload and generated bluebook/feedback-PDF paths are denied by the included Apache rules. The two existing public CIA templates remain available. PDF temporary caches are denied separately. New attachments use random filenames, enforce the five-attachment limit under a subject lock, encode titles at display, and remove a newly uploaded file if its metadata save fails.

Storage remains in its current locations. Deploy the access-rule files with the PHP code and retain Apache `AllowOverride` support; a PHP-only endpoint change cannot protect a file URL that the web server serves directly. Previously deleted CIA metadata does not authorize a download, even if the retained file still exists.

## CIA export defects — original findings, now repaired

The examples below describe the original defects. The follow-up implementation replaces the shared-variable helper with a scoped, regulation-aware calculator; see `cia-policy-and-results-corrections-2026-10-15.md`.

### Different subjects can receive identical marks

`download_cls_cia.php` loops through subjects but uses `require_once('modulecheckcia.php')` inside the loop. That helper loads marks into shared variables. PHP executes it only for the first subject; later subjects reuse those variables.

Illustrative student record:

| Course | Correct CIA | Current export can show |
| --- | ---: | ---: |
| Mathematics, first column | 24 | 24 |
| Physics, second column | 18 | 24 |
| Chemistry, third column | 27 | 24 |

The defect also retains the first subject's program and subject type, so mixed theory/lab/project exports can be calculated through the wrong branch. This was reproduced with the actual helper: subject 11 loaded marks 11; subject 22 still used marks 11.

The export now obtains independent values for each student and subject. The student subject page and CIA reconciliation use the same calculator. Missing required assessments and policies produce no numeric total; entered zeroes remain zero.

### PG totals can carry between students

The PG branch conditionally assigns `$totalInt` and `$totalCIA` without resetting them at the beginning of each student iteration. Missing assessment data leaves the previous student's value available.

Using the formula currently hard-coded in that branch, solely to illustrate the defect:

| Student | Mid 1 | Mid 2 | Component 3 | Expected handling | Current export can show |
| --- | ---: | ---: | ---: | --- | ---: |
| A | 20 | 10 | 3 | `0.7×20 + 0.3×10 + 3 = 20` | 20 |
| B | Missing | 8 | Missing | Incomplete; no final total | 20, inherited from A |

If B supplies component 3 as 2, the old intermediate total 17 can instead yield 19 despite B lacking the required first assessment. The correct rule for an incomplete assessment must come from the selected regulation. Resetting variables alone does not replace regulation-specific calculation and missing-data rules.

## Initial results/security verification

- 78 staff/results regression checks passed on a disposable database created from the real MySQL 8.0.44 schema, using only synthetic records.
- 15 student-portal checks passed, including own-result isolation, hidden drafts, exact offering lookup, distinct regulation grade points, and separate attempts.
- 13 distinct HTTP checks passed against isolated synthetic role sessions and local Apache, including authorized/unauthorized attachment downloads, protected feedback export, student pages, notification-form rendering, direct attachment URL denial, PDF-cache denial, and public-template availability.
- Syntax checks passed for all 351 first-party PHP files; shared-core checksum verification passed for all 22 files in both portals.
- Rollback tests deliberately failed evidence persistence to verify that publication and SEE updates do not partially commit.

Existing older test suites have unrelated path/fixture problems identified in the audit; they were not represented as passing. Production deployment and a representative college CSV acceptance run remain to be performed.

For repeatable isolated testing, `php tools/prepare_results_test.php` creates a uniquely named empty schema copy and prints its name. Pass that name to:

```text
php tests/results_portal_regression.php --database=cea_results_test_<12 hex characters>
php tests/results_portal_regression.php --database=cea_results_test_<12 hex characters> --student
```

The main test resets the supplied disposable database and seeds synthetic records. It refuses the configured working database and any name outside its restricted test prefix. Remove the disposable database after both runs using the database administration interface. Do not use production data as test fixtures.

## Follow-up implementation

The CIA export, separate PG continuous/record fields, structured policy editor, current-course readiness screen, and versioned correction imports are now implemented. The detailed implementation, document-derived rules, pending database activation, examples, tests, and remaining gaps are recorded in [the follow-up report](cia-policy-and-results-corrections-2026-10-15.md).

Historical policy freezing, nonstandard course-entry workflows, configurable attendance/feedback review rules, measured query/index optimization, and release/recovery standardization remain further work. Impersonation redesign remains a separate discussion.
