# T15 Release Readiness

## 1. Developer Information

| | |
|---|---|
| **Name** | Lucky Angel S. Guevarra |
| **GitHub Username** | Ether-Lucky |
| **Technology Stack** | PHP with Laravel (PHP 8.5.9, Laravel 13.24.0, SQLite) |
| **T15 Branch** | `feature/t15-system-hardening` |

## 2. Release Candidate Baseline

State of the application on `main` after T14, before any T15 change.

| Item | Working before T15? | Notes |
|---|---|---|
| Application startup | Yes | `php artisan serve` |
| `/health` | Yes | Returns `{"status":"ok", ...}` |
| Dashboard | Yes | `/dashboard` |
| Resident Management | Yes | `/residents` |
| Service Request Management | Yes | `/service-requests` |
| Starter through T14 automated tests | Yes | 170 tests passed |

Nothing was failing in the baseline test suite. The defects listed in section 3
were not covered by any existing test; they were found by reviewing the system
against the nine hardening areas.

## 3. Hardening Changes

| Area | Issue or Risk Found | Action Taken | Files Changed | Test/Evidence |
|---|---|---|---|---|
| 1. Full regression behavior | Reviewed - no defect identified. | None. | - | Full suite, two consecutive runs (section 5). |
| 2. Invalid and missing input | A form field sent as an array (for example `first_name[]=x`) was correctly rejected by validation, but the rejected value was flashed back as "old input". Re-rendering the form then failed with HTTP 500 because an array cannot be printed into a text input. Affected Resident registration, Resident update and Service Request submission. | Only the text values the controller actually read are flashed back after a failed submission. | `app/Http/Controllers/ResidentController.php`, `app/Http/Controllers/ServiceRequestController.php` | `test_malformed_identifiers_and_non_text_input_are_handled_safely` (failed with 500 before the fix). |
| 2. Invalid and missing input | Route identifiers accepted any run of digits. A number far beyond the integer range still reached the controller and relied on integer saturation to end in "not found". | Identifiers are constrained once, for every route, to 1-18 digits (`Route::pattern`). Anything else is answered with 404 by the router. | `routes/web.php` | Same test: `abc`, `1.5`, `-1`, `1 OR 1=1` and a 26-digit number all return 404 on every identifier route. |
| 3. Persistence integrity | Reviewed - no defect identified. Rejected registration, update, submission and transition leave rows unchanged. | Added a regression test for the least-covered case. | - | `test_rejected_update_leaves_an_inactive_resident_completely_unchanged`. |
| 4. HTTP and mutation safety | Reviewed - no defect identified. Every state change uses POST or PUT, is followed by a redirect, and GET on a mutation URL returns 405. | Added a whole-system read-only test. | - | `test_repeated_read_only_requests_never_change_persisted_state`. |
| 5. Query and database safety | Search terms were bound as parameters (no injection), but `%` and `_` inside a term were still interpreted as LIKE wildcards. Searching for `%` returned every record and `a_i` matched "Maria", which contradicts "the search term appears within the field" (T05, T11). | Search terms are escaped so they are matched literally, in both the Resident and the Service Request search. | `app/Support/LikePattern.php` (new), `app/Repositories/ResidentRepository.php`, `app/Repositories/ServiceRequestRepository.php` | `test_search_terms_containing_sql_wildcards_are_matched_literally` (failed before the fix). All T05 and T11 search tests still pass unchanged. |
| 5. Query and database safety | Dashboard aggregates reviewed - no defect identified. Counts use `COUNT(*)` and `GROUP BY`; recent requests use `ORDER BY ... LIMIT 5`. | None. | - | Existing `test_dashboard_counts_use_aggregate_queries_only`. |
| 6. Workflow and lifecycle protection | Reviewed - no defect identified. Transitions are decided only by `ServiceRequestStatusService`; the interface derives its buttons from the same transition table. Residents cannot be reactivated or deleted. | Added a cross-feature test. | - | `test_resident_deactivation_blocks_new_requests_but_keeps_existing_ones_working`. |
| 7. Interface and navigation | The application entry page (`/`) had no link to any module, so the Dashboard, Residents and Service Requests pages could only be reached by typing a URL. | Added Dashboard, Residents and Service Requests links to the entry page. Its existing content is unchanged. | `resources/views/index.blade.php` | `test_every_module_is_reachable_from_the_home_page_and_from_each_other` (failed before the fix). |
| 7. Interface and navigation | Template output reviewed - no defect identified. All Resident and Service Request values are rendered with Blade's escaped `{{ }}` output; no raw output is used. | None. | - | Code review. |
| 8. Test reliability | Reviewed - no defect identified. Every database test class migrates its own temporary SQLite file and deletes it afterwards; no test relies on a hardcoded generated ID or on another test running first. | None. | - | Two consecutive full runs pass (section 5). |
| 9. Repository and release hygiene | Reviewed - no defect identified (section 7). | None. | - | Section 7. |

## 4. Five New T15 Hardening Tests

All in `tests/Feature/SystemHardeningTest.php`.

### Test 1

- **Test Name:** `test_malformed_identifiers_and_non_text_input_are_handled_safely`
- **Category:** 1 - Invalid or Malformed Input
- **What It Verifies:** Non-numeric, negative, decimal, injection-shaped and
  out-of-range identifiers return 404 on every Resident and Service Request
  identifier route, for GET, PUT and POST. Query and form values sent as arrays
  are treated as missing text: searches still render, submissions are rejected
  with validation feedback, and the form re-renders with status 200. Afterwards
  every row of both tables is identical to before.
- **Why It Adds Value Beyond Existing Tests:** Earlier tests only used a
  well-formed but nonexistent ID and a single non-numeric ID. No test sent a
  value of the wrong type, which is what exposed the HTTP 500 defect.

### Test 2

- **Test Name:** `test_rejected_update_leaves_an_inactive_resident_completely_unchanged`
- **Category:** 2 - Rejected Mutation Protects Persistence
- **What It Verifies:** An update with four valid fields, one invalid contact
  number, and extra `id` and `status=Active` fields is rejected for an Inactive
  Resident. The record is re-read and compared in full, including
  `updated_at`: no field was partially saved, the Resident was not reactivated,
  and no other record was created.
- **Why It Adds Value Beyond Existing Tests:** Existing invalid-update tests
  use an Active Resident and do not attempt to change `id` or `status`. This
  combines atomic update (T06) with the one-way Resident lifecycle (T07).

### Test 3

- **Test Name:** `test_repeated_read_only_requests_never_change_persisted_state`
- **Category:** 3 - Read-Only Request Safety
- **What It Verifies:** Nineteen GET requests covering every page of the
  system (home, health, dashboard, lists, searches, filters, forms, details,
  missing records and an unsupported status filter) are each issued three
  times. Both tables are then compared row-for-row with their earlier state,
  timestamps included.
- **Why It Adds Value Beyond Existing Tests:** T12, T13 and T14 each check
  their own pages once. This is the only test that covers the whole system in
  one pass, repeats the requests, and includes the error paths.

### Test 4

- **Test Name:** `test_resident_deactivation_blocks_new_requests_but_keeps_existing_ones_working`
- **Category:** 4 - Cross-Feature Regression
- **What It Verifies:** A Resident submits a request and is then deactivated
  through the interface. A second submission is rejected and creates nothing;
  the existing request is still found by search and filter, can still be
  started and completed, and keeps its Resident ID; the Resident stays
  Inactive; and the dashboard counts are correct after each step.
- **Why It Adds Value Beyond Existing Tests:** It crosses T07, T09, T10, T11,
  T12, T13 and T14 in one scenario. No earlier test checks that deactivating a
  Resident leaves that Resident's existing Service Requests fully workable.

### Test 5

- **Test Name:** `test_search_terms_containing_sql_wildcards_are_matched_literally`
- **Category:** 5 - Repeatability or Boundary Behavior
- **What It Verifies:** Search terms made of `%`, `_` and the escape character
  match only records that literally contain them, for both Resident and
  Service Request searches, while ordinary partial searches still work.
- **Why It Adds Value Beyond Existing Tests:** Every earlier search test uses
  plain letters. This is the boundary where text input meets query syntax.

An additional navigation test,
`test_every_module_is_reachable_from_the_home_page_and_from_each_other`,
guards the navigation fix in section 3.

## 5. Full Regression Test Results

- **Test command used:** `php artisan test`
- **Run 1 result:** PASSED - 176 tests, 1350 assertions
- **Run 2 result:** PASSED - 176 tests, 1350 assertions (run immediately after Run 1)
- **Total passing tests reported by the test framework:** 176 of 176

The 176 tests are the 98 Starter-T10 tests, 18 for T11, 15 for T12, 21 for
T13, 18 for T14 and 6 for T15. No earlier test was deleted, skipped or changed.

## 6. Manual Release Verification

Verified against the running application (`php artisan serve`) with an empty,
freshly migrated SQLite database. Each flow was performed over real HTTP with
session cookies and CSRF tokens, and the pages were viewed in a browser.

| Verification | Result | Notes |
|---|---|---|
| Application starts | PASS | `/` returns 200 and links to all three modules. |
| /health works | PASS | `"status":"ok"`. |
| Dashboard loads | PASS | Empty system shows 0 for every metric and "No Service Requests available." |
| Resident listing/search works | PASS | ` sAnT ` finds Santos only; blank search lists all. |
| Valid Resident registration works | PASS | Redirects to the list with "Resident registered successfully."; status Active. |
| Invalid Resident registration is rejected | PASS | Feedback shown, typed values kept, nothing saved. |
| Resident update works | PASS | Valid change saved; invalid change rejected and record unchanged. |
| Resident deactivation works and record remains visible | PASS | Status Inactive, still listed and searchable; repeating it reports "already Inactive". |
| Valid Service Request submission for Active Resident works | PASS | Listed as Pending with a generated ID. |
| Missing/Inactive Resident submission is rejected | PASS | "Resident not found." / "Inactive Residents cannot submit new Service Requests."; total stays the same. |
| Service Request search/filter/details work | PASS | Text, status, combined, and details with the full description. |
| Valid Service Request workflow works | PASS | Pending -> In Progress -> Completed; Pending -> Cancelled. |
| Invalid/terminal transition is rejected | PASS | Pending -> Completed and Completed -> Cancelled rejected; status unchanged. |
| Dashboard reflects Resident/Service Request changes | PASS | Active/Inactive, Pending/In Progress and Open counts moved as expected. |
| Read-only GET operations do not modify persistence | PASS | Lists identical after repeated reads; GET on a mutation URL returns 405. |

Also verified: nonexistent and malformed Resident and Service Request URLs
return the application's 404 page with no stack trace.

## 7. Repository Hygiene Review

| Checked | Result |
|---|---|
| Debug output | Checked. No `dd`, `dump`, `var_dump` or `print_r` in `app`, `resources`, `routes` or `tests`. |
| Temporary files | Checked. None tracked. Test databases are created in the system temp directory and deleted by each test. |
| Generated build/runtime artifacts | Checked. `vendor`, `node_modules`, `public/build` and compiled views are ignored and untracked. |
| Accidental local database files | Checked. `database/.gitignore` ignores `*.sqlite*`; none are tracked. |
| Commented-out abandoned code | Checked. None found. |
| Secrets/credentials | Checked. `.env` is ignored; only `.env.example` with placeholder values is tracked. |
| Broken navigation/links | Checked. One defect found and fixed (section 3, area 7). |
| Direct SQL parameter binding where applicable | Checked. The only raw SQL fragments are the `LOWER(...) LIKE LOWER(?)` search conditions and the `status, COUNT(*)` aggregate selection. Every user value is bound with `?`; no input is concatenated into SQL. |

Cleanup performed: none was required. The empty starter placeholders
(`app/Services/ResidentService.php`, `app/Services/RequestService.php`,
`app/Support/Validators.php`) were left in place because they came with the
starter repository.

## 8. Known Limitations

These are outside the T01-T15 requirements and were deliberately not implemented.

- There is no authentication or authorization; anyone who can reach the
  application can use every page.
- Lists are not paginated.
- The Service Request form takes a Resident ID typed by the user rather than a
  Resident picker, as permitted by T13.
- A Resident cannot be reactivated and records cannot be deleted, by design.
- `.env.example` ships with `APP_DEBUG=true` for development. A production
  deployment must set `APP_DEBUG=false` so that unexpected errors do not show
  framework debug pages.
- Text search is case-insensitive for ASCII letters. SQLite's `LOWER()` does
  not fold accented letters such as `Ñ`.

No known release-blocking limitations identified within the defined CSMS scope.

## 9. Release Candidate Statement

**READY FOR T16 FINAL EXAM**
