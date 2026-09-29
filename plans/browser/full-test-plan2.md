Yes. Based on your current **PulseDeck Feature Catalog**, I would test it as a full product, not just as a status page. That means testing the request builder, monitor engine, incidents, maintenance, email, webhook, subscriptions, roles, settings, audit logs, public API, badge, responsive UI, and failure cases.

Below is a browser-focused master test list you can use as a QA checklist.

# PulseDeck — Full Browser Test Plan

## 0. Test setup

Before starting, prepare these test targets.

### HTTP test endpoints

You should have a small controllable test server or test endpoint with these behaviors:

| Test endpoint     | Expected purpose                                |
| ----------------- | ----------------------------------------------- |
| `/200`            | Returns HTTP 200                                |
| `/201`            | Returns HTTP 201                                |
| `/204`            | Returns HTTP 204                                |
| `/400`            | Returns HTTP 400                                |
| `/401`            | Returns HTTP 401                                |
| `/403`            | Returns HTTP 403                                |
| `/404`            | Returns HTTP 404                                |
| `/429`            | Returns HTTP 429                                |
| `/500`            | Returns HTTP 500                                |
| `/slow`           | Delayed response                                |
| `/timeout`        | Never responds / delays beyond timeout          |
| `/json-ok`        | Valid JSON with expected values                 |
| `/json-fail`      | Valid JSON with wrong values                    |
| `/invalid-json`   | Invalid JSON                                    |
| `/headers`        | Known response headers                          |
| `/redirect`       | Redirects to another URL                        |
| `/redirect-loop`  | Infinite redirects                              |
| `/echo`           | Returns request method, headers, query and body |
| `/auth`           | Requires authentication                         |
| `/large-response` | Large response for response-size limits         |

For request-body tests, also have a POST endpoint that echoes received JSON/form fields.

### Webhook endpoint

Use a webhook capture endpoint where you can inspect:

* HTTP method
* URL
* headers
* request body
* HMAC/signature header
* timestamp
* retry attempts

### Email

Use a test mailbox where you can inspect:

* From
* To
* Subject
* HTML
* links
* unsubscribe URL
* branding
* incident information

---

# 1. Authentication and initial access

| ID       | Test                                           | Expected                              |
| -------- | ---------------------------------------------- | ------------------------------------- |
| AUTH-001 | Open admin URL while logged out                | Redirect/login shown                  |
| AUTH-002 | Login with valid credentials                   | Dashboard opens                       |
| AUTH-003 | Invalid password                               | Error shown, login rejected           |
| AUTH-004 | Empty login fields                             | Validation shown                      |
| AUTH-005 | Directly open admin dashboard while logged out | Access denied/login                   |
| AUTH-006 | Open admin service URL while logged out        | Access denied/login                   |
| AUTH-007 | Logout                                         | Session ends                          |
| AUTH-008 | Browser back after logout                      | Protected page unavailable            |
| AUTH-009 | Multiple browser tabs after logout             | Protected actions rejected            |
| AUTH-010 | Session timeout                                | Protected actions require login again |

---

# 2. Roles and permissions

You have:

* `super-admin`
* `status-manager`
* `status-viewer`

Test all three.

### Super admin

| ID       | Test                         | Expected |
| -------- | ---------------------------- | -------- |
| ROLE-001 | Open dashboard               | Allowed  |
| ROLE-002 | Create service               | Allowed  |
| ROLE-003 | Edit service                 | Allowed  |
| ROLE-004 | Delete service               | Allowed  |
| ROLE-005 | Create incident              | Allowed  |
| ROLE-006 | Edit incident                | Allowed  |
| ROLE-007 | Create maintenance           | Allowed  |
| ROLE-008 | Manage notification channels | Allowed  |
| ROLE-009 | Manage subscribers           | Allowed  |
| ROLE-010 | Open settings                | Allowed  |
| ROLE-011 | Upload branding              | Allowed  |
| ROLE-012 | Open audit logs              | Allowed  |

### Status manager

| ID       | Test                 | Expected |
| -------- | -------------------- | -------- |
| ROLE-013 | Open dashboard       | Allowed  |
| ROLE-014 | Manage services      | Allowed  |
| ROLE-015 | Manage incidents     | Allowed  |
| ROLE-016 | Manage maintenance   | Allowed  |
| ROLE-017 | View monitoring      | Allowed  |
| ROLE-018 | Open settings        | Denied   |
| ROLE-019 | Change SMTP          | Denied   |
| ROLE-020 | Change branding      | Denied   |
| ROLE-021 | Change global alerts | Denied   |

### Status viewer

| ID       | Test              | Expected |
| -------- | ----------------- | -------- |
| ROLE-022 | Open dashboard    | Allowed  |
| ROLE-023 | View services     | Allowed  |
| ROLE-024 | View incidents    | Allowed  |
| ROLE-025 | View maintenance  | Allowed  |
| ROLE-026 | View monitoring   | Allowed  |
| ROLE-027 | Create service    | Denied   |
| ROLE-028 | Edit service      | Denied   |
| ROLE-029 | Delete service    | Denied   |
| ROLE-030 | Create incident   | Denied   |
| ROLE-031 | Change settings   | Denied   |
| ROLE-032 | Send test email   | Denied   |
| ROLE-033 | Send test webhook | Denied   |

Also test direct URL access for every restricted admin page, not just hidden buttons.

---

# 3. Public status page

| ID      | Test                          | Expected                         |
| ------- | ----------------------------- | -------------------------------- |
| PUB-001 | Open `/`                      | Status page loads                |
| PUB-002 | Open `/status`                | Status page works                |
| PUB-003 | Confirm `/` does not redirect | Served directly                  |
| PUB-004 | No services configured        | Graceful empty state             |
| PUB-005 | One operational service       | Shows operational                |
| PUB-006 | Multiple services             | All displayed                    |
| PUB-007 | Service groups                | Groups displayed correctly       |
| PUB-008 | Service sort order            | Correct order                    |
| PUB-009 | Private service               | Not exposed publicly             |
| PUB-010 | Public service                | Visible publicly                 |
| PUB-011 | Paused service                | Correctly handled                |
| PUB-012 | Overall all operational       | Correct banner                   |
| PUB-013 | One degraded service          | Overall degraded state           |
| PUB-014 | Partial outage                | Correct overall state            |
| PUB-015 | Major outage                  | Correct overall state            |
| PUB-016 | Maintenance only              | Maintenance state                |
| PUB-017 | Last updated time             | Visible and reasonable           |
| PUB-018 | "checked X ago"               | Correct per service              |
| PUB-019 | Active incident card          | Visible                          |
| PUB-020 | Scheduled maintenance card    | Visible                          |
| PUB-021 | No active incident            | Incident section hidden/empty    |
| PUB-022 | No maintenance                | Maintenance section hidden/empty |
| PUB-023 | Service detail link           | Opens correct service            |
| PUB-024 | Incident timeline link        | Opens correct incident           |
| PUB-025 | Browser refresh               | State remains correct            |

---

# 4. Public service detail page

| ID      | Test                    | Expected              |
| ------- | ----------------------- | --------------------- |
| PUB-030 | Open valid service slug | Correct service       |
| PUB-031 | Invalid slug            | 404                   |
| PUB-032 | Current status          | Correct               |
| PUB-033 | 90-day uptime           | Correct percentage    |
| PUB-034 | Average response time   | Correct               |
| PUB-035 | Uptime chart            | Displays              |
| PUB-036 | Response-time chart     | Displays              |
| PUB-037 | Daily uptime bars       | Correct               |
| PUB-038 | Hover uptime bar        | Exact value shown     |
| PUB-039 | Recent incidents        | Correct               |
| PUB-040 | No incidents            | Correct empty state   |
| PUB-041 | Active incident         | Clearly shown         |
| PUB-042 | Maintenance state       | Correct               |
| PUB-043 | Mobile uptime history   | Scrollable/usable     |
| PUB-044 | Very long service name  | Layout remains intact |

---

# 5. Dark / light mode

| ID        | Test                           | Expected                   |
| --------- | ------------------------------ | -------------------------- |
| THEME-001 | Default theme                  | Matches configured default |
| THEME-002 | Toggle light → dark            | Changes immediately        |
| THEME-003 | Toggle dark → light            | Changes immediately        |
| THEME-004 | Refresh page                   | Preference remains         |
| THEME-005 | Open another page              | Preference remains         |
| THEME-006 | Public page                    | Theme works                |
| THEME-007 | Admin page                     | Theme works                |
| THEME-008 | Charts in dark mode            | Readable                   |
| THEME-009 | Badges in dark mode            | Readable                   |
| THEME-010 | Modal in dark mode             | Readable                   |
| THEME-011 | Header form in dark mode       | Readable                   |
| THEME-012 | Validation errors in dark mode | Readable                   |

---

# 6. Branding

| ID        | Test              | Expected             |
| --------- | ----------------- | -------------------- |
| BRAND-001 | Set app name      | Appears everywhere   |
| BRAND-002 | Set tagline       | Appears correctly    |
| BRAND-003 | Upload light logo | Correct              |
| BRAND-004 | Upload dark logo  | Correct              |
| BRAND-005 | Upload favicon    | Correct              |
| BRAND-006 | Invalid logo file | Validation/error     |
| BRAND-007 | Oversized logo    | Rejected or handled  |
| BRAND-008 | Delete logo       | Fallback works       |
| BRAND-009 | Public page       | New branding visible |
| BRAND-010 | Admin             | New branding visible |
| BRAND-011 | Email             | Branding visible     |
| BRAND-012 | Dark mode         | Dark logo used       |
| BRAND-013 | Light mode        | Light logo used      |

---

# 7. Service group CRUD

| ID        | Test                       | Expected                    |
| --------- | -------------------------- | --------------------------- |
| GROUP-001 | Create group               | Success                     |
| GROUP-002 | Empty name                 | Validation                  |
| GROUP-003 | Duplicate name             | Handled                     |
| GROUP-004 | Duplicate slug             | Rejected                    |
| GROUP-005 | Edit group                 | Saved                       |
| GROUP-006 | Change sort order          | Reflected                   |
| GROUP-007 | Disable group              | Correct                     |
| GROUP-008 | Delete empty group         | Success                     |
| GROUP-009 | Delete group with services | Correct protection/handling |
| GROUP-010 | Public page                | Services grouped correctly  |

---

# 8. Service CRUD

| ID      | Test                        | Expected                    |
| ------- | --------------------------- | --------------------------- |
| SVC-001 | Create basic GET service    | Success                     |
| SVC-002 | Edit service                | Changes persist             |
| SVC-003 | Delete service              | Removed                     |
| SVC-004 | Duplicate slug              | Rejected                    |
| SVC-005 | Invalid URL                 | Validation                  |
| SVC-006 | Empty name                  | Validation                  |
| SVC-007 | Change group                | Correct                     |
| SVC-008 | Change interval             | Saved                       |
| SVC-009 | Pause service               | Checks stop                 |
| SVC-010 | Resume service              | Checks resume               |
| SVC-011 | Public off                  | Hidden publicly             |
| SVC-012 | Public on                   | Visible                     |
| SVC-013 | Sort order                  | Correct                     |
| SVC-014 | Long description            | Displays properly           |
| SVC-015 | Unicode name                | Works                       |
| SVC-016 | Special characters          | Safe                        |
| SVC-017 | Service delete confirmation | Prevent accidental deletion |
| SVC-018 | Cancel delete               | Service remains             |

---

# 9. Request method tests

Create one monitor for each.

| ID      | Method  | Expected       |
| ------- | ------- | -------------- |
| REQ-001 | GET     | Sent correctly |
| REQ-002 | POST    | Sent correctly |
| REQ-003 | PUT     | Sent correctly |
| REQ-004 | PATCH   | Sent correctly |
| REQ-005 | DELETE  | Sent correctly |
| REQ-006 | HEAD    | Sent correctly |
| REQ-007 | OPTIONS | Sent correctly |

Use `/echo` to verify the actual received method.

---

# 10. Query parameter tests

| ID        | Test                     | Expected                     |
| --------- | ------------------------ | ---------------------------- |
| QUERY-001 | One parameter            | Added                        |
| QUERY-002 | Multiple parameters      | All added                    |
| QUERY-003 | Empty value              | Correct handling             |
| QUERY-004 | Space in value           | Correct URL encoding         |
| QUERY-005 | `&` in value             | Correct encoding             |
| QUERY-006 | Unicode                  | Correct encoding             |
| QUERY-007 | Duplicate parameter keys | Defined behavior             |
| QUERY-008 | Edit existing parameter  | Correct                      |
| QUERY-009 | Delete parameter         | Removed                      |
| QUERY-010 | `/echo` validation       | Server receives exact values |

---

# 11. Header preset tests

| ID      | Test                      | Expected              |
| ------- | ------------------------- | --------------------- |
| HDR-001 | Open header selector      | Presets shown         |
| HDR-002 | Search header             | Matching results      |
| HDR-003 | Select Accept             | Name inserted         |
| HDR-004 | Select Content-Type       | Name inserted         |
| HDR-005 | Select Authorization      | Name inserted         |
| HDR-006 | Select X-API-Key          | Name inserted         |
| HDR-007 | Preset category filtering | Correct               |
| HDR-008 | Add multiple headers      | All retained          |
| HDR-009 | Delete header             | Removed               |
| HDR-010 | Edit header value         | Saved                 |
| HDR-011 | Duplicate same header     | Correctly handled     |
| HDR-012 | Header order              | Preserved if required |

---

# 12. Custom header tests

| ID      | Test                      | Expected               |
| ------- | ------------------------- | ---------------------- |
| HDR-020 | Add custom header         | Success                |
| HDR-021 | Custom name `X-Test`      | Sent                   |
| HDR-022 | Custom value              | Sent                   |
| HDR-023 | Empty header name         | Validation             |
| HDR-024 | Very long header          | Handled/rejected       |
| HDR-025 | Special header characters | Safe validation        |
| HDR-026 | Multiple custom headers   | All sent               |
| HDR-027 | Edit custom header        | Correct                |
| HDR-028 | Delete custom header      | Correct                |
| HDR-029 | `/echo` verification      | Exact headers received |

---

# 13. Authentication tests

### None

| ID       | Test | Expected                 |
| -------- | ---- | ------------------------ |
| AUTH-040 | None | No auth header generated |

### Bearer

| ID       | Test                    | Expected          |
| -------- | ----------------------- | ----------------- |
| AUTH-041 | Configure token         | Saved             |
| AUTH-042 | Run request             | Bearer token sent |
| AUTH-043 | Edit token              | Works             |
| AUTH-044 | Secret displayed masked | Yes               |
| AUTH-045 | Token not in logs       | Yes               |

### Basic

| ID       | Test              | Expected         |
| -------- | ----------------- | ---------------- |
| AUTH-046 | Username/password | Sent             |
| AUTH-047 | Wrong credentials | Expected failure |
| AUTH-048 | Password masked   | Yes              |

### API Key

| ID       | Test               | Expected |
| -------- | ------------------ | -------- |
| AUTH-049 | Header name custom | Works    |
| AUTH-050 | API key sent       | Works    |
| AUTH-051 | API key masked     | Yes      |

### Custom

| ID       | Test                          | Expected |
| -------- | ----------------------------- | -------- |
| AUTH-052 | Custom auth header            | Works    |
| AUTH-053 | Multiple auth-related headers | Works    |

---

# 14. Request body tests

### JSON

| ID       | Test              | Expected       |
| -------- | ----------------- | -------------- |
| BODY-001 | JSON body         | Sent           |
| BODY-002 | Nested JSON       | Sent correctly |
| BODY-003 | Boolean           | Correct type   |
| BODY-004 | Number            | Correct type   |
| BODY-005 | Array             | Correct type   |
| BODY-006 | Invalid JSON      | Validation     |
| BODY-007 | Content-Type JSON | Correct        |

### Form

| ID       | Test               | Expected |
| -------- | ------------------ | -------- |
| BODY-008 | Form field         | Sent     |
| BODY-009 | Multiple fields    | Sent     |
| BODY-010 | Empty field        | Correct  |
| BODY-011 | Special characters | Correct  |

### URL encoded

| ID       | Test            | Expected |
| -------- | --------------- | -------- |
| BODY-012 | One field       | Sent     |
| BODY-013 | Multiple fields | Sent     |

### Raw

| ID       | Test                | Expected     |
| -------- | ------------------- | ------------ |
| BODY-014 | Raw text            | Sent exactly |
| BODY-015 | Custom Content-Type | Correct      |

---

# 15. Test Request modal

| ID          | Test                 | Expected                      |
| ----------- | -------------------- | ----------------------------- |
| TESTREQ-001 | Open modal           | Opens                         |
| TESTREQ-002 | Valid request        | Shows result                  |
| TESTREQ-003 | 500 endpoint         | Shows failure                 |
| TESTREQ-004 | Timeout              | Shows timeout                 |
| TESTREQ-005 | Invalid URL          | Validation                    |
| TESTREQ-006 | Headers              | Result confirms headers       |
| TESTREQ-007 | Auth                 | Result confirms auth          |
| TESTREQ-008 | JSON body            | Result confirms body          |
| TESTREQ-009 | Assertions           | Pass/fail shown               |
| TESTREQ-010 | Response time        | Displayed                     |
| TESTREQ-011 | Redirect             | Final URL shown               |
| TESTREQ-012 | Secret headers       | Redacted                      |
| TESTREQ-013 | Large response       | Safe handling                 |
| TESTREQ-014 | Test without saving  | Does not unexpectedly persist |
| TESTREQ-015 | Browser close/reopen | No accidental corruption      |

---

# 16. HTTP status assertion tests

| ID         | Test                   | Expected             |
| ---------- | ---------------------- | -------------------- |
| ASSERT-001 | Expected 200 + 200     | Pass                 |
| ASSERT-002 | Expected 200 + 500     | Fail                 |
| ASSERT-003 | Expected 200/201 + 201 | Pass                 |
| ASSERT-004 | Expected 200/201 + 202 | Fail                 |
| ASSERT-005 | Expected 204 + 204     | Pass                 |
| ASSERT-006 | Change expected code   | New behavior         |
| ASSERT-007 | No status assertion    | Uses defined default |

---

# 17. Response-time assertions

| ID       | Test                              | Expected                  |
| -------- | --------------------------------- | ------------------------- |
| TIME-001 | 500ms response / 1000ms threshold | Pass                      |
| TIME-002 | 1200ms / 1000ms warning           | Degraded                  |
| TIME-003 | 3500ms / 3000ms failure           | Failed                    |
| TIME-004 | Exact threshold                   | Defined boundary behavior |
| TIME-005 | Very fast response                | Pass                      |
| TIME-006 | Timeout                           | Failed                    |
| TIME-007 | Change thresholds                 | New status used           |

---

# 18. Body assertions

| ID             | Test                       | Expected        |
| -------------- | -------------------------- | --------------- |
| BODYASSERT-001 | Contains existing text     | Pass            |
| BODYASSERT-002 | Contains missing text      | Fail            |
| BODYASSERT-003 | Not contains absent text   | Pass            |
| BODYASSERT-004 | Not contains existing text | Fail            |
| BODYASSERT-005 | Regex matches              | Pass            |
| BODYASSERT-006 | Regex doesn't match        | Fail            |
| BODYASSERT-007 | Empty response             | Correct failure |

---

# 19. JSON assertions

| ID       | Test                 | Expected               |
| -------- | -------------------- | ---------------------- |
| JSON-001 | `$.status = ok`      | Pass                   |
| JSON-002 | Wrong expected value | Fail                   |
| JSON-003 | Missing field        | Fail/exists false      |
| JSON-004 | Exists operator      | Pass                   |
| JSON-005 | Not exists           | Pass                   |
| JSON-006 | Nested field         | Works                  |
| JSON-007 | Array index          | Works if supported     |
| JSON-008 | Number comparison    | Correct                |
| JSON-009 | String contains      | Correct                |
| JSON-010 | Multiple assertions  | All evaluated          |
| JSON-011 | One assertion fails  | Overall failure        |
| JSON-012 | Invalid JSON         | JSON assertion failure |
| JSON-013 | Empty JSON object    | Correct handling       |

---

# 20. Response-header assertions

| ID           | Test                         | Expected   |
| ------------ | ---------------------------- | ---------- |
| RESP-HDR-001 | Header exists                | Pass       |
| RESP-HDR-002 | Missing header               | Fail       |
| RESP-HDR-003 | Header equals                | Pass       |
| RESP-HDR-004 | Wrong header value           | Fail       |
| RESP-HDR-005 | Multiple response assertions | Correct    |
| RESP-HDR-006 | Case sensitivity behavior    | Consistent |

---

# 21. Redirect tests

| ID        | Test                   | Expected             |
| --------- | ---------------------- | -------------------- |
| REDIR-001 | Redirect enabled       | Follows              |
| REDIR-002 | Redirect disabled      | Original result used |
| REDIR-003 | One redirect           | Works                |
| REDIR-004 | Multiple redirects     | Works within limit   |
| REDIR-005 | Too many redirects     | Fails safely         |
| REDIR-006 | Redirect to private IP | Blocked              |
| REDIR-007 | Redirect to localhost  | Blocked              |
| REDIR-008 | Final URL displayed    | Correct              |
| REDIR-009 | Redirect count         | Correct              |

---

# 22. SSL tests

| ID      | Test                             | Expected         |
| ------- | -------------------------------- | ---------------- |
| SSL-001 | Valid certificate + verify on    | Pass             |
| SSL-002 | Invalid certificate + verify on  | Fail             |
| SSL-003 | Invalid certificate + verify off | Works            |
| SSL-004 | HTTPS site                       | Works            |
| SSL-005 | HTTP site                        | Works if allowed |
| SSL-006 | SSL error details                | Safe and useful  |

---

# 23. SSRF/security tests

These are mandatory.

| ID       | Test                       | Expected                 |
| -------- | -------------------------- | ------------------------ |
| SSRF-001 | `127.0.0.1`                | Blocked                  |
| SSRF-002 | `localhost`                | Blocked                  |
| SSRF-003 | `10.0.0.1`                 | Blocked                  |
| SSRF-004 | `192.168.x.x`              | Blocked                  |
| SSRF-005 | `172.16.x.x`               | Blocked                  |
| SSRF-006 | `169.254.169.254`          | Blocked                  |
| SSRF-007 | IPv6 localhost             | Blocked                  |
| SSRF-008 | Private DNS hostname       | Blocked after resolution |
| SSRF-009 | Redirect to private IP     | Blocked                  |
| SSRF-010 | Redirect to localhost      | Blocked                  |
| SSRF-011 | `file://`                  | Blocked                  |
| SSRF-012 | `ftp://`                   | Blocked                  |
| SSRF-013 | `gopher://`                | Blocked                  |
| SSRF-014 | Unsupported scheme         | Blocked                  |
| SSRF-015 | DNS rebinding-style target | Protected                |
| SSRF-016 | Huge response              | Size capped              |

---

# 24. Timeout tests

| ID          | Test                     | Expected      |
| ----------- | ------------------------ | ------------- |
| TIMEOUT-001 | Normal response          | Pass          |
| TIMEOUT-002 | Connect timeout          | Failed check  |
| TIMEOUT-003 | Request timeout          | Failed check  |
| TIMEOUT-004 | Very low timeout         | Fails quickly |
| TIMEOUT-005 | Maximum allowed timeout  | Accepted      |
| TIMEOUT-006 | Invalid negative timeout | Validation    |

---

# 25. Scheduled monitoring

| ID      | Test                      | Expected                              |
| ------- | ------------------------- | ------------------------------------- |
| MON-001 | Create active service     | Gets scheduled                        |
| MON-002 | Next check time           | Correct                               |
| MON-003 | Due service               | Dispatched                            |
| MON-004 | Future service            | Not dispatched early                  |
| MON-005 | Paused service            | Not dispatched                        |
| MON-006 | Resumed service           | Dispatch resumes                      |
| MON-007 | Multiple services         | All due services processed            |
| MON-008 | Different intervals       | Correct schedule                      |
| MON-009 | Concurrent worker attempt | Duplicate prevented                   |
| MON-010 | Failed job                | Retried/backoff                       |
| MON-011 | Invalid config            | Visible failed check, worker survives |
| MON-012 | Worker stale              | Monitoring page shows stale           |
| MON-013 | Worker returns            | Shows running                         |

---

# 26. "Check Now"

| ID      | Test                         | Expected                         |
| ------- | ---------------------------- | -------------------------------- |
| NOW-001 | Click Check Now              | Job queued                       |
| NOW-002 | Normal service               | New check appears                |
| NOW-003 | Failed service               | Failed result appears            |
| NOW-004 | Verify response time         | Saved                            |
| NOW-005 | Verify timestamp             | Current time                     |
| NOW-006 | Multiple clicks quickly      | Duplicate protection if intended |
| NOW-007 | Paused service               | Correct behavior                 |
| NOW-008 | Deleted service while queued | Graceful handling                |

---

# 27. Check history

| ID       | Test                     | Expected             |
| -------- | ------------------------ | -------------------- |
| HIST-001 | Open service history     | Loads                |
| HIST-002 | Successful check         | Shows                |
| HIST-003 | Failed check             | Shows                |
| HIST-004 | HTTP code                | Correct              |
| HIST-005 | Response time            | Correct              |
| HIST-006 | Error message            | Correct              |
| HIST-007 | Failed assertion details | Correct              |
| HIST-008 | Pagination               | Works                |
| HIST-009 | Filter by result         | Works if implemented |
| HIST-010 | Large history            | Remains usable       |

---

# 28. Smart status calculation

| ID         | Test                                  | Expected                           |
| ---------- | ------------------------------------- | ---------------------------------- |
| STATUS-001 | One successful check                  | Operational                        |
| STATUS-002 | One failure below threshold           | Status doesn't immediately go down |
| STATUS-003 | Nth consecutive failure               | Goes down                          |
| STATUS-004 | Failure then success before threshold | Failure streak resets              |
| STATUS-005 | Slow response                         | Degraded                           |
| STATUS-006 | Fast response after degraded          | Operational                        |
| STATUS-007 | Assertion failure                     | Failed                             |
| STATUS-008 | Maintenance active                    | Maintenance                        |
| STATUS-009 | Maintenance ends + healthy            | Operational                        |
| STATUS-010 | Monitoring stale                      | Defined stale/unknown behavior     |

---

# 29. Failure threshold tests

Example: set failure threshold = 3.

| ID         | Sequence                          | Expected               |
| ---------- | --------------------------------- | ---------------------- |
| THRESH-001 | Fail                              | Remains previous state |
| THRESH-002 | Fail, Fail                        | Remains previous state |
| THRESH-003 | Fail, Fail, Fail                  | Down                   |
| THRESH-004 | Fail, OK                          | Failure count resets   |
| THRESH-005 | Fail, Fail, OK                    | No incident            |
| THRESH-006 | Configured threshold changed to 1 | One fail can trigger   |
| THRESH-007 | Per-service threshold             | Overrides global       |
| THRESH-008 | No per-service value              | Global default used    |

---

# 30. Automatic recovery

| ID      | Test                     | Expected                    |
| ------- | ------------------------ | --------------------------- |
| REC-001 | Service down             | Down                        |
| REC-002 | First good check         | Operational                 |
| REC-003 | Recovery timestamp       | Correct                     |
| REC-004 | Recovery email           | One email                   |
| REC-005 | Recovery webhook         | One event if configured     |
| REC-006 | Repeated good checks     | No duplicate recovery alert |
| REC-007 | Recovery then fail again | New incident cycle works    |

---

# 31. Incident creation

| ID      | Test                         | Expected              |
| ------- | ---------------------------- | --------------------- |
| INC-001 | N failures                   | Incident created      |
| INC-002 | Below threshold failures     | No incident           |
| INC-003 | Incident title               | Correct               |
| INC-004 | Correct service              | Linked                |
| INC-005 | Started time                 | Correct               |
| INC-006 | Impact level                 | Correct               |
| INC-007 | Public incident page         | Available             |
| INC-008 | Incident card                | Public page shows it  |
| INC-009 | Duplicate failure while open | No duplicate incident |

---

# 32. Manual incidents

| ID      | Test               | Expected            |
| ------- | ------------------ | ------------------- |
| INC-020 | Create incident    | Success             |
| INC-021 | Link one service   | Correct             |
| INC-022 | Mark multi-service | Correct             |
| INC-023 | No service         | Allowed if intended |
| INC-024 | Edit incident      | Saved               |
| INC-025 | Change impact      | Updated             |
| INC-026 | Change title       | Updated             |
| INC-027 | Delete incident    | Correct behavior    |

---

# 33. Incident updates

| ID         | Test                     | Expected             |
| ---------- | ------------------------ | -------------------- |
| INC-UP-001 | Add Investigating        | Timeline entry       |
| INC-UP-002 | Add Identified           | Timeline entry       |
| INC-UP-003 | Add Monitoring           | Timeline entry       |
| INC-UP-004 | Add Resolved             | Timeline entry       |
| INC-UP-005 | Timestamp                | Correct              |
| INC-UP-006 | Public timeline          | Updated              |
| INC-UP-007 | Email notification       | Triggered if enabled |
| INC-UP-008 | Edit update if supported | Correct              |
| INC-UP-009 | Long message             | Layout remains good  |

---

# 34. Impact levels

Test:

```text
None
Minor
Major
Critical
```

For each:

| ID      | Test     | Expected      |
| ------- | -------- | ------------- |
| IMP-001 | None     | Correct badge |
| IMP-002 | Minor    | Correct badge |
| IMP-003 | Major    | Correct badge |
| IMP-004 | Critical | Correct badge |

Also verify color/text doesn't become unreadable in dark mode.

---

# 35. Incident recovery and notification deduplication

This deserves a separate test.

Configure both:

```text
Service recovered = ON
Incident resolved = ON
```

Then:

```text
Service down
→ Incident created
→ Service recovers
```

Expected:

```text id="p6y2te"
One resolution notification
No duplicate service-recovered notification
```

Then verify delivery logs.

---

# 36. Maintenance windows

| ID       | Test                           | Expected          |
| -------- | ------------------------------ | ----------------- |
| MAIN-001 | Create maintenance             | Success           |
| MAIN-002 | Set start/end                  | Saved             |
| MAIN-003 | Select one service             | Linked            |
| MAIN-004 | Select multiple services       | All linked        |
| MAIN-005 | Future maintenance             | Scheduled         |
| MAIN-006 | Maintenance begins             | Active            |
| MAIN-007 | Public status                  | Maintenance       |
| MAIN-008 | Monitoring during maintenance  | No false outage   |
| MAIN-009 | Uptime during maintenance      | Excluded fairly   |
| MAIN-010 | Maintenance ends               | Normal monitoring |
| MAIN-011 | Cancel maintenance             | Cancelled         |
| MAIN-012 | Delete future maintenance      | Removed           |
| MAIN-013 | Past maintenance               | Remains history   |
| MAIN-014 | Maintenance banner             | Correct           |
| MAIN-015 | Maintenance start notification | Sent if enabled   |
| MAIN-016 | Maintenance end notification   | Sent if enabled   |

---

# 37. Notification settings

Test global master switches.

| ID        | Test                     | Expected                |
| --------- | ------------------------ | ----------------------- |
| NOTIF-001 | Email master ON          | Email can send          |
| NOTIF-002 | Email master OFF         | No email                |
| NOTIF-003 | Webhook master ON        | Webhook can send        |
| NOTIF-004 | Webhook master OFF       | No webhook              |
| NOTIF-005 | Event switch OFF         | No notification         |
| NOTIF-006 | Event switch ON          | Notification allowed    |
| NOTIF-007 | Per-service failure OFF  | Service failure skipped |
| NOTIF-008 | Per-service recovery OFF | Recovery skipped        |
| NOTIF-009 | Global OFF + event ON    | Global OFF wins         |
| NOTIF-010 | Global ON + event OFF    | Event OFF wins          |

---

# 38. Email SMTP configuration

| ID       | Test                   | Expected                |
| -------- | ---------------------- | ----------------------- |
| MAIL-001 | Correct SMTP host      | Saves                   |
| MAIL-002 | Correct port           | Saves                   |
| MAIL-003 | Username               | Saves                   |
| MAIL-004 | Password               | Masked/encrypted        |
| MAIL-005 | Encryption none        | Works if supported      |
| MAIL-006 | TLS                    | Works                   |
| MAIL-007 | From address           | Used                    |
| MAIL-008 | From name              | Used                    |
| MAIL-009 | Invalid host           | Test fails clearly      |
| MAIL-010 | Invalid port           | Validation              |
| MAIL-011 | Invalid credentials    | Test fails              |
| MAIL-012 | Save + reopen settings | Values behave correctly |

---

# 39. Send test email

| ID       | Test                                | Expected         |
| -------- | ----------------------------------- | ---------------- |
| MAIL-020 | Click Send Test Email               | Email sent       |
| MAIL-021 | Correct recipient                   | Yes              |
| MAIL-022 | Correct From                        | Yes              |
| MAIL-023 | Correct subject                     | Yes              |
| MAIL-024 | HTML renders                        | Yes              |
| MAIL-025 | Branding                            | Correct          |
| MAIL-026 | Dark logo where expected            | Correct          |
| MAIL-027 | Test mail does not trigger incident | Yes              |
| MAIL-028 | Delivery log entry                  | Recorded         |
| MAIL-029 | Failed send                         | Failure recorded |

---

# 40. Email incident notifications

For each event, test:

```text
Service failed
Service degraded
Service recovered
Incident created
Incident updated
Incident resolved
Maintenance started
Maintenance ended
```

| ID       | Test                | Expected         |
| -------- | ------------------- | ---------------- |
| MAIL-040 | Service failed      | Email            |
| MAIL-041 | Service degraded    | Email if enabled |
| MAIL-042 | Service recovered   | Email if enabled |
| MAIL-043 | Incident created    | Email            |
| MAIL-044 | Incident updated    | Email            |
| MAIL-045 | Incident resolved   | Email            |
| MAIL-046 | Maintenance started | Email if enabled |
| MAIL-047 | Maintenance ended   | Email if enabled |

Check each mail for:

* correct subject
* correct service
* current status
* incident link
* timestamp
* branding
* no secrets
* unsubscribe link where applicable

---

# 41. Subscriber email flow

| ID      | Test                        | Expected                   |
| ------- | --------------------------- | -------------------------- |
| SUB-001 | Enter valid email           | Verification sent          |
| SUB-002 | Invalid email               | Validation                 |
| SUB-003 | Submit same email twice     | Defined duplicate handling |
| SUB-004 | Open verification link      | Verified                   |
| SUB-005 | Expired verification link   | Rejected                   |
| SUB-006 | Invalid token               | Rejected                   |
| SUB-007 | Unverified subscriber       | No incident email          |
| SUB-008 | Verified subscriber         | Incident email             |
| SUB-009 | Incident update             | Email                      |
| SUB-010 | Incident resolve            | Email                      |
| SUB-011 | One-click unsubscribe       | Unsubscribed               |
| SUB-012 | Unsubscribed subscriber     | No future email            |
| SUB-013 | Re-subscribe                | Works                      |
| SUB-014 | Email address normalization | Correct                    |

---

# 42. Unsubscribe security

| ID        | Test                   | Expected                          |
| --------- | ---------------------- | --------------------------------- |
| UNSUB-001 | Valid token            | Unsubscribe                       |
| UNSUB-002 | Invalid token          | Rejected                          |
| UNSUB-003 | Modified token         | Rejected                          |
| UNSUB-004 | Reuse token            | Safe                              |
| UNSUB-005 | Wrong subscriber token | Must not unsubscribe another user |

---

# 43. Webhook configuration

| ID     | Test                | Expected         |
| ------ | ------------------- | ---------------- |
| WH-001 | Add webhook channel | Success          |
| WH-002 | Valid URL           | Saves            |
| WH-003 | Invalid URL         | Validation       |
| WH-004 | Enable webhook      | Active           |
| WH-005 | Disable webhook     | Inactive         |
| WH-006 | Set secret          | Encrypted/masked |
| WH-007 | Configure timeout   | Saved            |
| WH-008 | Configure retry     | Saved            |
| WH-009 | Edit webhook        | Saved            |
| WH-010 | Delete webhook      | Removed          |

---

# 44. Send test webhook

| ID     | Test                         | Expected                 |
| ------ | ---------------------------- | ------------------------ |
| WH-020 | Send test webhook            | Capture receives request |
| WH-021 | HTTP method                  | Correct                  |
| WH-022 | JSON body                    | Valid                    |
| WH-023 | Content-Type                 | Correct                  |
| WH-024 | Event field                  | Present                  |
| WH-025 | Timestamp                    | Present/correct          |
| WH-026 | Service data                 | Present if applicable    |
| WH-027 | HMAC signature               | Present                  |
| WH-028 | HMAC verifies                | Valid                    |
| WH-029 | Secret isn't exposed in body | Yes                      |
| WH-030 | Delivery log                 | Recorded                 |

---

# 45. Webhook HMAC tests

| ID          | Test                                     | Expected                    |
| ----------- | ---------------------------------------- | --------------------------- |
| WH-HMAC-001 | Verify signature using configured secret | Pass                        |
| WH-HMAC-002 | Alter body                               | Signature no longer matches |
| WH-HMAC-003 | Alter signature header                   | Verification fails          |
| WH-HMAC-004 | Change secret                            | Old signature invalid       |
| WH-HMAC-005 | Empty secret if allowed                  | Defined behavior            |

---

# 46. Webhook event tests

For each notification event, verify a webhook can receive it:

| ID         | Event               |
| ---------- | ------------------- |
| WH-EVT-001 | service.failed      |
| WH-EVT-002 | service.degraded    |
| WH-EVT-003 | service.recovered   |
| WH-EVT-004 | incident.created    |
| WH-EVT-005 | incident.updated    |
| WH-EVT-006 | incident.resolved   |
| WH-EVT-007 | maintenance.started |
| WH-EVT-008 | maintenance.ended   |

Check that the payload contains the right event and current state.

---

# 47. Webhook retry tests

Configure a webhook endpoint that returns failure.

| ID         | Test                       | Expected                 |
| ---------- | -------------------------- | ------------------------ |
| WH-RET-001 | Endpoint returns 500       | Retry                    |
| WH-RET-002 | Endpoint timeout           | Retry if enabled         |
| WH-RET-003 | First fail, second success | Delivery becomes success |
| WH-RET-004 | All retries fail           | Final failure            |
| WH-RET-005 | Retry count                | Correct                  |
| WH-RET-006 | Backoff                    | Correct                  |
| WH-RET-007 | Delivery log               | Shows final result       |
| WH-RET-008 | Secret not in error text   | Yes                      |

---

# 48. Notification rule routing

Test:

```text
Global rule
Service-specific rule
```

| ID       | Test                              | Expected                |
| -------- | --------------------------------- | ----------------------- |
| RULE-001 | Global email rule                 | All matching events     |
| RULE-002 | Service-specific email rule       | Only selected service   |
| RULE-003 | Global webhook rule               | All matching events     |
| RULE-004 | Service-specific webhook          | Only selected service   |
| RULE-005 | Disabled rule                     | No notification         |
| RULE-006 | Multiple channels                 | All applicable channels |
| RULE-007 | Same event matches multiple rules | No unintended duplicate |

---

# 49. Delivery log

| ID      | Test                  | Expected                |
| ------- | --------------------- | ----------------------- |
| DEL-001 | Successful email      | `sent`                  |
| DEL-002 | Failed email          | `failed`                |
| DEL-003 | Disabled notification | `skipped`               |
| DEL-004 | Webhook success       | `sent`                  |
| DEL-005 | Webhook failure       | `failed`                |
| DEL-006 | Recipient count       | Correct                 |
| DEL-007 | Error message         | Present without secrets |
| DEL-008 | Timestamp             | Correct                 |
| DEL-009 | Event type            | Correct                 |
| DEL-010 | Service filter        | Works if implemented    |

---

# 50. Email + webhook together

This is a critical end-to-end test.

Configure:

```text
Email = ON
Webhook = ON
Service failure = ON
Recovery = ON
```

Then:

```text
Service
→ reaches failure threshold
→ incident created
→ recovery
```

Expected:

```text
Failure:
1 email
1 webhook

Recovery:
1 appropriate recovery email
1 appropriate recovery webhook

No duplicates.
```

---

# 51. Incident notification deduplication

Test this exact sequence:

```text
Fail
Fail
Fail
Fail
Fail
```

Expected:

```text
One incident
One incident-created notification
```

Not five.

Then:

```text
Recover
Recover
Recover
```

Expected:

```text
One resolution
One resolution notification
```

---

# 52. Degraded notification behavior

Configure:

```text
Degraded notification = ON
```

Then make response exceed warning threshold but stay below failure threshold.

Expected:

```text
Service = Degraded
One degraded notification
No incident if your rules require an incident only for down state
```

Then continue degraded responses and verify there isn't a notification on every check.

---

# 53. Settings partial-safe saving

This is worth testing heavily.

| ID      | Test                              | Expected                 |
| ------- | --------------------------------- | ------------------------ |
| SET-001 | Change General setting            | Saved                    |
| SET-002 | Change Branding                   | Saved                    |
| SET-003 | Change Monitoring                 | Saved                    |
| SET-004 | Change Public                     | Saved                    |
| SET-005 | Change Mail                       | Saved                    |
| SET-006 | Change Alerts                     | Saved                    |
| SET-007 | Change Webhook                    | Saved                    |
| SET-008 | Change Retention                  | Saved                    |
| SET-009 | One invalid field                 | Only invalid part fails  |
| SET-010 | Valid fields in same submit       | Remain saved             |
| SET-011 | Reload page after partial failure | Valid changes remain     |
| SET-012 | SMTP invalid port                 | Other settings not wiped |
| SET-013 | Invalid webhook URL               | Other settings remain    |

---

# 54. Admin URL prefix

If configurable:

| ID      | Test                 | Expected              |
| ------- | -------------------- | --------------------- |
| URL-001 | Default admin prefix | Works                 |
| URL-002 | Change prefix        | New URL works         |
| URL-003 | Old URL              | Expected redirect/404 |
| URL-004 | Public status page   | Still works           |
| URL-005 | API                  | Still works           |

---

# 55. Public API

| ID      | Test                          | Expected           |
| ------- | ----------------------------- | ------------------ |
| API-001 | `/api/status`                 | 200 JSON           |
| API-002 | `/api/status/services`        | 200 JSON           |
| API-003 | `/api/status/services/{slug}` | Correct service    |
| API-004 | `/api/status/incidents`       | Correct incidents  |
| API-005 | Invalid slug                  | 404                |
| API-006 | JSON content type             | Correct            |
| API-007 | Overall status                | Correct            |
| API-008 | Service status                | Correct            |
| API-009 | Maintenance state             | Correct            |
| API-010 | Active incident               | Correct            |
| API-011 | Rate limit                    | Works              |
| API-012 | Rate limit headers            | Correct if exposed |
| API-013 | Public API disabled           | Correct behavior   |
| API-014 | Private service               | Never leaked       |

---

# 56. API state consistency

After changing a service from operational → degraded → outage:

| ID      | Test             | Expected    |
| ------- | ---------------- | ----------- |
| API-020 | Public page      | Correct     |
| API-021 | `/api/status`    | Same status |
| API-022 | Service endpoint | Same status |
| API-023 | Admin            | Same status |
| API-024 | Badge            | Same status |

There should never be conflicting status between these surfaces.

---

# 57. Status badge

| ID        | Test                         | Expected                          |
| --------- | ---------------------------- | --------------------------------- |
| BADGE-001 | Open badge URL               | SVG loads                         |
| BADGE-002 | Operational                  | Correct text                      |
| BADGE-003 | Degraded                     | Correct text                      |
| BADGE-004 | Outage                       | Correct text                      |
| BADGE-005 | Maintenance                  | Correct text                      |
| BADGE-006 | Embed in HTML                | Works                             |
| BADGE-007 | GitHub README use            | Renders                           |
| BADGE-008 | Cache behavior               | Does not become excessively stale |
| BADGE-009 | Badge endpoint rate handling | Safe                              |
| BADGE-010 | Private status               | Not exposed if disabled           |

---

# 58. Live refresh

| ID       | Test                      | Expected                  |
| -------- | ------------------------- | ------------------------- |
| LIVE-001 | Page open                 | Poll starts               |
| LIVE-002 | Status changes            | Page updates              |
| LIVE-003 | No status change          | No visual flicker         |
| LIVE-004 | API error during poll     | Page stays usable         |
| LIVE-005 | API returns 429           | Graceful                  |
| LIVE-006 | Browser tab background    | No runaway requests       |
| LIVE-007 | Browser comes back active | Refresh behaves correctly |
| LIVE-008 | Refresh interval setting  | Respected                 |

---

# 59. Uptime statistics

Test with controlled check data.

| ID         | Test               | Expected                    |
| ---------- | ------------------ | --------------------------- |
| UPTIME-001 | 100% success       | 100%                        |
| UPTIME-002 | One failed check   | Reduced                     |
| UPTIME-003 | Multiple failures  | Correct math                |
| UPTIME-004 | Maintenance period | Excluded according to rules |
| UPTIME-005 | No data            | Defined empty state         |
| UPTIME-006 | 24-hour view       | Correct                     |
| UPTIME-007 | 7-day view         | Correct                     |
| UPTIME-008 | 30-day view        | Correct                     |
| UPTIME-009 | 90-day view        | Correct                     |
| UPTIME-010 | Daily aggregation  | Matches raw checks          |

---

# 60. Response-time statistics

| ID       | Test            | Expected                    |
| -------- | --------------- | --------------------------- |
| RESP-001 | One check       | Correct average             |
| RESP-002 | Multiple checks | Correct average             |
| RESP-003 | Fast + slow     | Correct average             |
| RESP-004 | Failed checks   | Defined inclusion/exclusion |
| RESP-005 | Daily graph     | Correct                     |
| RESP-006 | Service page    | Matches database data       |

---

# 61. Monitoring engine health

| ID      | Test                    | Expected |
| ------- | ----------------------- | -------- |
| ENG-001 | Worker running          | Running  |
| ENG-002 | Worker stopped          | Stale    |
| ENG-003 | Queued jobs count       | Correct  |
| ENG-004 | Failed jobs count       | Correct  |
| ENG-005 | Heartbeat timestamp     | Current  |
| ENG-006 | Worker resumes          | Running  |
| ENG-007 | Failed notification job | Visible  |
| ENG-008 | Queue backlog           | Visible  |

---

# 62. Audit log

Perform:

* create service
* edit service
* delete service
* create incident
* edit incident
* create maintenance
* change SMTP
* change notification
* change branding
* change settings
* create/delete subscriber if admin action exists

For each:

| ID        | Test            | Expected               |
| --------- | --------------- | ---------------------- |
| AUDIT-001 | Action recorded | Yes                    |
| AUDIT-002 | User recorded   | Correct                |
| AUDIT-003 | Timestamp       | Correct                |
| AUDIT-004 | IP              | Correct                |
| AUDIT-005 | Object          | Correct                |
| AUDIT-006 | Old values      | Present where intended |
| AUDIT-007 | New values      | Present                |
| AUDIT-008 | Secrets         | Never stored           |

---

# 63. Search and filters

Test each admin page.

| ID         | Test               | Expected           |
| ---------- | ------------------ | ------------------ |
| FILTER-001 | Search services    | Correct            |
| FILTER-002 | Filter by status   | Correct            |
| FILTER-003 | Filter by group    | Correct            |
| FILTER-004 | Search incidents   | Correct            |
| FILTER-005 | Filter maintenance | Correct            |
| FILTER-006 | Search subscribers | Correct            |
| FILTER-007 | Search deliveries  | Correct            |
| FILTER-008 | Empty results      | Good empty state   |
| FILTER-009 | Clear filters      | All records return |

---

# 64. Pagination

| ID       | Test                 | Expected             |
| -------- | -------------------- | -------------------- |
| PAGE-001 | One page             | Correct              |
| PAGE-002 | Multiple pages       | Correct              |
| PAGE-003 | Next page            | Correct              |
| PAGE-004 | Previous page        | Correct              |
| PAGE-005 | Page size            | Correct if supported |
| PAGE-006 | Filters + pagination | State retained       |
| PAGE-007 | Search + pagination  | State retained       |

---

# 65. Delete/confirmation safety

For every destructive action:

| ID      | Test                 | Expected               |
| ------- | -------------------- | ---------------------- |
| DEL-020 | Click Delete         | Confirmation           |
| DEL-021 | Cancel               | Nothing deleted        |
| DEL-022 | Confirm              | Deleted                |
| DEL-023 | Double click confirm | No duplicate action    |
| DEL-024 | Back/refresh         | No accidental deletion |

Test for:

* services
* groups
* incidents where applicable
* maintenance
* notification channels
* rules
* subscribers
* templates

---

# 66. Validation and error messages

Every form should be tested for:

```text
Empty required field
Too short
Too long
Invalid URL
Invalid email
Invalid port
Invalid integer
Negative number
Invalid JSON
Invalid regex
Invalid timeout
Invalid interval
Invalid date
End before start
Duplicate slug
Duplicate resource
```

Expected:

* clear message
* correct field highlighted
* entered values preserved where safe
* secrets not exposed

---

# 67. Maintenance date tests

| ID       | Test                      | Expected                    |
| -------- | ------------------------- | --------------------------- |
| DATE-001 | Start in past             | Correctly handled           |
| DATE-002 | End in past               | Correctly handled           |
| DATE-003 | End before start          | Rejected                    |
| DATE-004 | Same start/end            | Rejected if invalid         |
| DATE-005 | Timezone conversion       | Correct                     |
| DATE-006 | Public displayed timezone | Correct configured timezone |
| DATE-007 | DST-sensitive timezone    | Correct if applicable       |

---

# 68. Email links

Open every generated link.

| ID       | Test              | Expected               |
| -------- | ----------------- | ---------------------- |
| LINK-001 | Incident link     | Opens correct incident |
| LINK-002 | Status page link  | Opens                  |
| LINK-003 | Service link      | Opens                  |
| LINK-004 | Verification link | Works                  |
| LINK-005 | Unsubscribe link  | Works                  |
| LINK-006 | Link host         | Correct public URL     |
| LINK-007 | HTTPS             | Correct                |

---

# 69. Notification secret handling

| ID         | Test               | Expected  |
| ---------- | ------------------ | --------- |
| SECRET-001 | API key in service | Masked    |
| SECRET-002 | Bearer token       | Masked    |
| SECRET-003 | SMTP password      | Masked    |
| SECRET-004 | Webhook secret     | Masked    |
| SECRET-005 | Failed request log | No secret |
| SECRET-006 | Delivery log       | No secret |
| SECRET-007 | Audit log          | No secret |
| SECRET-008 | Test result        | No secret |

---

# 70. Browser responsiveness

Test:

```text
Desktop
Laptop
Tablet
Mobile
```

At minimum:

```text
360px
390px
768px
1024px
1366px
1920px
```

Check:

| ID     | Test            | Expected        |
| ------ | --------------- | --------------- |
| UI-001 | Public home     | Usable          |
| UI-002 | Service detail  | Usable          |
| UI-003 | Incident page   | Usable          |
| UI-004 | Admin dashboard | Usable          |
| UI-005 | Service form    | Usable          |
| UI-006 | Header builder  | Usable          |
| UI-007 | JSON editor     | Usable          |
| UI-008 | Charts          | Usable          |
| UI-009 | Tables          | Scroll properly |
| UI-010 | Modals          | Fit screen      |
| UI-011 | Navigation      | Usable          |
| UI-012 | Dark mode       | Usable          |

---

# 71. Browser compatibility

At least test:

```text
Chrome
Firefox
Edge
Safari
```

Where available.

Check:

* forms
* tabs
* modal
* dropdown
* charts
* file upload
* date/time input
* clipboard if used
* theme toggle

---

# 72. File upload tests

For:

* logo
* dark logo
* favicon

Test:

```text
Valid PNG
Valid JPG
Valid SVG
Wrong MIME
Wrong extension
Huge image
Tiny image
Corrupted file
Executable renamed as image
SVG with unsafe content
```

Expected: unsafe uploads rejected or sanitized.

---

# 73. Accessibility checks

Test keyboard-only use:

| ID       | Test                               | Expected          |
| -------- | ---------------------------------- | ----------------- |
| A11Y-001 | Tab through public page            | Logical order     |
| A11Y-002 | Tab through admin                  | Logical order     |
| A11Y-003 | Open modal with keyboard           | Works             |
| A11Y-004 | Close modal with Esc               | Works             |
| A11Y-005 | Focus visible                      | Yes               |
| A11Y-006 | Form labels                        | Present           |
| A11Y-007 | Status not conveyed by color alone | Text also present |
| A11Y-008 | Buttons have names                 | Yes               |

---

# 74. Security input tests

Try:

```text
<script>alert(1)</script>
```

in:

* service name
* description
* incident title
* incident message
* maintenance title
* maintenance description
* header value
* query parameter
* footer text

Expected:

```text
Escaped / sanitized
No script execution
```

Also test SQL-like input in search fields and slugs.

---

# 75. CSRF tests

Every state-changing admin action should require valid CSRF protection.

Test:

* create
* edit
* delete
* save settings
* send test email
* send test webhook
* check now
* incident update
* maintenance changes

---

# 76. Rate-limit tests

Test:

```text
Public API
Test Request
Check Now
Subscription
Verification
Unsubscribe
```

Repeatedly hit the endpoint.

Expected:

```text
Rate limit eventually applies
System remains available
No queue flood
```

---

# 77. Browser back/forward tests

Test:

* after creating service
* after editing service
* after deleting service
* after changing settings
* after incident update
* after logout
* after verification
* after unsubscribe

No stale form should accidentally submit old data.

---

# 78. Double-submit tests

Very important for:

* create service
* save settings
* send test email
* send test webhook
* create incident
* add incident update
* subscribe

Double-click buttons rapidly.

Expected:

```text
One intended operation
```

not duplicate database records or duplicate notifications.

---

# 79. Notification failure isolation

Example:

```text
Email = broken
Webhook = working
```

Trigger an incident.

Expected:

```text
Webhook still sends
Email recorded as failed
Monitoring continues
Incident still created
```

Then reverse:

```text
Email = working
Webhook = broken
```

Expected:

```text
Email sends
Webhook fails
Incident still works
```

This is very important.

---

# 80. Partial notification failure

Test:

```text
Email
Webhook
Multiple channels
```

with one failing.

Expected:

* successful channel remains successful
* failed channel is logged independently
* no duplicate incident creation
* monitoring result is unaffected

---

# 81. Monitoring failure vs service failure

Stop/break the monitoring worker if possible.

Expected:

```text
Service should not falsely receive fresh successful checks.
Monitoring page should show stale.
```

This confirms that PulseDeck doesn't confuse "no check was made" with "service is healthy."

---

# 82. Database failure behavior

Where practical, test temporary DB failure during:

* check result save
* notification delivery log
* incident creation

Expected:

* worker/job failure is visible
* no corrupted incident state
* retry where configured
* secrets not leaked

---

# 83. Queue failure behavior

Test:

```text
Queue unavailable
Queue delayed
Job fails
Job retries
Job finally fails
```

Admin monitoring should show the problem.

---

# 84. Cleanup behavior

Although this is partly command/scheduler territory, verify through browser after running the cleanup process:

| ID        | Test             | Expected                     |
| --------- | ---------------- | ---------------------------- |
| CLEAN-001 | Old raw checks   | Removed after retention      |
| CLEAN-002 | Recent checks    | Kept                         |
| CLEAN-003 | Daily stats      | Kept according to policy     |
| CLEAN-004 | Audit logs       | Retained according to policy |
| CLEAN-005 | Delivery records | Retained according to policy |
| CLEAN-006 | Incident records | Not unexpectedly removed     |

---

# 85. Recalculate status

After deliberately creating inconsistent/latest check data:

```text
status:recalculate
```

Then inspect browser.

Expected:

* service state corrected
* incident logic remains consistent
* no duplicate incidents
* public page matches admin

---

# 86. Overall end-to-end scenario 1 — Healthy service

```text
Create group
→ Create service
→ GET /200
→ Check Now
→ Result = 200
→ Assertions pass
→ Service = Operational
→ Public page shows Operational
→ API shows Operational
→ Badge shows Operational
```

---

# 87. End-to-end scenario 2 — Service outage

```text
Create service
→ Configure expected 200
→ Target /500
→ Trigger enough failures
→ Service becomes Failed
→ Incident created
→ Public page shows outage
→ Email sent
→ Webhook sent
→ Delivery log records both
```

---

# 88. End-to-end scenario 3 — Recovery

```text
Service still down
→ Change endpoint to /200
→ Trigger check
→ Service recovers
→ Incident resolves
→ Public page becomes Operational
→ Recovery notification sent once
→ No duplicate recovery mail
→ Delivery log updated
```

---

# 89. End-to-end scenario 4 — Slow service

```text
Target /slow
→ Response crosses warning threshold
→ Service becomes Degraded
→ Degraded notification fires
→ Incident behavior matches configured rules
→ Response time graph records the slow request
```

---

# 90. End-to-end scenario 5 — API JSON validation

```text
POST /echo
→ JSON body
→ Custom headers
→ Bearer auth
→ Expected HTTP 200
→ $.status = ok
→ Response header assertion
→ Test Request passes
→ Scheduled check passes
```

Then intentionally change:

```text
$.status = failed
```

Expected:

```text
Assertion fails
Service fails
Incident rules work
```

---

# 91. End-to-end scenario 6 — Webhook

```text
Configure webhook
→ Send test webhook
→ Capture request
→ Verify JSON
→ Verify HMAC
→ Trigger service outage
→ Receive incident event
→ Resolve service
→ Receive recovery/resolution event
→ Delivery log shows all sends
```

---

# 92. End-to-end scenario 7 — Email subscriber

```text
Subscribe
→ Verification email
→ Verify email
→ Trigger incident
→ Receive incident email
→ Incident update
→ Receive update email
→ Resolve
→ Receive resolution email
→ Unsubscribe
→ Trigger another incident
→ No email received
```

---

# 93. End-to-end scenario 8 — Maintenance

```text
Schedule maintenance
→ Public page shows scheduled maintenance
→ Maintenance starts
→ Service reports Maintenance
→ Failure during maintenance does not create false outage
→ Uptime remains fair
→ Maintenance ends
→ Normal monitoring resumes
→ Maintenance end notification sent
```

---

# 94. End-to-end scenario 9 — SSRF

```text
Create service
→ Enter private IP
→ Save/Test
```

Expected rejection.

Then:

```text
Public URL
→ Redirects to private IP
```

Expected rejection too.

---

# 95. End-to-end scenario 10 — Notification isolation

```text
Email working
Webhook broken
→ Incident
```

Expected:

```text
Email = sent
Webhook = failed
Incident = created
Monitoring = healthy
```

Then reverse.

---

# 96. End-to-end scenario 11 — Failed monitor configuration

```text
Create service
→ Invalid assertion
→ Scheduled check
```

Expected:

```text
Failed check recorded
Error shown
Worker remains alive
Monitoring dashboard still works
```

---

# 97. End-to-end scenario 12 — Recovery deduplication

Use:

```text
Service failure threshold = 3
```

Then:

```text
Fail ×3
→ Incident created

Fail ×10 more
→ Still one incident

Recover
→ Incident resolved

Recover ×10 more
→ No duplicate resolution notifications
```

This should be a mandatory regression test.

---

# 98. Regression test after every release

At minimum, rerun these:

```text
R-001 Login
R-002 Create service
R-003 Test Request
R-004 Check Now
R-005 Scheduled check
R-006 Failure threshold
R-007 Incident creation
R-008 Recovery
R-009 Maintenance
R-010 Test email
R-011 Test webhook
R-012 Subscriber verify
R-013 Subscriber unsubscribe
R-014 Public API
R-015 Status badge
R-016 Dark mode
R-017 Role permissions
R-018 Audit log
R-019 SSRF protection
R-020 Delivery log
```

# 99. Final release acceptance test

Before calling PulseDeck production-ready, I'd require this complete chain to pass:

```text
✓ Public page
✓ Admin login
✓ All three roles
✓ Service groups
✓ Service CRUD
✓ GET
✓ POST
✓ PUT
✓ PATCH
✓ DELETE
✓ HEAD
✓ OPTIONS
✓ Query parameters
✓ Preset headers
✓ Custom headers
✓ Bearer auth
✓ Basic auth
✓ API key auth
✓ Custom auth
✓ JSON body
✓ Form body
✓ Raw body
✓ Status assertions
✓ Response-time assertions
✓ Body assertions
✓ JSON assertions
✓ Response-header assertions
✓ Redirects
✓ SSL verification
✓ Timeout
✓ SSRF protection
✓ Scheduled checks
✓ Check Now
✓ Test Request
✓ Failure threshold
✓ Degraded state
✓ Recovery
✓ Auto incident
✓ Manual incident
✓ Incident updates
✓ Maintenance
✓ Uptime statistics
✓ Response-time graphs
✓ Email test
✓ Incident email
✓ Recovery email
✓ Subscriber verification
✓ Subscriber unsubscribe
✓ Webhook test
✓ Webhook HMAC
✓ Webhook retries
✓ Notification routing
✓ Delivery logs
✓ Public API
✓ Status badge
✓ Live refresh
✓ Branding
✓ Dark/light mode
✓ Audit logs
✓ Settings
✓ Partial-safe settings save
✓ Queue/heartbeat
✓ Retention
✓ Responsive mobile UI
✓ Security input escaping
✓ Rate limits
✓ CSRF
✓ Double-submit protection
```

## Most important automated browser flows

For a real QA run, I would turn the above into these **12 Playwright/Cypress-style end-to-end suites**:

```text
01-auth-and-permissions
02-services-and-request-builder
03-http-monitoring
04-assertions-and-response-validation
05-incidents-and-recovery
06-maintenance
07-email-notifications
08-webhook-notifications
09-subscribers
10-public-status-api-and-badge
11-settings-branding-and-audit
12-security-responsive-and-regression
```

That gives you a clean regression structure instead of having one massive browser test.

For PulseDeck specifically, the **most important tests are not the normal 200-OK checks**. The high-risk areas are **SSRF/redirect validation, failure thresholds, recovery deduplication, notification routing, webhook HMAC/retries, subscriber verification/unsubscribe, maintenance exclusion from uptime, and secret redaction**. Those should be treated as release-blocking tests.

Notification Module — Full Guide
Sidebar: Configure > Notifications (resources/views/layouts/admin-tabler.blade.php:99-112) dropdown with 4 tabs:
Tab
Channels
Rules
Subscribers
Deliveries
Mental model / pipeline
CheckService / IncidentManager / MaintenanceManager
  │ dispatch domain events (no mail/webhook code here)
  ▼
ServiceWentDown, ServiceBecameDegraded, ServiceRecovered,
IncidentCreated/Updated/Resolved, MaintenanceStarted/Ended
  │ listeners in AppServiceProvider::registerNotificationListeners()
  ▼
NotificationManager::notify(event, service?, subject, lines[], url)
  │ gates: per-event setting → per-service flags → active channel+active rule → master switches
  ▼
SendStatusNotification (queued, tries=2, backoff 30/120s) — one job per channel
  │ mail → StatusMailConfig::apply() → StatusAlertMail
  │ webhook → POST JSON + X-Status-Signature
  ▼
status_notification_deliveries row: sent|failed|skipped + recipient_count + error
Key files: app/Services/Status/NotificationManager.php, app/Jobs/Status/SendStatusNotification.php, app/Providers/AppServiceProvider.php:62-152, app/Enums/Status/NotificationEvent.php, app/Enums/Status/NotificationChannelType.php.
1. Channels — where it goes
Model: app/Models/Status/StatusNotificationChannel.php → table status_notification_channels:
id, name, type[mail|webhook|telegram|discord|slack], config(encrypted TEXT), is_active, timestamps
type cast to NotificationChannelType enum. Only mail, webhook return implemented()===true; others are skipped silently in NotificationManager:83 and SendStatusNotification:65 — seam for V3 Slack/Telegram/Discord.
config cast encrypted:array: mail = ['to'=>[...emails]], webhook = ['url'=>..., 'secret'=>...]. Never logged raw.
UI (notifications/channels.blade.php): create mail (name+recipients CSV) or webhook (URL+secret), on/off switch, edit modal, rules_count. Secret field blank-keeps-stored (NotificationChannelController::configFor()).
Permission: status.notifications.manage.
2. Rules — when it fires
Model: app/Models/Status/StatusNotificationRule.php → table status_notification_rules:
id, channel_id(FK cascade), service_id nullable(FK cascade, NULL=all services), event[string 40], is_active, timestamps
event cast to NotificationEvent enum (8 values):
service.failed|degraded|recovered, incident.created|updated|resolved, maintenance.started|ended.
UI (notifications/rules.blade.php): channel + event + service(empty=All) + active. No edit, only create/delete.
Matching logic NotificationManager:48-60: channel must be active AND have at least one active rule where rule.event == fired event AND (rule.service_id IS NULL OR == service.id).
Scoping examples: service.failed + All services = global pager; service.failed + API = only that service. maintenance.* rules always pass service=null, so only global rules match them.
3. Delivery — gates + send + log
3a. Gates in NotificationManager::notify()
Order matters, first fail wins (no job queued):
Per-event kill-switch: StatusSetting::get($event->settingKey()) e.g. notify_on_service_failed. Off → return.
Per-service flags (only these two): service.failed requires $service->notify_on_failure, service.recovered requires notify_on_recovery (status_services cols, edited in service Advanced tab).
No matching active channel/rule → return.
Master switches: mail needs mail_enabled && email_alerts_enabled && StatusMailConfig::isConfigured() (SMTP host set); webhook needs webhook_alerts_enabled.
implemented() check; mail with 0 recipients skipped.
Recipients: channel.config.to (validated emails) + verified subscribers for public events only (SUBSCRIBER_EVENTS = failed, recovered, incident.* — degraded/maintenance excluded). Subscribers query: is_active + verified_at NOT NULL.
3b. Senders in SendStatusNotification job
sendMail(): StatusMailConfig::apply() loads SMTP from status_settings at runtime (no .env restart). Bulk to list gets one mail without footer; each verified subscriber gets individual StatusAlertMail with route('status.unsubscribe', token) footer. StatusAlertMail → resources/views/emails/status-alert.blade.php (logo, eventLabel, subject, lines[], View-status button).
sendWebhook(): endpoint = channel.config.url ?? webhook_default_url. Payload: {event, subject, lines[], url, service_id, sent_at}. If secret (channel.config.secret ?? webhook_secret) set: X-Status-Signature: hmac_sha256(json(payload), secret). Timeout from webhook_timeout (default 10s). ->throw() on non-2xx → caught → failed row.
Failure policy: try/catch → Log::warning(redacted) + record(failed, substr(error,500)). No storm retry. Inactive/deleted channel → skipped.
3c. Delivery log
Table status_notification_deliveries: id, channel_id nullable(nullOnDelete), event, service_id nullable, recipient_count, status[sent|failed|skipped], error nullable, created_at. Privacy: count only, no raw emails. UI (deliveries.blade.php): filter by event/outcome, paginated 25, shows time/channel/event/service/count/badge/error(100 chars). Read-only. Cleaned by status:cleanup via retention setting.
Supporting pieces
Subscribers (public): StatusPageController::subscribe/verify/unsubscribe — firstOrCreate(email), verify mail with one-time verification_token (nulled after), persistent unsubscribe_token for one-click footer + unsubscribe/{token} (deletes row). Only verified+active ever mailed. Throttled 10/min.
Dedup recovery: CheckService::fireTransitionEvents():206 — if IncidentManager auto-resolved in same run ($incidentResolved=true), ServiceRecovered is not fired; only IncidentResolved mail goes out. One recovery = one mail.
Listeners subjects: AppServiceProvider:64-151 e.g. "{name} is down" + error/HTTP/checked-ago lines → /status; incident → /status/incidents/{slug}.
Settings (Alerts/Mail/Webhook groups): mail_enabled, email_alerts_enabled, webhook_alerts_enabled, 8x notify_on_*, subscriptions_enabled, SMTP host/port/user/pass/encryption/from, webhook_default_url/secret/timeout. Test buttons in Settings send test mail/webhook.
Audit: channel/rule create/delete → StatusAuditLog::record().
For future devs
Add channel type: add enum case → implemented()=true → add match arm in SendStatusNotification::handle() + config handling in NotificationChannelController.
Add event: add enum case + label()+settingKey() → add listener in AppServiceProvider → rules UI picks it up automatically (NotificationEvent::cases()).