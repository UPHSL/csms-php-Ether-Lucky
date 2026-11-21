# Final Examination Checkpoint

## 1. Developer Information

Name: Lucky Angel S. Guevarra
GitHub Username: Ether-Lucky
Technology Stack: PHP with Laravel
T16 Branch: feature/t16-final-release
Final Release Version: 1.0.0

## 2. My Final Release Implementation

The release identity is defined once, in a new configuration file, `config/csms.php`. It
holds the application's full name, its short name `CSMS`, and the version `1.0.0`.
Configuration files are where a Laravel application keeps settings that its controllers and
views read, so the version sits beside the framework's own configuration instead of inside a
controller, a domain service, or a template. The values are written in the file rather than
read from `.env`, because the release version belongs to the code being deployed and must not
change from one machine's environment file to another.

`HealthController` builds the `/health` response from `config('csms.*')`. The response
reports the status `ok`, the application name, `CSMS`, and the version, and nothing else. The
shared Blade layout used by the Dashboard, Residents and Service Requests pages reads the same
configuration for its footer (`v1.0.0`), its brand label and its page titles. `HomeController`
passes the same values to the starter home page.

Because every place that shows the version asks the configuration for it, no page or endpoint
holds a copy of its own. Moving to 1.0.1 means editing one line, and a test proves this by
changing the configured value and checking that `/health`, the home page and the layout all
change with it.

## 3. Files I Changed

- `config/csms.php` (new): the single source of the release identity (name, short name, version 1.0.0).
- `app/Http/Controllers/HealthController.php`: `/health` now reports status, application, short name and version from the configuration instead of hardcoded `0.1.0` values.
- `app/Http/Controllers/HomeController.php`: the starter home page takes its application name and version from the same configuration.
- `resources/views/layouts/app.blade.php`: adds a footer showing the application name and `v1.0.0` to every module page, and reads the brand label and page-title suffix from the configuration.
- `tests/Feature/CsmsApplicationTest.php`: the two Starter tests that pinned the old `0.1.0` version now expect the `1.0.0` release (see section 6).
- `tests/Feature/ReleaseIdentityTest.php` (new): automated release-version verification.
- `tests/Feature/FinalExaminationTest.php` (new): my Final Examination integration test.
- `docs/release-v1.0.0.md` (new) and `docs/final-checkpoint.md` (new): release documentation.

## 4. My Final Examination Test

Test Name: `test_rejected_operations_never_reach_persistence_and_dashboard_matches_stored_records`
(in `tests/Feature/FinalExaminationTest.php`)

Modules/Responsibilities Covered:
- Resident validation, registration and update (T02, T04, T06)
- Resident deactivation (T07)
- Service Request submission and Resident eligibility (T09)
- The status workflow (T10)
- Querying (T05, T11)
- The browser interface (T12, T13)
- Dashboard aggregation (T14)

All of these run against real SQLite persistence.

Starting State: An empty, freshly migrated database, confirmed through the dashboard showing
zero Residents and zero Service Requests.

Operations Performed: All through the application's HTTP routes, as a browser would send them:
- Residents:
  - Register two Residents and attempt one invalid registration.
  - Update one Resident's address, then attempt an invalid update.
- Service Requests:
  - Submit four requests.
  - Deactivate the second Resident, then attempt a submission for them.
- Workflow:
  - Valid paths: Pending to In Progress to Completed, Pending to Cancelled, and Pending to In Progress.
  - Rejected transitions: Completed to Cancelled, Cancelled to In Progress, and Pending to Completed.

Expected Final State:
- Residents:
  - Exactly two are stored: one Active, and one Inactive with the updated address. The
    invalid update left nothing behind.
  - The rejected registration does not exist.
- Service Requests:
  - Exactly four are stored, and the rejected submission does not exist.
  - Their statuses are Completed, Cancelled, Pending and In Progress. The In Progress request
    still belongs to the now-Inactive Resident.
- Dashboard: every count equals the number of stored rows counted independently in the test.
  Open is 2, and the recent list is in date-then-ID order.
- Queries: search and filter return the same final state.

Why This Test Is Meaningful: An isolated unit test proves that one rule works when it is called
directly. This test proves something none of them can: that across a realistic mix of
operations, every rejected operation (from four different modules) left no trace in the
database, and that the reporting layer built on top still agrees exactly with what is stored.
A defect anywhere in the chain makes it fail. I confirmed this by temporarily breaking four
separate rules, one at a time, and running the test after each:
- Letting Inactive Residents submit.
- Counting only Pending as Open.
- Allowing Completed to Cancelled.
- Partially saving an invalid update.

The test failed in every case.

## 5. My Final Technical Decision

Decision: Keep the release identity in a dedicated `config/csms.php` file with fixed values,
and have the controllers and the layout read it with `config()`.

Alternative Considered:
- Reusing Laravel's existing `APP_NAME` setting and adding an `APP_VERSION` variable to `.env`.
- A PHP constant on a class.

Reason for My Decision:
- **Why not `.env`:** it is not committed to Git and differs on every machine. This
  project's `.env` still says `APP_NAME=Laravel`, so the release identity would silently
  depend on each developer's local file. A tagged release must report the same version
  wherever it runs.
- **Why not a class constant:** it would work, but it would place release metadata inside
  application code that has nothing to do with it, and Blade templates would need to import
  that class.
- **Why a config file:** it is the standard Laravel location for application settings. It
  is available everywhere through `config()`, is included by `php artisan config:cache`, and
  can be overridden inside a test. That last point let me prove there is only one source.

## 6. Problem or Verification Issue Encountered

Problem: After `/health` and the home page started reading the configured version, the full
suite failed with two errors, both in the original Starter tests
(`CsmsApplicationTest::test_home_page_displays_application_name` and
`test_health_endpoint_returns_expected_payload`). Both expected the text `0.1.0`.

Cause or Investigation: The application was correct; it now reported `1.0.0`. The two tests
were written in Sprint 0 to pin the release identity of that time, `0.1.0`. Because the course
rules forbid changing old expected values to hide a regression, I checked whether this was a
regression or a requirement change. T16 explicitly requires the running application to report
1.0.0 instead of the starter version, so the old expectation itself is what changed. Every
other assertion in those tests (the application name, the sprint text, the technology, the
`ok` status) still passes unchanged.

Resolution: I changed only the expected version in those two assertions, from `0.1.0` to
`1.0.0`. I wrote it as a literal rather than reading it from the configuration, so the tests
still pin the release independently and would catch an accidental version change.

I also confirmed one related concern. Laravel's unused starter view `welcome.blade.php` prints
the framework's version, not the application's. I checked that no route renders it. The manual
check found no page or endpoint still serving `0.1.0`, so there is no conflicting version
anywhere in the running application.

## 7. Final Regression Results

Test Command: `php artisan test`
Final Regression Run 1: PASS - 182 tests, 1450 assertions
Final Regression Run 2: PASS - 182 tests, 1450 assertions (run immediately after Run 1)
Total Passing Tests Reported: 182

These are the 176 Starter-T15 tests plus 6 new T16 tests: 5 release-identity tests and 1 Final
Examination test. No earlier test was deleted, skipped or weakened.

## 8. Tools and References Used

- Course T16 ticket and the existing CSMS repository documentation (`docs/t15-release-readiness.md`)
- Laravel documentation: configuration, Blade templates, HTTP tests
- PHPUnit through `php artisan test`, and Laravel Pint for formatting
- Git and GitHub CLI (`gh`) for the branch, Pull Request, tag and release
- AI coding assistant (Claude, via Claude Code). It was used to:
  - Read the T16 ticket and draft the configuration file, the controller and layout changes,
    and both new test files.
  - Run the two regression runs, the rule-breaking check described in section 4, and the
    manual verification against the running application.
  - Draft this document and the release notes.

  I reviewed the changes and I am responsible for them.

## 9. Final Release Declaration

CSMS v1.0.0 - READY FOR RELEASE
