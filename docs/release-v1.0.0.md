# CSMS v1.0.0 Release Notes

## 1. Release

| | |
|---|---|
| **Application** | Community Services Management System |
| **Release** | v1.0.0 |
| **Release Type** | Final Course Release |
| **Technology** | PHP 8.5 with Laravel 13, SQLite |

## 2. Release Summary

CSMS v1.0.0 is a server-rendered Laravel application for barangay community
services staff. It keeps a register of Residents and lets staff record, find
and process the Service Requests those Residents make. Business rules decide
who may submit a request and how a request may move through its workflow. A
dashboard summarises the current state of both areas from aggregate database
queries. Every page shares one navigation bar. The running release reports
itself as version 1.0.0 on every page and through `/health`.

## 3. Resident Management Capabilities

- **Register** a Resident with first name, last name, address, contact number
  (`09XXXXXXXXX`) and email. New Residents start as Active and receive a
  database-generated ID.
- **Validation:** invalid information is rejected with a message for each
  field, the entered values are kept on the form, and nothing is saved.
- **List and search:** Residents are listed by last name, first name, then ID.
  Search matches part of the first or last name, ignores letter case and
  surrounding spaces, and treats `%` and `_` as ordinary characters. A blank
  search lists everyone.
- **Update** the five editable fields. ID and status cannot be changed through
  the edit form, and an invalid update leaves the stored record unchanged.
- **Deactivate** an Active Resident. The record is kept and stays visible and
  searchable as Inactive. Repeating the action is safe. There is no deletion
  or reactivation.

## 4. Service Request Management Capabilities

- **Submit** a request with Resident ID, service type, description and date
  requested. The request starts as Pending with a generated ID.
- **Eligibility:** submission is rejected with a clear message when the
  information is invalid, the Resident does not exist, or the Resident is
  Inactive.
- **List, search and filter:** requests are listed newest date first, then
  highest ID first. Text search covers service type and description (partial
  and case-insensitive). A status filter can be combined with the search.
- **Details:** each request has a page showing all of its information,
  including the full description.
- **Controlled workflow:**

  | Current status | Available actions |
  |---|---|
  | Pending | Start Processing (to In Progress), Cancel |
  | In Progress | Complete, Cancel |
  | Completed | none (terminal) |
  | Cancelled | none (terminal) |

  Only the actions allowed for the current status are shown. Any other
  transition sent directly to the server is still rejected and leaves the
  stored status unchanged.

## 5. Dashboard Capabilities

- Resident totals: Total, Active, Inactive.
- Service Request totals: Total, Pending, In Progress, Completed, Cancelled.
- Open Service Requests: Pending plus In Progress, calculated rather than
  stored.
- The five most recent Service Requests, each linking to its details page.
- All figures come from `COUNT` and `GROUP BY` queries on the stored records,
  so they always match the data. Viewing the dashboard changes nothing.

## 6. Quality and Hardening

**T15 hardening:**
- **Array input crash:** form fields sent as arrays no longer cause an
  HTTP 500 error.
- **Literal search:** search terms are matched literally, so `%` and `_` are
  no longer wildcards.
- **Home page navigation:** the home page now links to every module.
- **Identifiers:** malformed or oversized record IDs are answered with 404.
- **Tests:** six hardening tests were added.

**T16 final verification:**
- **Release identity:** set in one place, `config/csms.php`.
- **Where the version appears:** `/health` and a shared page footer.
- **Tests:** an integration test now covers the whole system, along with
  release-version tests.
- **Regression:** the full suite was run twice in a row.
- **Manual acceptance:** every module was checked by hand against the running
  application.

## 7. Final Verification

| Check | Result |
|---|---|
| Final Regression Run 1 | PASS - 182 tests, 1450 assertions |
| Final Regression Run 2 | PASS - 182 tests, 1450 assertions |
| Application Startup | PASS |
| /health | PASS - `{"status":"ok","application":"Community Services Management System","short_name":"CSMS","version":"1.0.0"}` |
| Dashboard | PASS |
| Resident Management | PASS |
| Service Request Management | PASS |
| Final Release Version | PASS - 1.0.0 on `/health`, the home page and every page footer |

## 8. Known Limitations

Carried forward from T15. All are outside the defined CSMS scope.

- **No access control:** there is no authentication or authorization; anyone
  who can reach the application can use every page.
- **No pagination:** lists are not paginated.
- **Resident ID entry:** the Service Request form takes a typed Resident ID
  rather than a Resident picker.
- **Development debug setting:** `.env.example` ships with `APP_DEBUG=true`
  for development. A production deployment must set `APP_DEBUG=false`.
- **Case-insensitive search:** text search ignores case for ASCII letters
  only. SQLite's `LOWER()` does not fold accented letters such as `Ñ`.

No known release-blocking limitations remain within the defined CSMS v1.0.0
scope.
