# CIA policies, results corrections and readiness

15 October 2026. Active portals: `jntuacea` and `jntuaceastudents`. This follows [the results/security upgrade](results-upgrade-2026-10-15.md). The intentional alternate login is unchanged. The working database has only been read; code and all data-changing verification ran against a disposable database with synthetic records.

## Changes completed

Class CIA export now uses a scoped calculator with separate state for every student and subject. Student subject totals and academic CIA reconciliation use that same calculator. Required assessments must all exist; a missing mark is not silently treated as zero. Zero scores are preserved. Invalid component values, duplicate evidence and scores exceeding configured maxima produce a blank final total and an explicit status. Unknown course types require an explicit rule. A class with no complete totals returns a clear error instead of an empty or fabricated marks export.

PG totals require a configured rule for the exact regulation ID and course type. There is no general PG 70/30 fallback. The structured rule editor supports required components, per-assessment maxima, theory weights and rounding, with an authority/reason and before/after history. Verified document formulas can be loaded into the editor for review without saving anything automatically.

The PG entry pages now expose separate continuous evaluation and lab-record fields where configured. Whole-cohort draft, submission and correction operations validate membership, score ranges and precision before writing. All changes are transactional and audited. Corrections require a reason, and stale forms are rejected. Regulation and subject locking prevents cooperating policy/marks updates from interleaving. Existing evidence is not redistributed to new fields automatically.

Results imports now explicitly choose incremental update or complete-file replacement. The preview counts additions, changes, removals and unchanged records; shows changed values and records that will be removed; and binds the import mode, current draft, source and course policies to confirmation. Corrections and complete replacements require a reason. Removed/changed records survive in before/after snapshots, grouped by course and department. A failed audit rolls back the entire import. Reviewed results must return to draft and receive a fresh review after correction. The history page shows who changed a notification, when, why and how its record count changed.

An explicit external maximum of zero now remains zero. Internal-only results can be published without creating fictitious SEE marks. Automatic regular-result SEE synchronization still preserves manual and question-wise marks, and supplementary/revaluation attempts remain separate.

## Regulation sources and decisions

The relevant rules and grade-table text were extracted from the original PDFs; the PG rule and grade-table pages were also visually checked. The reviewed rule catalogue stores their SHA-256 checksums. A changed source file requires another review before the configuration tool applies it.

| Program / regulation | Theory CIA | Practical CIA | Source |
| --- | --- | --- | --- |
| MCA R20 | `0.7 × better mid + 0.3 × other mid + assignment`; mids out of 30, assignment out of 10 | One recorded internal assessment out of 40; the document does not specify an additional day/test/record split | [MCA R20](regulations/mca_r20_regulations.pdf), evaluation section 4 and grading section 5 |
| M.Tech R21 | `0.7 × better mid + 0.3 × other mid + assignment`; mids out of 30, assignment out of 10 | Day-to-day 10 + internal test 20 + record 10 | [M.Tech R21](regulations/mtech_r21_regulations.pdf), sections 5.1–5.4 and 6 |
| M.Tech R25 | `0.8 × better mid + 0.2 × other mid + continuous evaluation`; mids out of 30, continuous evaluation out of 10 | Day-to-day 10 + internal test 20 + record 10 | [M.Tech R25](regulations/mtech_r25_regulations.pdf), sections 8.1–8.4 and 15 |
| B.Tech R23 | `0.8 × better mid + 0.2 × other mid`; each mid combines subjective 15, objective 10 and assignment 5 | Day-to-day 15 + internal test 15 | [B.Tech R23](regulations/btech_r23_regulations.pdf), sections 9 and 19 |

The user explicitly approved nearest-whole-mark rounding for final PG CIA, treating B.Tech R20 and R26 like R23, and using the MCA PDF grade-point table. Those decisions are distinguished from the document text in the configuration audit metadata. The MCA table includes fractional points: A1 9.5, A3 8.5, B2 7.5, C2 6.5 and D2 5.5. The new tests verify those values survive import, publication and SGPA calculation.

PG evidence identities are: mid 1/2, assignment 3, continuous evaluation 4, lab internal 11, lab record 12. For M.Tech R21/R25, field 11 holds the combined day-to-day/internal-test subtotal out of 30; record 12 is separate and out of 10. R25 theory uses component 4 rather than adding both old assignment 3 and continuous evaluation 4. Historical component 3 values are retained, but do not contribute to an explicitly configured R25 total.

## CIA export defects with examples

The old export included `modulecheckcia.php` using `require_once` inside the subject loop. The helper ran only for the first subject and left marks, program and subject-type variables shared with later subjects. A student with Mathematics 24, Physics 18 and Chemistry 27 could therefore receive an exported row of **24, 24, 24**. Mixed theory/lab/project classes could also enter the wrong formula branch. The new implementation loads independent evidence per offering; HTTP tests verify theory 23, a missing course blank, lab 15 and an entered zero in the same export.

The PG branch also retained intermediate totals between students. If A had mid scores 20 and 10 plus assignment 3, its legacy total was `0.7×20 + 0.3×10 + 3 = 20`. B missing the first mid and assignment could inherit A's 20. B supplying only assignment 2 could instead receive 19 from A's intermediate 17. The new calculator reports B incomplete and exports no final total.

R25's formula creates another concrete difference: mid scores 20 and 10 plus continuous evaluation 8 produce `0.8×20 + 0.2×10 + 8 = 26`, not the old 70/30 total of 25. Applying both assignment and continuous evaluation would double-count the extra component; the configured required-assessment list prevents this.

## Current readiness and data issues

Read-only inspection on 15 October found **394 current subject offerings** using active class status and date ranges. **320 had calculable CIA configuration** and **328 had result-policy configuration** under the readiness checks. These counts assess configuration, not whether marks have been entered or a complete result file exists. Counts reflect the current database before the prepared activation.

- 56 current offerings lack a valid curriculum link for their exact regulation, specialization and semester: 38 theory, 12 lab, 3 skill and 3 audit courses. Theory can use an exact regulation CIA maximum, but result import still needs valid credits and maxima.
- PG configuration remains unseeded in the working database, so its totals remain blocked.
- 36 MCA R20 and 210 M.Tech R21 curriculum theory/lab/elective rows retain the legacy **30 CIA / 70 SEE** maxima. Their supplied regulations specify **40 CIA / 60 SEE**. The prepared correction changes only that confirmed legacy pair; unexpected maxima stop activation for review.
- `academic_settings` currently enforces uniqueness by regulation label and setting key. That prevents B.Tech R20 and MCA R20 from storing distinct values. The new migration keys settings by regulation ID, retaining label-based uniqueness for legacy rows without an ID.
- 25 current skill courses, 15 audit courses, 8 integrated courses, one mandatory course, one project-review course and one co-curricular course need dedicated entry/calculation policy decisions. They are surfaced for review; a generic theory formula is not silently assigned to them. Existing skill-course marks-entry structure does not model the regulation's continuous-assessment workflow directly.
- Drawing/design evaluation and two-part integrated practical courses need explicit models for weighted mid tests/common day-to-day marks or averaging of separately assessed parts. A simple sum or theory fallback would be incorrect.
- Zero-credit satisfactory/unsatisfactory courses need an explicit result-status/grade representation. The ordinary letter-grade import supports zero credits but does not yet implement full audit-course grading semantics.
- Existing attainment/subject overrides can supersede regulation settings. The readiness screen flags result maxima or grade points that disagree with the reviewed tables; overrides require review before reliance on those results.

## Production SQL package — not applied

The user requested production SQL migration files. Both files are complete and tested; the working database and production remain unchanged.

1. `database/migrations/20261015_001_regulation_setting_scope.sql` separates settings by regulation ID with a replay-safe atomic ALTER.
2. `database/migrations/20261015_002_verified_regulation_policies.sql` contains the actual six regulation configurations, grade tables, confirmed PG maxima corrections and before/after audit snapshots. It requires an active academic administrator's users.id and authority, resolves production IDs semantically, rejects missing mappings/unexpected maxima, and rolls back persistent updates together on failure.

The production runner now accepts `--actor` and `--reason`, supports the SQL routine's DELIMITER blocks, and retains its verified-backup requirement. Full application instructions, read-only post-checks and recovery guidance are in [the production runbook](deployment/production-regulations-2026-10-15.md). The existing PHP configuration tool remains an optional read-only preview/alternative activation utility; do not run both as separate changes unnecessarily.

After activation, review current-course readiness and repair remaining links/classification/overrides. Enter and review actual marks, and validate a representative college CSV before live publication. Neither migration rewrites entered marks, imports/publishes results or changes SEE evidence.

## Verification

All writes used an empty schema copy with synthetic records, not a copy of student data. The temporary HTTP harness was outside the website and listened only on loopback.

| Checks | Count |
| --- | ---: |
| Existing results/security integration | 78 |
| CIA calculation, PG entry, policies and correction history | 62 |
| Verified configuration, migration replay, rollback, fractional MCA grades and internal-only publication | 20 |
| Student result isolation and separate attempts | 21 |
| HTTP entry/view pages, export accuracy, access/CSRF checks and student CIA | 23 |
| Actual HTTP PG correction and staged result-import submissions | 5 |
| Exact production SQL, all-six configuration matrices, rollback and replay | 35 |
| **Total** | **244** |

PHP syntax checks passed for 363 first-party/application/test/tool files. All 29 shared-core PHP files match between portals. Whitespace/diff checks passed. Earlier attachment/Apache access tests remain documented in the initial upgrade report. The synthetic database and temporary HTTP server are removed after verification.

Repeatable verification:

```text
php tools/prepare_results_test.php
php tests/production_regulation_sql.php --database=cea_results_test_<12 hex>
php tests/results_portal_regression.php --database=cea_results_test_<12 hex> --student
php tools/sync_core.php --check
```

The verified-regulations test invokes the CIA and baseline results suites, and applies the migration/configuration only to its explicitly named disposable database. Test scripts reject the configured working database. No production deployment or real college-result acceptance run has been performed.

## Further work

Prioritize complete current-course curriculum mapping and nonstandard course entry, then policy version freezing for historical reports, broader faculty report consistency, configurable review/attendance/feedback rules, and measured query/index/queued-export improvements. The class export, reconciliation and student subject page now agree; separate legacy faculty print/analysis pages still need an inventory before all CIA representations can be claimed consistent. Staff impersonation remains outside this change, as requested.
