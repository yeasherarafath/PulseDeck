PulseDeck — Other Modules Detailed Browser Test Cases

This document covers browser-based testing for all PulseDeck modules except the detailed notification test plan.

Covered modules:

Public Status Page
Monitoring Engine
Services & Request Builder
Incidents
Maintenance Windows
Public Subscribers
Settings
Dashboard
Public API
Authentication, Roles & Permissions
Audit Log
Queue / Scheduler / Operations
Cross-module and regression scenarios
Security and browser UI scenarios
0. Test Environment and Test Data

Before running the tests, prepare a QA environment with:

Test services

Create these endpoints:

/200
Returns HTTP 200

/201
Returns HTTP 201

/204
Returns HTTP 204

/400
Returns HTTP 400

/401
Returns HTTP 401

/403
Returns HTTP 403

/404
Returns HTTP 404

/429
Returns HTTP 429

/500
Returns HTTP 500

/slow
Returns 200 after configurable delay

/timeout
Does not respond before configured timeout

/json-ok
{
  "status": "ok",
  "version": "1.0.0",
  "data": {
    "active": true,
    "count": 5
  }
}

/json-fail
{
  "status": "error",
  "version": "1.0.0",
  "data": {
    "active": false,
    "count": 0
  }
}

/invalid-json
Returns malformed JSON

/headers
Returns known response headers

/redirect
Returns 302 to /200

/redirect-chain
Redirects through multiple URLs

/redirect-private
Redirects to a private/internal address

/echo
Returns method, query, headers and body received
Test users

Create:

Super Admin
Status Manager
Status Viewer
Test groups
Websites
APIs
Infrastructure
Test services
Website
API
AI API
Database

Use different check intervals and thresholds during testing.

1. Public Status Page
PUB-001 — Open /
Steps
Log out.
Open /.
Expected
Status page loads.
No redirect to /status.
Public layout is displayed.
Branding is correct.
No admin controls are visible.
PUB-002 — Open /status
Steps

Open /status.

Expected
Page loads successfully.
Content matches /.
No unexpected redirect.
Same service groups and statuses are displayed.
PUB-003 — Compare / and /status
Steps
Open /.
Record visible services/statuses/incidents.
Open /status.
Compare.
Expected

Both pages represent the same public status payload.

PUB-004 — No services
Steps
Remove or hide all public services.
Open /.
Expected
Page does not crash.
Empty state is shown.
Overall status has a sensible value.
No broken service-group section appears.
PUB-005 — One operational service
Steps
Create public service pointing to /200.
Run a check.
Open /.
Expected
Service is shown.
Status badge = Operational.
"Checked X ago" is displayed.
Overall status = Operational.
PUB-006 — Multiple groups
Steps

Create:

Websites
  Website

APIs
  Main API
  AI API
Expected
Groups appear separately.
Services appear under correct group.
Group ordering follows sort_order.
Service ordering follows sort_order.
PUB-007 — Service hidden from public page
Steps
Set is_public = false.
Open /.
Expected
Service is not shown.
PUB-008 — Hidden service direct URL

Open:

/status/services/{private-slug}
Expected




Private service information is not exposed.
PUB-009 — Invalid service slug

Open:

/status/services/not-existing
Expected




No exception page.
No sensitive database information.
PUB-010 — Active incident banner
Steps

Create an active incident.

Expected
Red incident card appears.
Incident title is correct.
Link opens incident page.
Correct service is shown where applicable.
PUB-011 — No active incidents

Resolve all incidents.

Expected
Active incident card disappears or shows the intended empty state.
PUB-012 — Scheduled maintenance card

Create future maintenance.

Expected
Blue maintenance card appears.
Title is correct.
Date/time is correct.
Affected service information is correct.
PUB-013 — Completed maintenance

Allow maintenance to finish.

Expected
Upcoming maintenance card no longer shows the completed window.
Historical information remains where applicable.
PUB-014 — Overall Operational state

All services operational.

Expected

Banner:

All Systems Operational

or equivalent configured wording.

PUB-015 — Overall Degraded state

At least one service degraded, no outage.

Expected

Overall state = Degraded.

PUB-016 — Partial outage priority

Create a failed service while another service is operational.

Expected

Overall state = Partial Outage according to implemented severity rules.

PUB-017 — Major outage priority

Create a major outage state.

Expected

Overall state = Major Outage.

PUB-018 — Maintenance priority

Put all public services under maintenance.

Expected

Overall state = Maintenance.

PUB-019 — Verify status priority

Create combinations:

Operational + Degraded
Operational + Partial Outage
Degraded + Partial Outage
Maintenance + Operational
Maintenance + Degraded
Expected priority
Major Outage
↓
Partial Outage
↓
Degraded
↓
Maintenance
↓
Operational

Verify the actual banner follows this order.

2. Public Status Caching
CACHE-001 — Public payload caching
Steps
Open /.
Change a backend-visible service state.
Reload immediately.
Expected
Cached response may remain for the configured 30-second cache period.
After cache expiration/invalidation, new status appears.
CACHE-002 — Status change invalidation
Steps
Service = Operational.
Open public page.
Trigger a status transition.
Reload.
Expected

Cache is invalidated when the current status flips.

CACHE-003 — Unchanged status
Steps
Run multiple checks without changing service status.
Open public page repeatedly.
Expected

No unnecessary public cache invalidation occurs.

CACHE-004 — Service detail cache

Open:

/status/services/{slug}
Expected

Service detail data loads correctly from its service-specific cache.

CACHE-005 — Service detail update

Change current service status.

Expected

After invalidation/expiry, the service detail page reflects the new state.

3. Public Service Detail Page
SERVICE-PUB-001 — Valid service page
Expected

Page shows:

service name
current status
uptime percentage
response time
uptime history
response time chart
recent incidents
SERVICE-PUB-002 — Uptime window

Change:

uptime_window_days
Expected

Displayed history uses configured window.

SERVICE-PUB-003 — 90-day history

With default 90 days:

Expected
90 daily bars or available days.
Exact percentages shown on tooltip.
Mobile view remains usable.
SERVICE-PUB-004 — No uptime data

Service has no checks.

Expected
No division-by-zero.
Sensible empty state.
SERVICE-PUB-005 — Recent incidents

Create more than five incidents.

Expected

Service page shows only the configured five recent incidents.

SERVICE-PUB-006 — Response-time history

Generate multiple successful checks with different response times.

Expected

Response-time chart contains the correct values.

4. Live Refresh
REFRESH-001 — Polling

Open /.

Wait for configured refresh interval.

Expected

Browser sends refresh request.

REFRESH-002 — Status changes during open page
Open status page.
Change service state.
Wait for polling interval.
Expected

Page updates without a full browser reload.

REFRESH-003 — Polling throttled

Open refresh endpoint repeatedly.

Expected

After exceeding:

60 requests / minute

rate limiting applies.

REFRESH-004 — Polling error

Temporarily make refresh request fail.

Expected
Existing page remains usable.
JavaScript does not enter an uncontrolled retry loop.
No visible application crash.
5. Public Dark / Light Mode
THEME-001 — Default theme
Expected

Uses configured theme_default.

THEME-002 — Toggle light → dark
Expected

Entire public interface switches correctly.

THEME-003 — Toggle dark → light
Expected

All components return to light mode.

THEME-004 — Refresh persistence

Toggle dark mode and refresh.

Expected

User preference remains.

THEME-005 — Service page

Check service detail page in both modes.

Expected

Charts, badges and text remain readable.

THEME-006 — Incident page

Check incident page in both modes.

Expected

Timeline remains readable.

6. Public Branding
BRAND-001 — App name

Change app name.

Expected

New name appears on public pages.

BRAND-002 — Light logo

Upload light logo.

Expected

Correct logo appears in light mode.

BRAND-003 — Dark logo

Upload dark logo.

Expected

Correct logo is used in dark mode where intended.

BRAND-004 — Favicon

Upload favicon.

Expected

Browser tab shows favicon.

BRAND-005 — Remove logo

Remove logo.

Expected

Fallback branding works.

7. Public Subscription
SUB-001 — Open subscribe form
Expected

Form is visible when subscriptions are enabled.

SUB-002 — Subscriptions disabled

Disable public subscriptions.

Expected
Subscribe UI is hidden/disabled.
POST attempts are rejected according to implementation.
SUB-003 — Valid subscription

Submit:

user@example.com
Expected
Subscriber created.
Verification is required.
Verification token exists.
User is not considered verified yet.
SUB-004 — Invalid email

Submit:

abc
user@
Expected

Validation error.

SUB-005 — Duplicate subscription

Submit same address twice.

Expected

Existing subscriber is reused/updated according to firstOrCreate.
No accidental duplicate subscriber rows.

SUB-006 — Verify subscriber

Open verification URL.

Expected
verified_at populated.
Verification token becomes null.
Subscriber becomes eligible for alerts.
SUB-007 — Invalid verification token

Open random token.

Expected

Verification fails safely.

SUB-008 — Reuse verification token

Open successful verification URL again.

Expected

No second verification action occurs.

SUB-009 — Unsubscribe

Open persistent unsubscribe link.

Expected

Subscriber is deleted or deactivated according to implementation.
Future alerts stop.

SUB-010 — Invalid unsubscribe token
Expected

No subscriber is affected.

SUB-011 — Verification token cannot unsubscribe

Try replacing unsubscribe token with verification token.

Expected

Rejected.

SUB-012 — Rate limiting

Submit subscription more than 10 times/minute from same client.

Expected

Throttle applies.

8. Service CRUD — General
SVC-001 — Create service

Fill only valid required fields.

Expected

Service created.

SVC-002 — Empty name
Expected

Validation error.

SVC-003 — Empty URL
Expected

Validation error.

SVC-004 — Invalid URL

Use:

abc
example
https://
Expected

Rejected.

SVC-005 — Duplicate slug

Create service with existing slug.

Expected

Rejected.

SVC-006 — Edit service

Change:

name
description
group
URL
Expected

Changes persist.

SVC-007 — Delete service
Expected

Confirmation shown.
After confirmation, service is removed.

SVC-008 — Cancel delete
Expected

Nothing is deleted.

SVC-009 — Pause service

Click Pause.

Expected
is_active = false
Scheduled checks stop.
Service remains visible if public.
SVC-010 — Resume service
Expected
is_active = true
Future checks resume.
SVC-011 — Public/private toggle
Expected

Changing visibility updates public page access correctly.

9. Service Sorting
SORT-001 — Group sort order

Create groups with:

sort_order = 10
sort_order = 20
sort_order = 30
Expected

Displayed in ascending order.

SORT-002 — Service sort order

Set service sort order values.

Expected

Services are ordered correctly inside groups.

10. Request Builder — Method

Create a service for each:

GET
POST
PUT
PATCH
DELETE
HEAD
OPTIONS
REQ-001 to REQ-007
Expected

/echo receives exactly the configured request method.

11. Query Parameters
REQ-Q-001 — Single parameter
page=1
Expected

Server receives page=1.

REQ-Q-002 — Multiple parameters
Expected

All parameters arrive correctly.

REQ-Q-003 — Empty value
Expected

Empty value is sent according to configured behavior.

REQ-Q-004 — Special characters

Use:

hello world
a+b
a&b
বাংলা
Expected

URL encoding is correct.

REQ-Q-005 — Edit query parameter
Expected

New value is used.

REQ-Q-006 — Delete parameter
Expected

Parameter disappears from request.

12. Headers
REQ-H-001 — Common header preset

Select:

Accept
Expected

Header name is inserted correctly.

REQ-H-002 — Multiple headers

Configure:

Accept
Content-Type
X-Request-ID
Expected

All are sent.

REQ-H-003 — Custom header

Add:

X-PulseDeck-Test: true
Expected

Header reaches /echo.

REQ-H-004 — Edit header
Expected

New value reaches destination.

REQ-H-005 — Delete header
Expected

Header is removed.

REQ-H-006 — Duplicate header

Add same header twice.

Expected

Behavior is defined and consistent.

13. Request Body
REQ-B-001 — JSON body

Send:

{
  "name": "PulseDeck",
  "active": true
}
Expected

Server receives valid JSON.

REQ-B-002 — Nested JSON
Expected

Nested structure remains intact.

REQ-B-003 — Numeric JSON value

Verify that:

{"count": 5}

does not become:

{"count": "5"}

unless intended.

REQ-B-004 — Form body
Expected

Form fields arrive correctly.

REQ-B-005 — URL encoded body
Expected

Fields are correctly encoded.

REQ-B-006 — Raw body
Expected

Exact raw body reaches destination.

REQ-B-007 — Invalid JSON
Expected

Form validation blocks invalid JSON.

14. Authentication
REQ-AUTH-001 — No authentication
Expected

No auth header generated.

REQ-AUTH-002 — Bearer token
Expected

Destination receives:

Authorization: Bearer <token>
REQ-AUTH-003 — Basic auth
Expected

Correct credentials sent.

REQ-AUTH-004 — API key
Expected

Configured header is sent.

REQ-AUTH-005 — Custom authentication
Expected

Custom headers work.

REQ-AUTH-006 — Secret masking
Expected

Credentials are never displayed in clear text after saving.

15. Test Request — Saved Configuration
TEST-001

Save service, open Test Request.

Expected

Real request executes.

TEST-002 — Test successful request
Expected

Modal displays:

HTTP status
response time
final URL
redirect count
assertion result
TEST-003 — Test failed request

Target /500.

Expected

Failed result is visible.

TEST-004 — Test timeout

Target /timeout.

Expected

Timeout error displayed.

TEST-005 — Test invalid configuration
Expected

Validation/error response is displayed rather than worker/application crash.

TEST-006 — Test does not create history

Run Test Request.

Open service history.

Expected

No new status_checks row from Test Request.

TEST-007 — Test does not fire events

Run a failing Test Request.

Expected
No incident event.
No service failure notification.
No recovery event.
16. Test Request — Unsaved Configuration
TEST-UNSAVED-001

Change URL without saving.

Click Test.

Expected

Request uses unsaved values.

TEST-UNSAVED-002

Change headers without saving.

Expected

Unsaved header is sent.

TEST-UNSAVED-003

Change body without saving.

Expected

Unsaved body is sent.

TEST-UNSAVED-004

Close modal.

Reload form.

Expected

Unsaved changes are not silently persisted.

17. Assertion Builder
ASSERT-001 — Expected status 200
Expected

/200 passes.

ASSERT-002 — Wrong expected status

Configure expected:

201

against /200.

Expected

Assertion fails.

ASSERT-003 — Multiple expected status codes

Configure:

200,201

Target /201.

Expected

Pass.

ASSERT-004 — Response body contains
Expected

Existing string passes.

ASSERT-005 — Response body not contains
Expected

Missing string passes.

ASSERT-006 — Response body equals
Expected

Exact match succeeds only when content matches.

ASSERT-007 — Regex

Test matching and non-matching regex.

Expected

Correct result in assertion_result.

18. JSON Assertions
JSON-001 — Equals
$.status = ok
Expected

Pass.

JSON-002 — Wrong value
$.status = failed
Expected

Fail.

JSON-003 — Exists
$.version exists
Expected

Pass.

JSON-004 — Not exists

Use a missing path.

Expected

Pass.

JSON-005 — Nested path
$.data.active = true
Expected

Pass.

JSON-006 — Numeric comparison

Test:

greater-than
less-than
Expected

Correct result.

JSON-007 — Contains

Test string/array contains operator.

Expected

Correct result.

JSON-008 — Invalid JSON

Target /invalid-json.

Expected

JSON assertions fail clearly.

JSON-009 — Multiple JSON assertions

Create:

$.status = ok
$.data.active = true
$.data.count > 1
Expected

All pass.

JSON-010 — One assertion failure
Expected

Overall assertion result = failed.
Failed assertion is identified.

19. Response Header Assertions
HEADER-ASSERT-001 — Header exists
Expected

Pass when returned.

HEADER-ASSERT-002 — Header missing
Expected

Failure.

HEADER-ASSERT-003 — Header equals
Expected

Correct value passes.

HEADER-ASSERT-004 — Wrong header value
Expected

Failure.

HEADER-ASSERT-005 — Multiple response-header assertions
Expected

All evaluated independently.

20. Advanced Request Options
ADV-001 — Request timeout

Set low timeout against /slow.

Expected

Timeout occurs within expected range.

ADV-002 — Connection timeout

Use unreachable/slow destination.

Expected

Connection timeout is respected.

ADV-003 — Follow redirects ON

Target /redirect.

Expected

Final /200 response is received.

ADV-004 — Follow redirects OFF
Expected

Redirect response is treated according to configured logic.

ADV-005 — Max redirects

Create redirect chain longer than limit.

Expected

Request fails safely.

ADV-006 — SSL verification ON

Valid SSL destination.

Expected

Pass.

ADV-007 — Invalid certificate
Expected

Fails when verification is ON.

ADV-008 — SSL verification OFF
Expected

Request can continue where allowed by configuration.

ADV-009 — HTTP version

Test supported configured values.

Expected

Request remains valid.

ADV-010 — Custom User-Agent
Expected

Destination receives exact User-Agent.

21. SSRF Protection
SSRF-001 — localhost

Try:

http://localhost
Expected

Blocked.

SSRF-002 — 127.0.0.1
Expected

Blocked.

SSRF-003 — Private 10.x
Expected

Blocked.

SSRF-004 — Private 172.16/12
Expected

Blocked.

SSRF-005 — Private 192.168.x
Expected

Blocked.

SSRF-006 — Link-local

Try:

169.254.x.x
Expected

Blocked.

SSRF-007 — IPv6 localhost
Expected

Blocked.

SSRF-008 — DNS hostname resolving to private address
Expected

Blocked.

SSRF-009 — Redirect to private address

Public URL redirects to internal IP.

Expected

Second hop blocked.

SSRF-010 — Redirect chain

Public → public → private.

Expected

Request stops at private destination.

SSRF-011 — Unsupported scheme

Try:

file://
ftp://
gopher://
data:
Expected

Blocked.

22. Response Size Limit
SIZE-001

Target /large-response.

Expected

Response is stopped at configured maximum.

SIZE-002

Verify check result.

Expected

Error indicates oversized response.
Worker remains healthy.

23. Monitoring Scheduler
MON-001 — Due service

Create active service where:

next_check_at <= now
Expected

Next scheduler run queues it.

MON-002 — Future service

Set next_check_at in future.

Expected

It is not queued early.

MON-003 — Paused service

Set is_active = false.

Expected

No automatic check is dispatched.

MON-004 — Resume service

Set active again.

Expected

Future checks resume.

MON-005 — Multiple due services

Create >100 due services if possible.

Expected

Chunking works and all eligible records eventually get queued.

MON-006 — Different intervals

Configure:

1 min
5 min
10 min
Expected

Each service follows its own schedule.

24. Manual Check
CHECK-001 — Check Now

Click Check Now.

Expected
Job is queued.
Service receives a fresh result.
CHECK-002 — Check Now failed service
Expected

Failure result appears in history.

CHECK-003 — Check Now paused service

Verify expected implementation.

Expected

Manual check works only if manual checks are intentionally allowed while paused; otherwise it is rejected.

CHECK-004 — Double Check Now click

Click quickly twice.

Expected

Lock prevents overlapping duplicate checks where intended.

25. Monitoring Lock
LOCK-001

Trigger two checks for same service simultaneously.

Expected

status-check:{id} lock prevents concurrent execution.

LOCK-002

Wait for lock expiry.

Expected

Future checks work normally.

26. Invalid Stored Configuration

This requires creating an invalid saved configuration in a QA environment.

INVALID-CONFIG-001

Break JSON/assertion/configuration stored for a service.

Run Check Now/scheduled check.

Expected
A failed StatusCheck row is written.
Visible error explains invalid configuration.
Worker continues.
Other services remain healthy.
27. Status Check History
HISTORY-001

Open service show.

Expected

25 checks per page.

HISTORY-002

Generate >25 checks.

Expected

Pagination works.

HISTORY-003

Failed check

Expected

Displays:

status
HTTP code
response time
error
failed assertions
HISTORY-004

Successful check

Expected

No misleading error.

28. Check Record Data

For one check, verify:

success
status
http_status
response_time
connect_time
final_url
redirect_count
response_size
error_type
error_message
assertion_result
checked_at
Expected

Values match the actual request.

29. Status Calculation Order

Test each condition separately.

CALC-001 — Maintenance

Service under maintenance.

Expected

Status = Maintenance regardless of normal check result.

CALC-002 — Transport error
Expected

Transport error wins over later checks.

CALC-003 — Unexpected HTTP status
Expected

HTTP status failure.

CALC-004 — Failed assertion

HTTP 200 but assertion fails.

Expected

Failed.

CALC-005 — Response too slow

Above failure threshold.

Expected

Failed.

CALC-006 — Warning threshold

Above warning but below failure threshold.

Expected

Degraded.

CALC-007 — Normal response
Expected

Operational.

30. Flap Protection

Configure:

min_failed_checks_down = 3
FLAP-001
Fail
Expected

Check row = failed.
Public/service current state does not flip down yet.

FLAP-002
Fail
Fail
Expected

Still below down threshold.

FLAP-003
Fail
Fail
Fail
Expected

Service becomes down.

FLAP-004
Fail
Fail
Success
Expected

Failure sequence resets.

FLAP-005
Fail
Fail
Fail
Success
Expected

Recovery can occur according to recovery configuration.

31. Per-Service Threshold Override
THRESH-001

Global:

min_failed_checks_down = 3

Service:

min_failed_checks_down = 1
Expected

One failure changes status for this service.

THRESH-002

Remove service override.

Expected

Global setting applies.

32. Incident Automatic Creation
INC-AUTO-001

Configure:

failure_threshold = 3
auto_create_incidents = true

Trigger three consecutive failures.

Expected
Incident created.
Status = Investigating.
Initial update created.
Only one active incident exists.
INC-AUTO-002

Only two failures.

Expected

No incident.

INC-AUTO-003

Failure sequence broken

Fail
Fail
Success
Fail
Expected

No automatic incident.

INC-AUTO-004

Already active incident

Continue failures.

Expected

No duplicate incident.

33. Incident Automatic Resolution
INC-RES-001

Configure:

recovery_threshold = 2
auto_resolve_incidents = true

Then:

Success
Success
Expected

Incident resolves.

INC-RES-002

Only one success

Expected

Incident stays active.

INC-RES-003

Success then fail

Expected

Recovery count resets.

INC-RES-004

Already resolved incident

Continue healthy checks.

Expected

No duplicate resolve operation.

34. Manual Incident
INC-MAN-001

Create incident from admin.

Expected

Created successfully.

INC-MAN-002

Create with service.

Expected

Service link correct.

INC-MAN-003

Create multi-service incident

Set:

service_id = null
Expected

Incident works without one specific service.

INC-MAN-004

Invalid title

Expected

Validation.

INC-MAN-005

Impact minor/major/critical

Expected

Correct badge and saved value.

35. Incident Status Transitions

Test:

Investigating
Identified
Monitoring
Resolved
Expected
Status changes correctly.
Timeline update appears.
Public incident page updates.
INC-STAT-001 — Resolved transition

Change:

Monitoring → Resolved
Expected

resolved_at populated.

INC-STAT-002 — Already resolved

Attempt another resolve.

Expected

No duplicate resolve event.

36. Incident Updates
INC-UPD-001

Add update.

Expected

Timestamped update appears.

INC-UPD-002

Add long update.

Expected

Stored and rendered correctly.

INC-UPD-003

Special HTML/script content.

Expected

Escaped/sanitized.

INC-UPD-004

Multiple updates.

Expected

Chronological timeline.

37. Public Incident Page
INC-PUB-001

Open valid incident slug.

Expected

Full timeline appears.

INC-PUB-002

Invalid slug.

Expected


INC-PUB-003

Resolved incident.

Expected

Resolved timestamp displayed.

INC-PUB-004

Multi-service incident.

Expected

Does not show an incorrect single-service association.

38. Incident Public Timeline

Create:

Investigating
Identified
Monitoring
Resolved
Expected

All updates appear in correct order with timestamps and statuses.

39. Maintenance CRUD
MAIN-001 — Create

Enter:

title
description
start
end
services
Expected

Created as Scheduled.

MAIN-002 — No service

Submit with zero services.

Expected

Validation error.

MAIN-003 — End before start
Expected

Validation error.

MAIN-004 — Same start/end
Expected

Rejected if minimum duration is required.

MAIN-005 — Edit
Expected

Changes persist.

MAIN-006 — Cancel

Click Cancel.

Expected

Status = Cancelled.

MAIN-007 — Delete
Expected

Maintenance is removed according to configured behavior.

40. Maintenance State Sync

Create a maintenance starting soon.

MAIN-STATE-001

Before start:

Expected

Status = Scheduled.

MAIN-STATE-002

After start:

Expected

Status = Active.

MAIN-STATE-003

After end:

Expected

Status = Completed.

MAIN-STATE-004

Cancelled before start:

Expected

Never becomes Active.

41. Maintenance Monitoring Behavior
MAIN-MON-001

Put service under maintenance and target /500.

Expected

Service check result is treated as Maintenance rather than outage.

MAIN-MON-002

During maintenance, verify uptime.

Expected

Maintenance period is excluded from uptime calculation.

MAIN-MON-003

Maintenance ends while endpoint is healthy.

Expected

Service becomes Operational.

MAIN-MON-004

Maintenance ends while endpoint is failing.

Expected

Next check evaluates normally and can become Failed.

42. Maintenance Public Page
MAIN-PUB-001

Future maintenance.

Expected

Blue maintenance card.

MAIN-PUB-002

Affected service during maintenance.

Expected

Scheduled Maintenance badge.

MAIN-PUB-003

Unrelated service.

Expected

No maintenance badge.

MAIN-PUB-004

More than 10 maintenance windows.

Expected

Public page follows configured 10-item limit.

43. Dashboard
DASH-001 — Service counts

Create services in all states.

Expected

Dashboard counts match actual records.

DASH-002 — Problem services

Create >10 problematic services.

Expected

Top 10 problem services shown.

DASH-003 — Active incidents

Create >5 active incidents.

Expected

Five displayed.

DASH-004 — Recent checks

Generate many checks.

Expected

Latest 10 shown.

DASH-005 — 24-hour average response

Create known response times.

Expected

Displayed average matches actual data.

DASH-006 — Heartbeat

With worker running:

Expected

Heartbeat = Running.

DASH-007 — Stale heartbeat

Stop worker or let heartbeat expire.

Expected

Dashboard shows Stale.

44. Public API — Status
API-001

GET:

/api/status
Expected

HTTP 200 when enabled.

Response envelope:

{
  "ok": true,
  "data": {}
}
API-002 — API disabled

Set:

public_api_enabled = false
Expected

API returns configured disabled response/status.

API-003 — /services
Expected

Public services only.

API-004 — Private service leakage

Create private service.

Expected

It never appears in public API.

API-005 — Service endpoint

Open:

/api/status/services/{slug}
Expected

Correct service data and 30-day uptime.

API-006 — Invalid slug
Expected


API-007 — Incidents endpoint
Expected

Latest 25 incidents at most.

API-008 — Only public data
Expected

No internal credentials, headers, tokens, audit details, or private services.

45. Public API Rate Limit
API-RATE-001

Send >60 requests/minute.

Expected

Rate limit response.

API-RATE-002

Wait until limit resets.

Expected

Requests work again.

46. API and Page Consistency

Change service state.

Compare:

Public page
Public API
Service page
Badge
Expected

All agree after cache invalidation/update.

47. Authentication
AUTH-001

Open admin page while logged out.

Expected

Login screen.

AUTH-002

Valid login.

Expected

Dashboard opens.

AUTH-003

Invalid password.

Expected

Login rejected.

AUTH-004

Logout.

Expected

Session ends.

AUTH-005

Back button after logout.

Expected

Protected content is unavailable.

48. Password Reset
RESET-001

Open forgot password.

Expected

Form works.

RESET-002

Valid email.

Expected

Reset email sent.

RESET-003

Unknown email.

Expected

No account disclosure.

RESET-004

Open valid reset link.

Expected

Password can be changed.

RESET-005

Invalid/expired token.

Expected

Rejected.

RESET-006

Use reset link twice.

Expected

Second use fails.

49. Role — Super Admin

Test:

dashboard
monitoring
services
groups
incidents
maintenance
settings
notifications
audit logs
Expected

Full access.

50. Role — Status Manager
ROLE-MGR-001
Expected

Can manage:

services
groups
incidents
maintenance
monitoring

Cannot manage settings.

ROLE-MGR-002

Directly open settings URL.

Expected

403/permission denied.

ROLE-MGR-003

Try setting update request manually.

Expected

Denied by permission middleware.

51. Role — Status Viewer
ROLE-VIEW-001

Can view dashboard.

ROLE-VIEW-002

Can view service list.

ROLE-VIEW-003

Can view monitoring.

ROLE-VIEW-004

Can view incidents.

ROLE-VIEW-005

Can view maintenance.

Expected

Read-only.

ROLE-VIEW-006

Try create service.

Expected

Denied.

ROLE-VIEW-007

Try edit service directly by URL.

Expected

Denied.

ROLE-VIEW-008

Try delete service.

Expected

Denied.

52. Sidebar Permission Visibility

For each role:

Expected

Users only see navigation items they can access.

Also test direct URLs separately to ensure hiding a menu item is not the only protection.

53. Settings — General

Test:

app_name
tagline
base_url
timezone
theme_default
admin_prefix
SET-GEN-001

Change app name.

Expected everywhere.

SET-GEN-002

Change tagline.

Expected on public/admin places where used.

SET-GEN-003

Change base URL.

Expected generated public links use new URL.

SET-GEN-004

Change timezone.

Expected displayed timestamps use configured timezone.

SET-GEN-005

Change theme default.

Expected new visitors use new default.

SET-GEN-006

Change admin prefix.

Expected admin routes use new prefix.

54. Settings — Monitoring

Test defaults:

check interval
timeout
failure threshold
recovery threshold
min failed checks down
incident auto-create
incident auto-resolve
Expected

New services inherit defaults where designed.

Existing service-specific overrides remain intact.

55. Settings — Public

Test:

public page
public API
subscriptions
badge
public refresh interval
history window
SET-PUB-001

Disable public page.

Expected

Public page blocked/disabled according to implementation.

SET-PUB-002

Disable API.

Expected

API unavailable.

SET-PUB-003

Disable subscriptions.

Expected

Subscription UI/API blocked.

SET-PUB-004

Disable badge.

Open /status/badge.svg.

Expected

Badge unavailable/disabled response.

SET-PUB-005

Change refresh interval.

Expected

Browser uses new interval.

SET-PUB-006

Change history window.

Expected

Public service page uses new window.

56. Settings — Mail

Test:

mail_enabled
SMTP host
port
username
password
encryption
from address
Expected

Each value persists and mail test behaves accordingly.

57. Settings — Alerts

Test all eight:

notify_on_incident_created
notify_on_incident_resolved
notify_on_incident_updated
notify_on_maintenance_ended
notify_on_maintenance_started
notify_on_service_degraded
notify_on_service_failed
notify_on_service_recovered

For every setting:

ON → event can notify
OFF → event does not notify
58. Settings — Webhook

Test:

webhook_default_url
webhook_secret
webhook_timeout
webhook_retries
Expected

All persist and affect webhook behavior.

59. Settings — Retention

Set short test retention values.

Run cleanup.

Expected

Only records older than configured retention are removed.

Test separately:

checks
daily_stats
audit
deliveries
60. Partial-Safe Settings Save
SET-PARTIAL-001

Submit valid General fields and an invalid SMTP port.

Expected
Invalid SMTP field rejected.
Valid independent settings are preserved according to implementation.
Existing encrypted secrets are not blanked.
SET-PARTIAL-002

Submit only one setting.

Expected

Other settings remain unchanged.

SET-PARTIAL-003

Unchecked checkbox omitted from request.

Expected

Full-form behavior turns checkbox OFF where applicable.

SET-PARTIAL-004

Partial request with checkbox omitted.

Expected

Partial-update behavior does not unintentionally alter unrelated keys.

61. Encrypted Settings

Test:

webhook secret
Expected
Stored encrypted.
Never shown in plaintext after save.
Blank editing field keeps existing secret.
Changing secret replaces old secret.
62. Branding Uploads
FILE-001

Upload valid logo.

Expected

Preview appears.

FILE-002

Upload dark logo.

Expected

Correct preview.

FILE-003

Upload favicon.

Expected

Correct preview.

FILE-004

Invalid file.

Expected

Rejected safely.

FILE-005

Oversized file.

Expected

Rejected according to limits.

FILE-006

Remove logo.

Expected

Fallback displays.

63. Audit Log

Perform:

Service create
Service update
Service delete
Incident create
Incident update
Incident resolve
Maintenance create
Maintenance update
Maintenance cancel
Channel create
Channel delete
Rule create
Rule delete
Subscriber state change
Settings update
Expected each time

Audit record contains:

action
model/type
before
after
user
IP
timestamp
64. Audit Secret Redaction

Change:

SMTP password
Webhook secret
API credentials
Expected

Audit log never exposes secret values.

65. Audit Read-only Behavior

Viewer opens audit page.

Expected

Can read only if permission grants it.
Cannot modify/delete records.

66. Queue / Failed Job Monitoring
OPS-001

Create a failing background job.

Open Monitoring.

Expected

Failed job count increases.

OPS-002

Fix and retry failed job.

Expected

Queue metrics update.

OPS-003

Queued jobs present.

Expected

Queue count is displayed.

67. Worker Heartbeat
OPS-004

Worker running.

Expected

Heartbeat = Running.

OPS-005

Worker stopped long enough.

Expected

Heartbeat = Stale.

OPS-006

Restart worker.

Expected

Heartbeat returns to Running.

68. status:recalculate

Run recalculate after manually creating an incorrect state in QA.

Expected

Service current state is reconstructed from latest checks according to rules.

69. status:calculate-daily

Generate known check data.

Run daily aggregation.

Expected

status_daily_stats contains correct:

total_checks
successful_checks
failed_checks
uptime_percentage
avg_response_time
min_response_time
max_response_time
70. Cleanup

Create old test records.

Run cleanup.

Expected

Records beyond configured retention disappear.

Recent records remain.

71. CLI Debug Check Visible from Browser

Run:

status:test --service=ID
Expected

Result matches browser Check Now behavior.

Secrets are redacted.

72. Timezone Testing

Change timezone to:

UTC
Asia/Dhaka
America/New_York

Create:

service check
incident
maintenance
Expected

All browser-displayed timestamps consistently use configured timezone.

73. Date Boundary Test

Create maintenance around midnight.

Example:

23:59 → 00:01
Expected

State transitions and displayed dates are correct.

74. Browser Refresh During Form Submission

Start creating/editing a service.

Refresh during validation/error.

Expected

No corrupted record.

75. Double Submit Protection

Rapidly click:

Save service
Save incident
Save maintenance
Save settings
Check Now
Test Request
Expected

No duplicate records/jobs unless deliberately allowed.

76. XSS Tests

Enter:

<script>alert(1)</script>

into:

service name
description
incident title
incident update
maintenance title
maintenance description
group name
settings text
footer text
Expected

Script never executes.

77. HTML Injection

Use:

<img src=x onerror=alert(1)>
Expected

Escaped/sanitized.

78. SQL-like Input

Search:

' OR 1=1 --
Expected
No query error.
Normal search behavior.
No unexpected records.
79. CSRF

Try POST actions without valid CSRF.

Test:

service create/update/delete
incident create/update
maintenance create/cancel
settings update
test email
test webhook
subscribe
Expected

Rejected.

80. Unauthorized Direct Requests

Log in as Viewer.

Attempt manager-only URLs manually.

Expected

403/permission response.

Repeat while logged out.

Expected

Authentication required.

81. Public Data Leakage

Inspect public:

HTML
JSON API
SVG badge
service pages
incident pages
refresh response
Must never contain
API keys
Bearer tokens
Passwords
SMTP password
Webhook secret
Private service URLs
Internal database data
Audit information
Admin-only settings
82. Multi-Service Incident + Public Status

Create incident with:

service_id = null
Expected
Public incident page works.
Overall status changes appropriately.
No incorrect service detail link is generated.
83. Service Deleted After Incident
Create incident.
Delete related service.
Expected
No application error.
Incident history remains readable.
Public incident page handles missing service safely.
Historical notification data remains usable.
84. Service Deleted After Maintenance
Create maintenance.
Link service.
Delete service.
Expected
Pivot/reference cleanup behaves correctly.
Maintenance page does not crash.
85. Service Deleted After Rules
Create service.
Create service-specific rule.
Delete service.
Expected
Related rule is removed through cascade or handled safely.
No orphan rule appears.
86. Group Deleted With Services

Try deleting a group containing services.

Expected

Behavior is explicit:

blocked,
cascaded,
or reassigned.

No orphan service.

87. Public Page While Monitoring Is Stale

Stop worker.

Expected

Public page does not pretend that a very old check is fresh.

Verify behavior based on your stale-state design.

88. Monitoring Stale + Existing Incident

If monitoring becomes stale while an incident is open:

Expected
Existing incident remains visible.
No fake recovery occurs.
No new false incident is created simply because the worker stopped.
89. Monitoring Worker Recovery
Stop worker.
Confirm Monitoring page = Stale.
Start worker.
Wait for heartbeat.
Expected

Monitoring returns to Running.
Scheduled checks resume.

90. Cross-Module Test — Service Failure

Full flow:

Create service
→ Configure /500
→ Enable auto incident
→ Enable service failure handling
→ Run checks
→ Failure threshold reached
→ Service becomes Failed
→ Incident created
→ Public page shows outage
→ Incident public page works
→ Dashboard reflects problem
→ Monitoring shows failed check
91. Cross-Module Test — Recovery
Failed service
→ Change endpoint to /200
→ Recovery threshold reached
→ Service becomes Operational
→ Incident resolves
→ Public page updates
→ Dashboard updates
→ Uptime history updates
92. Cross-Module Test — Maintenance
Create maintenance
→ Public card appears
→ Maintenance becomes Active
→ Service shows Maintenance
→ /500 does not create normal outage state
→ Uptime excludes maintenance
→ Maintenance completes
→ Next check evaluates normally
93. Cross-Module Test — Public API Consistency

After each major service-state transition compare:

Dashboard
Admin service
Public page
Service detail
Public API
Badge
Expected

All reflect the same current state after cache invalidation.

94. Cross-Module Test — Public Subscription
Subscribe
→ Verify
→ Create service failure
→ Incident created
→ Subscriber eligible for incident notifications
→ Resolve incident
→ Subscriber remains verified
→ Unsubscribe
→ Trigger next incident
→ Subscriber receives nothing
95. Cross-Module Test — Role Safety

As Viewer:

View dashboard
View monitoring
View service
View incident

Then attempt:

Create service
Change settings
Create incident
Create maintenance
Expected

Read works where permitted.
Write operations are blocked.

96. Cross-Module Test — Settings Change

Change:

public_refresh_seconds
history_window
theme_default
base_url
Expected

Affected public behavior changes without restarting the application.

97. Cross-Module Test — Service Configuration Change
Service currently checks /200.
Change URL to /500.
Save.
Click Check Now.
Expected

New URL is immediately used.

98. Cross-Module Test — Request Builder + Assertions

Configure:

POST
JSON body
Authorization
expected 200
JSON $.status = ok
Expected

Test Request, scheduled checks, history, and status calculation all use the same request definition.

99. Cross-Module Test — Unsaved Builder

Change:

URL
header
body
assertion

without saving.

Click Test Request.

Expected

All unsaved values are used.

Saved service configuration remains unchanged afterward.

100. Cache + State Regression

Perform:

Operational
→ Degraded
→ Failed
→ Incident
→ Recovery
→ Operational

After every transition:

Check:

Public page
Refresh API
Service detail
API
Badge
Dashboard
Expected

No surface remains permanently stale beyond configured cache behavior.

101. Browser Responsive Testing

Test:

360px
390px
768px
1024px
1366px
1920px

For:

public status
service page
incident page
admin dashboard
service form
request builder
assertion builder
monitoring page
settings
tables
modals
Expected
no horizontal overflow except intentionally scrollable sections
forms remain usable
buttons remain accessible
tables scroll correctly
mobile navigation works
charts remain readable
102. Browser Compatibility

Test where available:

Chrome
Firefox
Edge
Safari

Focus on:

Tabs
Modals
Dropdowns
File upload
Charts
Date/time inputs
Theme switch
Tables
AJAX polling
103. Keyboard Accessibility

Test:

Tab navigation
Enter
Escape
Arrow keys in dropdowns
Modal focus
Form submit
Close buttons
Expected

All major actions remain usable without a mouse.

104. Validation Regression

Every form should be tested with:

Empty required value
Invalid URL
Invalid slug
Duplicate slug
Negative integer
Zero interval
Too large timeout
Invalid JSON
Invalid regex
Invalid date
End before start
Invalid email
Invalid port
Invalid timezone
Invalid enum value

Expected:

validation message
correct field indication
no partial corruption
105. Final Browser Acceptance Suite

Before release, run this exact end-to-end sequence:

1. Login as Super Admin
2. Create service group
3. Create public service
4. Configure GET /200
5. Run Test Request
6. Run Check Now
7. Verify check history
8. Verify public page
9. Verify public API
10. Verify badge

11. Configure JSON API request
12. Add headers
13. Add authentication
14. Add JSON assertion
15. Test unsaved configuration
16. Save configuration
17. Run scheduled check

18. Change endpoint to /slow
19. Verify Degraded
20. Change endpoint to /500
21. Reach failure threshold
22. Verify Failed
23. Verify incident creation
24. Verify incident public page

25. Change endpoint to /200
26. Reach recovery threshold
27. Verify incident resolution
28. Verify status recovery

29. Create maintenance
30. Verify public maintenance card
31. Verify service maintenance state
32. Verify uptime exclusion
33. End maintenance
34. Verify normal monitoring

35. Create verified subscriber
36. Test subscriber lifecycle
37. Unsubscribe
38. Verify future alert exclusion

39. Test email settings
40. Test webhook settings
41. Verify notification module separately

42. Test dashboard
43. Test Monitoring page
44. Stop worker
45. Verify heartbeat becomes Stale
46. Restart worker
47. Verify Running

48. Test roles
49. Test audit logs
50. Test public API rate limit
51. Test SSRF protection
52. Test XSS protection
53. Test CSRF
54. Test mobile browser
55. Test dark mode
56. Test branding
57. Test cleanup
58. Final regression
106. Release Blocking Cases

The following should block a production release if they fail:

SEC-001   SSRF to localhost blocked
SEC-002   SSRF to private IP blocked
SEC-003   Redirect to private IP blocked
SEC-004   Unsupported URL scheme blocked
SEC-005   API keys never exposed publicly
SEC-006   SMTP password never exposed
SEC-007   Webhook secret never exposed
SEC-008   CSRF protection works
SEC-009   Viewer cannot write
SEC-010   Private services never appear publicly

MON-001   Scheduled checks execute
MON-002   Worker heartbeat works
MON-003   Failed job handling works
MON-004   Check lock prevents duplicate execution
MON-005   Invalid configuration creates visible failed check

STATUS-001  Status calculation priority correct
STATUS-002  Flap protection works
STATUS-003  Recovery threshold works
STATUS-004  Maintenance overrides normal outage state
STATUS-005  Maintenance excluded from uptime

INC-001   Automatic incident creation works
INC-002   Automatic resolution works
INC-003   Duplicate incident prevention works
INC-004   Manual multi-service incident works
INC-005   Incident timeline remains correct

PUB-001   Public page works
PUB-002   Private service not exposed
PUB-003   Public API does not leak private data
PUB-004   Cache invalidation after state change
PUB-005   Service page works
PUB-006   Badge works

AUTH-001  Authentication works
AUTH-002  Role permissions enforced server-side
AUTH-003  Direct unauthorized URLs rejected

SET-001   Settings persist
SET-002   Partial-safe update works
SET-003   Encrypted secrets remain protected
SET-004   Branding uploads work

SUB-001   Double opt-in works
SUB-002   Unverified subscriber receives no alerts
SUB-003   One-click unsubscribe works
SUB-004   Verification token cannot be reused

UI-001    Mobile public page usable
UI-002    Mobile admin usable
UI-003    Dark/light mode usable
UI-004    Forms remain usable at narrow widths
107. Regression Matrix

Run these after every major code change:

Area	Minimum regression
Public status	Home, service, incident, refresh
Monitoring	Check Now, scheduled check, failed check, recovery
Request builder	GET, POST, header, auth, JSON body
Assertions	HTTP, response time, body, JSON, headers
Incidents	Auto-create, manual create, update, resolve
Maintenance	Schedule, active, completed, uptime
Subscribers	Subscribe, verify, unsubscribe
Settings	General, Monitoring, Public, Mail, Alerts
Dashboard	Counts, heartbeat, recent checks
API	Status, services, incidents, rate limit
Auth	Login, logout, role permissions
Audit	Create/update/delete records
Security	SSRF, XSS, CSRF, secret leakage
UI	Mobile, dark mode, browser refresh
108. Recommended Browser Test Organization

Store these as separate browser suites:

plans/browser/

01-public-status.md
02-monitoring.md
03-services-request-builder.md
04-assertions.md
05-incidents.md
06-maintenance.md
07-subscribers.md
08-settings.md
09-dashboard.md
10-public-api.md
11-auth-permissions.md
12-audit.md
13-operations.md
14-security.md
15-cross-module.md
16-regression.md

Keep the notification test plan separate:

17-notifications.md

That way a change to the request builder does not force you to search through a huge single document to find the relevant browser cases.