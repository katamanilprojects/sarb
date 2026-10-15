# Attainment and optional AI release — 2026.10.08.2

This release extends the existing college-specific portals. It does not remove the examination workflow or require a college to use it. Existing feature switches remain as configured; the new AI module is disabled by default. Separate installations remain the deployment boundary: shared-database multi-college tenancy is not implemented.

## What changed

| Existing behaviour | Updated behaviour |
|---|---|
| SEE entry mainly assumed preset paper patterns. | Existing presets remain; regulation/type profiles and subject overrides support different maxima, grade ranges/points and choice groups. |
| Results ingestion relied on the examination module. | `attainmentimport.php` previews and commits external CSV evidence independently of result publication. It matches enrolled roll number and course code. |
| A missing curriculum link could prevent a valid offering from resolving. | Missing, stale or context-mismatched links are ignored. An explicit CSV maximum or configured profile supplies missing assessment maxima; no historical mapping is guessed. |
| Raw and scaled scores were not consistently retained. | Imports retain original marks/maxima, scaled final marks/maxima, question evidence, source and policy hashes, and the import batch. |
| Alternative-question totals could be chosen differently for different COs. | An option is selected once for each student. Selected questions feed both the total and CO calculation. Multiple CO mappings share a question's marks equally and record that strategy. Existing preset caps remain. |
| Mixed entry modes could use the first student's mode for everyone. | Detailed, aggregate and grade-derived evidence are handled individually. Estimated and measured counts are reported separately. |
| Missing scores and unimported students could disappear or appear as zeros. | Explicit zero is an assessed zero. Blank scores are missing. Unimported enrolled students appear in missing coverage. Percentages use evaluated students and incomplete coverage is provisional. |
| Blueprint regeneration discarded a marks grid. | Revisions require confirmation/reason when marks exist, archive the previous blueprint/grid/totals, preserve retained questions' scores, validate revised maxima, and invalidate detailed totals until reconsolidated. |
| Result synchronization could replace another source silently. | Existing marks require an explicit replacement and reason; source grades/statuses and before/after evidence are retained atomically. |
| Some PDF grade statistics used fixed 70-mark ranges and inferred pass outcomes. | PDF output uses configured grade bands and reported letter grades. Official pass rates use supplied PASS/FAIL statuses, with the evaluated denominator shown. |
| Grade points were cast to integers in both portals. | Fractional points survive import, database storage and student GPA calculation. There is no hard-coded ten-point restriction. |
| AI tables existed without an operational workflow. | Optional local Ollama / Chat Completions API adapters, superadmin provider configuration, task consent, evidence-only jobs, a bounded CLI worker, failure/retry handling, lease recovery and scoped human review are implemented. |

## Simple configuration and use

1. Superadmin → **Attainment profiles**. Select the exact regulation ID/programme and subject type. A subject override takes priority over the type profile; a type profile takes priority over `all`; mapped curriculum supplies the initial maxima when available. Blank basic fields inherit. Approved college rules must be supplied; no sample profile is seeded.
2. Keep existing modules enabled or disabled as the college requires. Enable **SEE marks** and grant the required role's view/edit/export actions when accepting an attainment pilot. Result publication is a separate optional module.
3. Faculty → existing SEE hub → **Import results for attainment**. HOD and Academic Section dashboards expose the same scoped import when permitted. Choose a course, then aggregate/grade evidence or a configured external question component. Download its template, upload, review the preview and confirm the import. Invalid matching rows prevent the whole commit. Other course codes are skipped and counted.
4. A question-wise import needs every configured question column. Unattempted alternatives may be blank; every selected/compulsory question needs a score, including explicit `0` when appropriate. The existing grid/CSV and condensed-review paths remain available.
5. Corrections preserve old evidence and invalidate affected detailed totals. Recalculate and review before finalizing again. Stale previews, changed policies and concurrent marks changes are rejected.

Aggregate CSV columns: `RollNumber,SubCode,Marks,MaxMarks`. `Externals` / `ExternalMarks` are accepted aliases. Optional columns: `GrossMarks,GrossMax,Grade,GradePoints,Status`. Gross values scale proportionally to the final course maximum. If both gross and net are supplied, they must agree. Grade-only conversion requires configured bands and an explicit lower/midpoint/upper estimate policy. It is labelled `GRADE_ESTIMATE`, never a measured CO score.

The independent adapter currently consumes external assessment evidence. A file containing only combined internal+external `Total` must be mapped to its official external component; it is not silently treated as external marks or replaced by a grade estimate. Full combined-total-only and supplementary/revaluation attempt policies remain separate work. Existing examination CSV publication is retained for colleges using it.

Advanced profile example (illustration only; not an approved college rule):

```json
{
  "grade_bands": [
    {"grade":"F","min_percent":0,"max_percent":50,"points":0},
    {"grade":"B","min_percent":50,"max_percent":70,"points":2.5},
    {"grade":"A","min_percent":70,"max_percent":100,"points":4}
  ],
  "choice_groups": [
    {"options":[["2a","2b"],["3a","3b"]],"select":1}
  ]
}
```

Omit `choice_groups` (or set it to `null`) to retain the existing paper preset. An explicit `[]` means all questions are compulsory. A choice group may include `max_marks` to preserve a capped option. Grade intervals validate the source's grade/points mapping; the provided grade selects its interval. Grade-only estimates retain the interval and selected estimation strategy. Reported final course grades are not inferred from external marks alone.

## Optional AI operation

Superadmin → **AI providers** configures a name, provider type, base URL, installed/provider-supported model identifier, timeout and dedicated `CEA_AI_...` credential environment variable name. The secret value belongs only in the worker's environment. Ollama uses an explicit private/loopback endpoint; cloud providers require public HTTPS. Redirects, link-local endpoints, unbounded responses and incomplete/invalid output are rejected. API providers must implement the selected Chat Completions JSON protocol; arbitrary vendor APIs need additional adapters.

Enable **Optional AI assistance** only after provider acceptance. It depends on the attainment module. Faculty/HOD users opt in separately to attainment analysis or ATR proposals, review the displayed metrics, choose a provider, and explicitly queue a draft. Only curated CO summaries are transmitted: no names, roll numbers, individual scores, uploads or free-form student information. Revoking consent cancels pending/running jobs and prevents storing a late response. Revocation cannot undo a request already transmitted.

The worker is bounded and serialized per database:

```text
php tools/ai_worker.php --limit=10
```

Jobs retain input/policy hashes, reviewed source snapshots, provider configuration versions, model identifiers, returned fingerprint when available, attempts and review history. Changed provider configuration fails before transmission. Three attempts are the maximum; user-requested retries wait briefly. Stale worker leases recover. Outputs cite supplied evidence IDs and numerical prose claims are rejected; this does not establish factual correctness of narrative prose, so a department reviewer must approve a draft. Approval does not publish it or change marks, attendance, attainment policies or recorded CQI execution.

The implemented tasks use external CO attainment summaries. CIA/PO narratives, policy-document retrieval, institution-specific quality evaluations and richer approved evidence packs remain future extensions. No model was downloaded, no real provider was contacted, and no recurring scheduler was enabled. Model suitability and latency must be benchmarked on the allotted machine. A restored-backup analysis pipeline with separate read-only credentials and an approved nightly schedule is not yet implemented; the current worker reads reviewed snapshots in its configured application database and writes only AI job state/output.

Protocol references: [Ollama Chat API](https://docs.ollama.com/api/chat), [OpenAI Chat Completions API](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create). Other compatible providers require their own acceptance tests; models are not assumed interchangeable.

## Verification and deployment

The two additive migrations are `20261008_003_attainment_integrations.sql` and `20261008_004_ai_queue_guards.sql`. They were rehearsed on restored databases before local application. The migration ledger verifies immutable checksums. The earlier migrations and feature states are preserved.

The combined suite passed **166** checks, including the earlier **98** core/workflow checks, atomic failures, missing curriculum, scaling, choice attribution, fractional grades, revisions, stale evidence, consent/revocation, duplicate AI jobs and lease recovery. **Seven** real HTTP adapter checks used only a loopback fake provider. **53** existing/new HTTP route checks exercised the local isolated server. **335** first-party PHP files passed syntax checks; **13** direct Apache checks verified public sign-in pages and denial of internal/configuration/upload paths. Final verification output and source/DB backup manifests are private under `/private/tmp/cea-attainment-phase2-*`; these are development recovery artifacts, not persistent production backup storage.

Reproduce against a disposable restored database only:

```text
php tools/migrate.php --database=cea_standardisation_test_EXAMPLE --apply
php tests/ai_jobs.php --database=cea_standardisation_test_EXAMPLE
php tools/sync_core.php --check
```

The suite requires representative college data for scoped fixtures; it does not seed a generic ERP. Tests never target the configured working database. PHP upload/request size limits must support the intended CSV size (the application cap is 10 MB and 10,000 rows). Production requires a persistent private document location, retained backups, service credentials, logging/monitoring, dependency/CI work and departmental acceptance. Legacy CIA/attendance/report equivalence and comprehensive legacy permission coverage are still pending; this release is not a claim of institution-wide production readiness.

For rollback, preserve post-release academic/import/review evidence, restore the matching pre-change source/database to a separate recovery environment, and validate before switching. Do not restore a test database into the working database or drop additive tables containing new evidence.
