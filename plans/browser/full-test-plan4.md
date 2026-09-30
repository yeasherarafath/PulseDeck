# PulseDeck — Browser Test Plan 4 (page-by-page, function-by-function)

> Successor to `full-test-plan*.md`. This plan is organised **per page**, then **per function on that page**,
> and ends with cross-cutting suites. Every case has an ID, steps, expected result, and a Status column that is
> filled while executing. Bugs found are logged in section 12 with the fix and the proof.
>
> Status legend: `Pass` · `Fail` (bug logged) · `Fixed` (re-tested after fix) · `Todo` · `N/A`

## 0. Environment and harness notes (learned in the phase-1 run)

| Item | Value |
|---|---|
| App | `php artisan serve --port=8000` → `http://localhost:8000` (APP_URL must be `http://localhost:8000`, http; https is forced only when APP_URL is https) |
| DB | sqlite (`database/database.sqlite`), reset with `php artisan migrate:fresh --seed --force` |
| Assets | `npm ci && npm run build` (without it every page 500s: "Vite manifest not found") |
| Login (local seed only) | `admin@example.com` / `password` |
| Queue | No worker runs by default. Drain on demand: `php artisan queue:work --stop-when-empty --tries=1` |
| Scheduler | Run once by hand: `php artisan status:dispatch-due` |
| Mail | Settings → Email: mailer `smtp`, host `localhost`, port `1025`, encryption `none`, from `status@example.com`, enable switch ON. Mailpit UI `http://localhost:8025`, API `http://localhost:8025/api/v1/search?query=to:addr` |
| Network | Fixtures use `https://example.com/` (200) and `https://example.com/missing-page` (404). Private/loopback URLs are blocked by design (SSRF) |
| Baseline | `php artisan test` must be green before and after (69 tests at time of writing) |

Automation tips (Claude in Chrome):

1. Screenshot frame (1568 px) is scaled against a 1920 px viewport; coordinate clicks on small targets are unreliable. Prefer `find` + `ref`, or drive forms with `javascript_exec` (`form.requestSubmit()` inside `setTimeout` so the tab is not destroyed mid-evaluation).
2. Modals: open with the real trigger (`[data-bs-toggle=modal]`). Never call `window.bootstrap` — Tabler bundles its own Bootstrap and it is **not** exposed on `window` (see BUG-B4).
3. Enter inside a text input submits the enclosing form (reliable fallback when a button click does not register).
4. Do not leave native `confirm()` dialogs unhandled: delete buttons use `onsubmit="return confirm(...)"`; override with `window.confirm = () => true` before clicking, or submit the form via JS.
5. Mailpit inbox contains unrelated mail from other projects — always filter by recipient/subject, never "delete all".

Fixtures created by the seed step of this plan (create them once, reuse):

| Fixture | Value |
|---|---|
| Group | `Websites` |
| Service A | `Example Site` — `https://example.com/` — group Websites — public — defaults |
| Service B | `Broken API` — `https://example.com/missing-page` — failure_threshold 1, recovery_threshold 1 |
| Service C | `Internal API` — `https://example.com/` — **not public** |
| Mail channel | `Ops email` → `ops@example.com` |
| Rules | service.failed, service.recovered, incident.created, incident.resolved → Ops email |
| Subscriber | `fan@example.com` (subscribe + verify through Mailpit link) |
| Users | `admin@example.com` (super-admin); `manager@example.com` (status-manager); `viewer@example.com` (status-viewer) — create via tinker |

---

## 1. Authentication pages (`/login`, `/forgot-password`, `/reset-password/{token}`, logout)

| ID | Function | Steps | Expected | Status |
|---|---|---|---|---|
| AUTH-01 | Login page renders | GET `/login` | Logo, email, password, remember, sign-in, forgot link; theme toggle works | Pass |
| AUTH-02 | Valid login | Seed creds → Sign in | Redirect to `/admin/status`, dashboard shown, session cookie set | Pass |
| AUTH-03 | Invalid password | Wrong password | Stays on `/login`, error under email, email kept, no user enumeration text | Todo |
| AUTH-04 | Empty submit | Submit blank | Native `required`/server message, no 500 | Todo |
| AUTH-05 | Per-IP throttle | 6 wrong logins in 60 s | 429 page (themed) on 6th | Pass |
| AUTH-06 | Per-email limiter | 5 wrong logins for one email from fresh session | Message "Too many login attempts. Try again in N seconds." | Todo |
| AUTH-07 | Limiter reset | Correct login after limiter cleared | Login succeeds | Todo |
| AUTH-08 | Guest → admin | GET `/admin/status` logged out | Redirect `/login`; after login lands on intended URL | Todo |
| AUTH-09 | Logged-in → /login | GET `/login` while logged in | Redirect (guest middleware) | Todo |
| AUTH-10 | Logout | Top-right menu → Log out | Session ended, redirect to public status page, admin URL redirects to login | Todo |
| AUTH-11 | Forgot password | Enter admin email | Flash "reset link sent"; mail in Mailpit via Settings mailer | Todo |
| AUTH-12 | Forgot unknown email | Enter unknown email | Same neutral response (see minor note AUTH-05 in results-2026-09-29) | Todo |
| AUTH-13 | Reset with token | Open mailed link, set new password, login | Works; old password rejected; password restored afterwards | Todo |
| AUTH-14 | Reset bad/expired token | Tamper token | Error, no crash | Todo |
| AUTH-15 | CSRF | POST `/login` without `_token` (fetch) | 419 page | Pass |

## 2. Dashboard (`/admin/status`)

| ID | Function | Steps | Expected | Status |
|---|---|---|---|---|
| DASH-01 | Stat cards | Open with 2 services (1 up, 1 down) | Totals match DB; labels on one line (BUG-B2 fixed) | Fixed |
| DASH-02 | Engine heartbeat | Before any check / after `dispatch-due` | "Not running" → "Running" with age | Pass |
| DASH-03 | Current incidents | Open incident exists | Listed with status badge and relative time | Pass |
| DASH-04 | Services with problems | Outage service | Listed, links to service | Pass |
| DASH-05 | Recent checks | After checks | Newest first with ms and HTTP code | Pass |
| DASH-06 | New service button | Click | Goes to create form | Todo |
| DASH-07 | Empty state | Fresh DB | "No active incidents", "No checks recorded yet", zero cards, no errors | Pass |
| DASH-08 | Viewer role | Login as viewer | Page renders; "New service" hidden | Todo |
| DASH-09 | Dark mode | Toggle | Cards/badges readable, no hardcoded white | Todo |
| DASH-10 | Console | Load page | No JS errors | Pass |

## 3. Services

### 3.1 List (`/admin/status/services`)

| ID | Function | Expected | Status |
|---|---|---|---|
| SVC-L01 | Columns Service/Group/Status/Response/Last checked/Interval/Active/Actions | Correct data, badges | Todo |
| SVC-L02 | Search `?search=` | Filters by name/url, keeps query on paginate | Todo |
| SVC-L03 | Filters group/status/active | Combine; Reset clears | Todo |
| SVC-L04 | Row actions View / Edit / Check now / Pause / Delete | Each works; delete asks confirmation, Cancel keeps row | Todo |
| SVC-L05 | Pagination (seed 30 services) | Tabler pager, page 2 correct | Todo |
| SVC-L06 | Empty state | Friendly message + create button | Todo |

### 3.2 Create / edit form (`/services/create`, `/services/{slug}/edit`) — tabs General · Request · Auth · Assertions · Advanced

| ID | Function | Steps | Expected | Status |
|---|---|---|---|---|
| SVC-F01 | Required validation | Submit empty | Field errors, "Please fix N error(s)", input preserved | Todo |
| SVC-F02 | Create minimal | Name + URL | Redirect to edit, flash "Service [X] created.", slug auto, appears on public page as Unknown | Pass |
| SVC-F03 | Slug custom/duplicate | Reuse an existing slug | Unique error | Todo |
| SVC-F04 | URL safety | `http://127.0.0.1/`, `http://localhost`, `ftp://x`, `http://169.254.169.254` | Rejected with clear message | Todo |
| SVC-F05 | Method select | GET/POST/PUT/PATCH/DELETE/HEAD/OPTIONS saved | Pass |
| SVC-F06 | Interval select | 60/300/600/900/1800/3600 saved; default follows Settings default (invalid default falls back to 5 min) | Todo |
| SVC-F07 | Request tab: query rows | Add/remove rows, values encoded | Todo |
| SVC-F08 | Request tab: headers | Presets (Tom Select grouped), custom header, template apply keeps existing rows | Pass |
| SVC-F09 | Sensitive header | Value masked, blank on edit keeps stored | Todo |
| SVC-F10 | Body types none/json/form/urlencoded/raw | JSON validated (Monaco/textarea); invalid JSON error | Pass |
| SVC-F11 | Auth types none/bearer/basic/api_key/custom | Correct sub-fields; secrets never prefilled; blank keeps | Pass |
| SVC-F12 | Assertions: expected statuses | `200,204`; empty invalid | Pass |
| SVC-F13 | Assertions: warn/fail ms | `fail >= warn` enforced | Todo |
| SVC-F14 | Assertions: body contains/not/equals/regex | Row add/remove; regex `(a+)+b` cannot hang worker (SafeRegex) | Pass |
| SVC-F15 | Assertions: JSON path/operator | `$.` prefix required; 9 operators | Pass |
| SVC-F16 | Assertions: header | operator list | Todo |
| SVC-F17 | Advanced: timeout, connect timeout | `connect <= timeout`, 1–60 | Todo |
| SVC-F18 | Advanced: redirects, max redirects, verify SSL, HTTP version, UA | Saved and honoured by check | Todo |
| SVC-F19 | Advanced: thresholds, min failed checks, notify switches | Persist (BUG-01 regression) | Pass |
| SVC-F20 | Active / Public switches | Public off → hidden everywhere on public side | Todo |
| SVC-F21 | Test request modal (unsaved) | Modal opens with result table: method/url, HTTP, result badge, timings, final URL, size, assertions | Pass |
| SVC-F22 | Test again | Re-runs | Pass |
| SVC-F23 | Test with bad config | 422 → "Validation failed" list, no crash | Pass |
| SVC-F24 | Test throttle | 11 rapid tests | 429 after 10/min | Todo |
| SVC-F25 | Cancel link | Returns to list, nothing saved | Todo |
| SVC-F26 | Edit prefill | All tabs prefilled; secrets blank | Pass |
| SVC-F27 | Update | Flash "updated"; changes visible | Pass |

### 3.3 Show page (`/services/{slug}`)

| ID | Function | Expected | Status |
|---|---|---|---|
| SVC-S01 | Status card | Current status, 30-day uptime %, checks x/y, avg response | Pass |
| SVC-S02 | Configuration list | Values match form incl. "global default (n)" fallbacks | Todo |
| SVC-S03 | Check now | Flash "Check queued"; after worker a history row appears | Pass |
| SVC-S04 | **Test request** button (new, BUG-B4) | Modal opens via Tabler trigger, shows result/HTTP/time/final URL/error/failed assertions | Fixed |
| SVC-S05 | History table | 25/page, failed rows show HTTP, ms, error, expandable failed assertions | Todo |
| SVC-S06 | Edit button hidden for viewer | Todo |

### 3.4 Pause/resume and delete

| ID | Function | Expected | Status |
|---|---|---|---|
| SVC-P01 | Pause | `is_active=0`, scheduler skips, Monitoring shows paused | Todo |
| SVC-P02 | Resume | `is_active=1` | Todo |
| SVC-D01 | Delete with confirm | Row, checks, incidents cascade per FK; audit entry | Todo |

## 4. Groups (`/admin/status/groups`)

| ID | Function | Expected | Status |
|---|---|---|---|
| GRP-01 | New group modal opens | Name, slug, description, sort order, active | Pass |
| GRP-02 | Create (Enter or button) | Flash "Group [X] created", slug auto; API-style POST without slug does not 500 (BUG-B1) | Fixed |
| GRP-03 | Empty name | Native required / server error | Todo |
| GRP-04 | Duplicate slug | Error | Todo |
| GRP-05 | Edit modal prefill + save | Rename, reorder, deactivate persist | Todo |
| GRP-06 | Delete with services | Services keep working, become ungrouped | Todo |
| GRP-07 | Public grouping order | sort_order respected; inactive group hidden | Todo |

## 5. Incidents (`/admin/status/incidents`)

| ID | Function | Expected | Status |
|---|---|---|---|
| INC-01 | List + filters | Status/impact/service filters, pagination | Todo |
| INC-02 | Manual create | Title/service/impact/initial update; public page shows it | Todo |
| INC-03 | Create validation | Missing title/message | Todo |
| INC-04 | Show page timeline | Updates in order; add update with status | Todo |
| INC-05 | Post update to resolved | Resolved timestamp set; resolved mail sent once | Todo |
| INC-06 | Edit | Title/impact/status; transition to resolved fires event once | Todo |
| INC-07 | Delete with confirm | Removed from public page | Todo |
| INC-08 | Auto-open | Broken API fails (threshold 1) → incident "Broken API is down", public banner "Major outage in progress" | Pass |
| INC-09 | Auto-resolve | Fix URL, check → incident resolved, single resolved mail per recipient, no extra "recovered" mail | Pass |
| INC-10 | Private service incident | `Internal API` incident: 404 on public incident page, absent from `/api/status/incidents`, home, and subscriber mail | Pass |
| INC-11 | Public incident page | Timeline, badges, service name | Todo |

## 6. Maintenance (`/admin/status/maintenances`)

| ID | Function | Expected | Status |
|---|---|---|---|
| MNT-01 | Create form fields | Title, description, start/end (datetime-local), services checkboxes | Pass |
| MNT-02 | Validation | end before start; no service selected | Todo |
| MNT-03 | Schedule future window | Blue card on public page; status Scheduled | Pass |
| MNT-04 | Active window | Set start = now−1 min → `dispatch-due` → Active; affected service shows "Scheduled Maintenance", check result Maintenance, no incident/mail | Pass |
| MNT-05 | Window ends | Completed; service returns to Operational on next check; ended event | Todo |
| MNT-06 | Expired-unsynced window | Scheduler down past end → Completed on next sync | Todo |
| MNT-07 | Cancel | Status Cancelled, disappears from public | Todo |
| MNT-08 | Edit / delete with confirm | Todo |

## 7. Notifications

### 7.1 Channels

| ID | Function | Expected | Status |
|---|---|---|---|
| NCH-01 | Create mail channel | Recipients CSV validated, listed | Pass |
| NCH-02 | Create webhook channel | URL required; private/loopback URL rejected unless `STATUS_WEBHOOK_ALLOW_PRIVATE=true` | Todo |
| NCH-03 | Edit / toggle active / delete | Todo |
| NCH-04 | Secret handling | Webhook secret never shown; blank keeps | Todo |

### 7.2 Rules, subscribers, deliveries

| ID | Function | Expected | Status |
|---|---|---|---|
| NRL-01 | Create rule (channel, event, service or all) | Listed | Pass |
| NRL-02 | Duplicate/inactive rule | Handled | Todo |
| NRL-03 | Delete rule | Todo |
| NSB-01 | Subscribers list | Email, verified, active, actions | Todo |
| NSB-02 | Toggle / delete | Todo |
| NDL-01 | Deliveries list | Time, channel, event, service, recipients, outcome; filters by event/outcome | Pass |
| NDL-02 | Failed delivery | Wrong SMTP port → `failed` row with error, no secrets | Todo |
| NMAIL-01 | Failure mail | `Broken API is down` to ops + verified subscriber | Pass |
| NMAIL-02 | Incident opened mail | to ops + subscriber | Pass |
| NMAIL-03 | Resolved mail | one per recipient, unsubscribe link only for subscriber | Pass |
| NMAIL-04 | Master switches | `email_alerts_enabled` off → no mail; per-event `notify_on_*` off → none | Todo |
| NMAIL-05 | Per-service notify flags | Off → no failure/recovery mail | Todo |
| NMAIL-06 | Retry idempotency | Fail SMTP mid-list, retry → earlier recipients not re-mailed | Todo (unit-level in code, browser optional) |

## 8. Settings (`/admin/status/settings`, permission `status.settings.manage`)

| ID | Tab | Function | Expected | Status |
|---|---|---|---|---|
| SET-01 | General | Save app name/tagline/description | Public page + emails follow; `app_name` max 100 | Todo |
| SET-02 | General | Timezone dropdown (grouped) | Absolute times shift on public/admin pages; invalid rejected | Pass |
| SET-03 | General | Admin prefix change | Admin moves after cache clear; login redirect follows; revert afterwards | Todo |
| SET-04 | Branding | Upload PNG logo, dark logo, favicon | Preview, appears in header (light/dark), favicon | Todo |
| SET-05 | Branding | Upload clean SVG | Stored sanitised | Todo |
| SET-06 | Branding | Upload malicious SVG (script, `onload`, `javascript:` href, DOCTYPE/entity) | Sanitised or rejected with message; old logo kept when rejected | Pass |
| SET-07 | Branding | Remove image | File deleted, setting cleared | Todo |
| SET-08 | Branding | Footer text | Rendered in public footer | Todo |
| SET-09 | Monitoring | Default interval/timeout/connect timeout | New service form uses them; connect > timeout rejected | Todo |
| SET-10 | Monitoring | failure/recovery thresholds, min failed checks, stale multiplier | Live behaviour changes (BUG-09 regression) | Todo |
| SET-11 | Public page | Toggles page/API/subscriptions/badge | Each disabled surface 404/403; subscribe card hidden when subscriptions off | Todo |
| SET-12 | Public page | Refresh seconds, history window | Applied to polling and bars | Todo |
| SET-13 | Email | Save SMTP (localhost:1025, none) + enable | Persisted, password field blank | Pass |
| SET-14 | Email | Send test email | Flash "Test email sent"; message in Mailpit | Pass |
| SET-15 | Email | Wrong port | Flash error, no stack trace, no secret | Todo |
| SET-16 | Email | Clear stored password checkbox | Secret removed; blank without tick keeps | Todo |
| SET-17 | Alerts | Master + per-event switches | See NMAIL-04 | Todo |
| SET-18 | Webhook | Default URL/secret/timeout; Send test webhook | Private URL blocked with error; public URL delivers | Todo |
| SET-19 | Retention | min 1 enforced; run `status:cleanup` | Old rows purged | Todo |
| SET-20 | Any | Partial-safe save | One bad value does not wipe others | Todo |
| SET-21 | Any | Viewer/manager access | 403 for non-super-admin | Todo |

## 9. Monitoring and Audit

| ID | Function | Expected | Status |
|---|---|---|---|
| MON-01 | Queue depth / failed jobs / heartbeat | Values match `jobs`/`failed_jobs`/cache | Pass |
| MON-02 | Per-service rows | Status, last/next check, response ms, last error | Pass |
| MON-03 | Stale service | No check for interval×multiplier → Unknown after `dispatch-due` | Todo |
| AUD-01 | Audit list | Entries for group/service/channel/rule/settings with user and IP | Pass |
| AUD-02 | Filter by action, Reset | Todo |
| AUD-03 | No secrets | Settings entry shows keys only; service entry redacted | Todo |

## 10. Public pages

### 10.1 Home (`/` and `/status`)

| ID | Function | Expected | Status |
|---|---|---|---|
| PUB-01 | Overall banner | Operational / Degraded / Partial / Major / Maintenance / **Unknown when nothing checked yet** (BUG-B3) | Fixed |
| PUB-02 | Groups and rows | Group names, per-service badge, "checked x ago" | Pass |
| PUB-03 | 90-day bars | One bar per day, tooltips date/%/counts; scroll on mobile | Todo |
| PUB-04 | Active incidents card | Red card, link to incident | Pass |
| PUB-05 | Maintenance card | Blue card with window | Pass |
| PUB-06 | Private service hidden | Never on home | Pass |
| PUB-07 | Live refresh | After a check, badge updates within refresh interval with no reload; text injected safely (BUG-B5) | Todo |
| PUB-08 | Server time | Shown in configured timezone | Todo |
| PUB-09 | Footer links | Status, Incident History anchor, API | Todo |
| PUB-10 | Theme toggle | Persists across reload, default light | Pass |
| PUB-11 | Page disabled | `public_page_enabled` off → 404 | Todo |
| PUB-12 | Cache | Flip status → page reflects within seconds | Todo |

### 10.2 Service page (`/status/services/{slug}`)

| ID | Function | Expected | Status |
|---|---|---|---|
| PUB-S01 | Details | Status, 90-day uptime, avg response | Todo |
| PUB-S02 | Charts | Uptime + response charts render, follow theme | Todo |
| PUB-S03 | Recent incidents | Only this service | Todo |
| PUB-S04 | Private / unknown slug | 404 | Todo |

### 10.3 Subscriptions

| ID | Function | Expected | Status |
|---|---|---|---|
| SUB-01 | Subscribe valid email | Flash "check your email"; verification mail in Mailpit | Pass |
| SUB-02 | Invalid email | Validation error under field | Todo |
| SUB-03 | Repeat subscribe | No duplicate row | Todo |
| SUB-04 | Verify link | Flash "Subscription confirmed"; token consumed (second use 404) | Pass |
| SUB-05 | Unverified gets no incident mail | Pass |
| SUB-06 | Unsubscribe GET | Confirmation page only; row still exists | Pass |
| SUB-07 | Unsubscribe POST | Row deleted, flash "You have been unsubscribed." | Pass |
| SUB-08 | Reused/invalid link | GET 404; POST flash "no longer valid" | Todo |
| SUB-09 | Subscriptions disabled | Form hidden, endpoint 404 | Todo |

### 10.4 Machine endpoints

| ID | Function | Expected | Status |
|---|---|---|---|
| API-01 | `/api/status` | `{ok:true,data:{status,...}}` | Todo |
| API-02 | `/api/status/services`, `/services/{slug}` | Public only; private 404 | Todo |
| API-03 | `/api/status/incidents` | No private-service incidents | Pass |
| API-04 | API disabled | 403 | Todo |
| API-05 | Rate limit | 61st request/min → 429 | Todo |
| BDG-01 | `/status/badge.svg` | SVG, colour by state, label escaped, cache header | Todo |
| REF-01 | `/status/refresh` | JSON used by polling | Todo |

### 10.5 Error pages

| ID | Function | Expected | Status |
|---|---|---|---|
| ERR-01 | 404 (public + admin) | Themed page, link home | Todo |
| ERR-02 | 403 (viewer on settings) | Themed | Todo |
| ERR-03 | 419 | Themed | Todo |
| ERR-04 | 429 | Themed | Todo |
| ERR-05 | 500 with APP_DEBUG=false | No stack trace | Todo |

## 11. Cross-cutting suites

| ID | Suite | Steps | Expected | Status |
|---|---|---|---|---|
| XC-01 | Roles: super-admin | Everything reachable | Todo |
| XC-02 | Roles: status-manager | All except Settings and Audit | Todo |
| XC-03 | Roles: status-viewer | Read-only: no create/edit/delete/check buttons, POSTs 403 | Pass |
| XC-04 | Dark mode all admin pages | No unreadable text; charts/Monaco follow | Pass (public home + service page; admin pages still to sweep) |
| XC-05 | Mobile 375 px | Sidebar collapses, tables scroll inside cards, no page-level horizontal scroll, modals fit | Pass |
| XC-06 | Tablet 768 px | Todo |
| XC-07 | Keyboard | Tab order in forms, Esc closes modal, Enter submits | Todo |
| XC-08 | Console clean | No errors on any page listed above | Pass so far |
| XC-09 | XSS probes | Service/group/incident/channel names `"><img src=x onerror=alert(1)>` render escaped everywhere (admin, public, mail, JS polling) | Pass |
| XC-10 | CSRF/method | State-changing GET routes do not exist (unsubscribe GET is read-only) | Pass |
| XC-11 | Double submit | Rapid double click on Create service | One row (or unique error), no 500 | Todo |
| XC-12 | Timezone consistency | Same event shows same instant on admin/public/mail (configured tz) | Todo |
| XC-13 | Scheduler + worker end to end | Run `status:dispatch-due` twice a minute apart; checks appear; heartbeat Running | Todo |
| XC-14 | Concurrency lock | Two Check now clicks quickly | One check row per run (lock) | Todo |
| XC-15 | Performance | 50 services: home < 1 s cached, admin lists < 1 s | Todo |

## 12. Bug log

| ID | Where | Problem | Fix | Proof |
|---|---|---|---|---|
| BUG-B1 | `GroupController@store/update` | POST without `slug` key → 500 `Undefined array key "slug"` | `($validated['slug'] ?? null) ?:` | `AdminSmokeTest::test_group_service_lifecycle` + browser create (GRP-02) |
| BUG-B2 | Dashboard stat cards | Label "OPERATIONAL" wrapped to "OPERATION / AL" | `text-nowrap` on `.subheader` | DASH-01 |
| BUG-B3 | Public banner | "All Systems Operational" shown when every service is still Unknown (never checked) | `PublicStatusService::overallStatus` returns Unknown if all statuses Unknown | PUB-01 |
| BUG-B4 | Service show page Test button | Used `window.bootstrap` (undefined: Tabler bundles its own) → modal never opened | `data-bs-toggle="modal"` trigger | SVC-S04 |
| BUG-B5 | `status-page.js` live refresh | Status text injected via `innerHTML` (XSS from API value) | DOM nodes + `textContent` | PUB-07, code review |
| BUG-B6 | Regex assertions | User regex could catastrophically backtrack and stall a worker | `SafeRegex` (JIT off, backtrack cap) | `SafeRegexTest` |
| BUG-B8 | Maintenance form | `datetime-local` value was stored as UTC regardless of the configured display timezone, so with timezone Asia/Dhaka a window entered as 14:00 became 14:00 UTC (= 20:00 Dhaka) and started 6 h late; index page showed raw UTC | `setting_input_to_utc()` on save, `setting_utc_to_input()` on edit, `setting_time()` on the list, timezone hint under the field | `AdminSmokeTest::test_maintenance_times_are_entered_in_the_display_timezone`; browser: window activated on `dispatch-due`, service showed Scheduled Maintenance, public card time correct |
| BUG-B9 | SVG sanitizer | `<a>` wrapper dropped with its children → logos wrapped in links became empty | Unwrap `a`/`switch`, keep sanitised children | `SvgSanitizerTest::test_anchor_wrappers_keep_their_shapes` |
| BUG-B7 | Migrations | `status_daily_stats.date`, `status_audit_logs.created_at` unindexed for retention deletes | New migration | `migrate:fresh` clean |

Phase 2 (2026-09-30, same day): viewer role verified in browser (write buttons hidden, Settings/Audit/Notifications absent from menu, POST check/pause/delete and settings/audit/channels all 403); private-service incident 404 on page and absent from `/api/status/incidents` and home; XSS probe service name `"><img src=x onerror=...>` escaped on public home, admin list, show and edit (no `window.__xss`); dark mode persists and charts follow it on public pages.

Phase 3 (same day): login throttle returns 429 on the 6th wrong attempt; maintenance window created in Asia/Dhaka activates and forces Maintenance state with no false alarm; SVG upload via browser: script/onload/javascript:/foreignObject stripped, XXE upload rejected and previous logo kept; request builder POST + JSON body + bearer + custom header + JSON assertion + body assertion passes against httpbin and reports expected/actual on a failing assertion; saved secrets are masked on edit; 375 px iframe sweep of 21 public/admin pages shows no page-level horizontal overflow (window resize is not honoured by this browser, so an iframe was used).

Execution log for phase 1 (2026-09-30): logged in, created group/service via UI, Test request modal (unsaved and saved),
Mailpit SMTP test email, subscribe → verify → unsubscribe (confirm page), channel + 4 rules, failing service →
auto incident → ops + subscriber mails → recovery → single resolved mail, deliveries and audit pages, dashboard and
monitoring. Remaining `Todo` rows are to be executed in the next run in this order: sections 11 (roles) → 5/6 → 8 → 10 → 1 → 3.
