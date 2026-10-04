# Midterm Examination Checkpoint

## Developer Information

Name: Lucky Angel S. Guevarra
GitHub Username: Ether-Lucky
Primary Technology Stack: PHP with Laravel
T10 Branch: feature/t10-service-request-status

## My T10 Implementation

The status workflow is managed by `App\Services\ServiceRequestStatusService`, whose
`changeStatus($serviceRequestId, $requestedStatus)` method is the only T10 entry point.
It first retrieves the existing Service Request through the T09
`ServiceRequestRepository::findById()`, and returns a not-found result if nothing comes back.
The current status is read from that freshly loaded model, so the decision is always based on
what is actually persisted rather than on a value supplied by the caller. The allowed
transitions live in one constant, `ALLOWED_TRANSITIONS`, which maps every supported status to
the statuses it may move to next; a requested status that is not a key of that map is
reported as unsupported, and a supported status that is not in the current status's list is
reported as an invalid transition. Only after both checks pass does the service call
`ServiceRequestRepository::updateStatus()`, which runs a single parameter-bound
`UPDATE service_requests SET status = ? WHERE id = ?` through Eloquent and touches no other
column. Because every failure path returns before that call, an invalid request never reaches
persistence, so there is nothing to roll back. On success the service reloads the record with
`findById()` and returns it inside a `ServiceRequestStatusResult`, so the caller receives the
state that is actually stored.

## My Transition Rules

| Current status | Allowed next status |
|---|---|
| Pending | In Progress, Cancelled |
| In Progress | Completed, Cancelled |
| Completed | none (terminal) |
| Cancelled | none (terminal) |

- Pending to In Progress: allowed, staff start processing the request.
- Pending to Cancelled: allowed, a request can be withdrawn before work starts.
- In Progress to Completed: allowed, the only way to finish a request.
- In Progress to Cancelled: allowed, work can be stopped before completion.

Pending to Completed is rejected because a request must actually be processed before it can be
finished; `Completed` is only listed under `In Progress`, so a Pending request has to pass
through In Progress first.

Completed is terminal because it records a finished workflow. Its list of allowed next statuses
is empty, so every target, including Cancelled, is rejected as an invalid transition.

Cancelled is terminal because T10 does not support reopening a cancelled request. Its list is
also empty.

Same-status requests (for example Pending to Pending) are rejected as invalid transitions. No
status lists itself as an allowed next status, so the same rule that blocks other invalid
moves also blocks these, and the stored record stays unchanged.

Unsupported values such as `Approved`, `Done`, or a differently-cased `in progress` are not
keys of `ALLOWED_TRANSITIONS`, so they are rejected as unsupported before any transition check.

## Files I Changed

File: app/Services/ServiceRequestStatusService.php
Purpose: New application-layer component that retrieves the Service Request, checks the
requested status and transition rules, and asks the repository to persist valid changes only.

File: app/Services/ServiceRequestStatusResult.php
Purpose: New result object that tells the caller whether the change succeeded, or whether it
failed because the Service Request was not found, the status was unsupported, or the
transition was invalid.

File: app/Repositories/ServiceRequestRepository.php
Purpose: Added `updateStatus()`, which updates only the status column of the requested
Service Request ID. It contains no business rules.

File: tests/Feature/ServiceRequestStatusServiceTest.php
Purpose: The thirteen required T10 scenarios plus my student-designed test, run against the
real SQLite test database.

File: docs/midterm-checkpoint.md
Purpose: This checkpoint document.

## Problem I Encountered

This problem came up during T10's application verification, which my AI assistant ran
through a Git Bash script that starts `php artisan serve` and checks `/` and `/health` with curl.

Problem or error:
  The home-page check printed a broken line instead of the route and status code:

      C:/Program Files/Git/ -> 200/n

  while the `/health` check in the same script printed correctly (`/health -> 200`).
  At first glance it looked like the home route was resolving to the wrong path.

Cause:
  Git Bash on Windows (MSYS) automatically converts command-line arguments that look like
  Unix paths into Windows paths before passing them to a native program. The curl format
  string `-w "/ -> %{http_code}\n"` starts with `/`, so MSYS treated it as a path: it
  replaced `/` with the Git install folder `C:/Program Files/Git/` and turned the `\n`
  backslash into a forward slash, producing `/n`. The application itself was fine; the
  status code 200 was correct.

How I investigated it:
  I restarted the server and ran the same curl command three ways: exactly as written,
  with MSYS path conversion disabled (`MSYS_NO_PATHCONV=1`), and with a label that does not
  start with `/`. Only the original form was garbled, which showed the shell was rewriting
  the argument and that Laravel's routing was not involved.

How I resolved it:
  Prefixed the curl command in the verification script with `MSYS_NO_PATHCONV=1`, which
  prints `/ -> 200` correctly. Lesson: when a Windows shell result looks wrong, check whether
  the shell changed the command before blaming the application.

## My Student-Designed Test

Test Name: test_existing_request_can_still_be_processed_after_its_resident_is_deactivated

What the Test Verifies: A Service Request is submitted for an Active Resident, then that
Resident is deactivated with the T07 `ResidentDeactivationService`. The existing request can
still move from Pending to In Progress to Completed, the final status is read back through a
new `ServiceRequestRepository` instance, and the Resident stays Inactive with its information
unchanged.

Why I Added This Test: The ticket says T09's Active-Resident rule applies only to creating new requests, and that T10
must not block an existing request just because its Resident was later deactivated. None of the
thirteen required tests involve the Resident at all, so a T10 implementation that wrongly
re-checked Resident status would still pass every one of them. This test protects that rule,
and it also exercises two valid transitions in sequence across T07, T09 and T10 together.

## Tools and References Used

- Course T10 ticket
- Laravel documentation (Eloquent query builder updates, database testing)
- Laravel Pint
- PHPUnit through `php artisan test`
- AI coding assistant (Claude, via Claude Code): drafted the status service, result class,
  repository method, the T10 tests and a first draft of this document. It also checked the
  tests by temporarily breaking the transition rules (allowing Pending to Completed, and
  allowing any supported status) to confirm the tests fail, and ran the application
  verification where the Git Bash problem above appeared. The written explanations in this
  document were also drafted by the assistant. I remain responsible for understanding and
  explaining the submitted implementation.
