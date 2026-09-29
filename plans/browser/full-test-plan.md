# PulseDeck — Full Browser Test Plan (every page, every control)

> Functional end-to-end verification in a real browser. Not CRUD-only: each module is
> tested for **whether the feature actually works** (checks run, statuses flip, mails
> arrive, banners change). Execute top-to-bottom with the browser-control session;
> screenshot each module. Record results in [§13 Results log](#13-results-log).

- Base URL: `https://status-page.test/` (never `php artisan serve`).
- Admin root: `https://status-page.test/admin/status` (prefix changeable in Settings).
- Companion automated suite: `php artisan test` (19 tests) must stay green alongside.

## 1. Environment & fixtures (do once, before AUTH-01)

1. Fresh DB: `php artisan migrate:fresh --seed` (seeds roles, header presets/templates,
   default settings, local admin).
2. Queue worker running: `php artisan queue:work` (separate terminal). Scheduler running
   (`php artisan schedule:run` loop or cron) — or dispatch manually where noted.
3. Mail catcher running (dev: Mailpit on the configured SMTP port). A webhook catcher
   ready (e.g. webhook.site URL) for NCH/NRL drills.
4. Fixtures to create during the run (do not pre-create — creation is part of the test):
   `GRP-WEB` “Websites”, `GRP-API` “APIs” groups; services `SVC-GOOD` (healthy URL),
   `SVC-BAD` (unroutable URL, used for the failure drill), `SVC-PRIVATE` (is_public off).
5. Viewports: desktop 1440px, tablet 768px, mobile 375px. Themes: light + dark on every
   key page (toggle in header, reload, must persist).
6. Three users (create via tinker): super-admin (`admin@example.com` / `password`),
   one `status-manager`, one `status-viewer`.

## 2. AUTH — login, logout, password, access control

| ID | Steps | Expected |
|---|---|---|
| AUTH-01 | Guest visits `/admin/status` | 302 to `/login` |
| AUTH-02 | Login with wrong password | Back to login with error, no session |
| AUTH-03 | Login as super-admin (valid) | Lands on dashboard, name/menu visible |
| AUTH-04 | Logout via menu | Session ends, admin URLs redirect to login again |
| AUTH-05 | Forgot-password with unknown email | Generic “sent if exists” style response, no user leak |
| AUTH-06 | Forgot-password with admin email → open reset link from mail catcher → set new password → login | Full reset loop works; old password rejected |
| AUTH-07 | Login as `status-viewer`: open Dashboard, Services, Monitoring, Incidents, Maintenance | All render; **no** New/Edit/Delete/Check-now/Settings controls visible |
| AUTH-08 | As viewer, POST directly to service store / settings update / notification store (via devtools or typed URL submit) | 403 on each |
| AUTH-09 | As viewer, visit `/admin/status/settings` and `/admin/status/notifications/channels` directly | 403 page, not a crash |
| AUTH-10 | As `status-manager`: Services/Incidents/Maintenance/Notifications fully usable; visit Settings | Settings 403; everything else 200 |
| AUTH-11 | Rapid wrong-password logins (6×) | Throttled (429-style lockout message) — throttle is 5/min |

## 3. NAV — admin shell (check on every page, record once)

| ID | Steps | Expected |
|---|---|---|
| NAV-01 | Sidebar: click each section (Dashboard, Monitoring, Services, Groups, Incidents, Maintenance, Notifications, Settings, Audit) | Each loads 200; active item highlighted |
| NAV-02 | Notifications dropdown: Channels / Rules / Subscribers / Deliveries | All four render; active tab highlighted |
| NAV-03 | Scroll any long page (Services index, Check history) | Top bar stays sticky, never overlaps content |
| NAV-04 | Theme toggle → dark → reload → toggle → light → reload | Choice persists; no unstyled flash; sidebar readable in both |
| NAV-05 | Narrow to 375px: sidebar collapses/behaves, tables scroll horizontally inside cards (page itself must not scroll sideways except table/uptime areas) | No page-level horizontal overflow |

## 4. DASH — dashboard (`/admin/status`)

| ID | Steps | Expected |
|---|---|---|
| DASH-01 | Compare the five count cards vs DB (`StatusService` counts by state) | Numbers match exactly |
| DASH-02 | With worker running: heartbeat card | Green “Running” + “seconds/minutes ago” |
| DASH-03 | Stop worker + scheduler, wait 6 min, reload | Red “Stale” + guidance text (restart worker after) |
| DASH-04 | Active incidents card: click a row | Lands on that incident’s admin page |
| DASH-05 | Services-with-problems card | Lists only non-operational services, each links to service page |
| DASH-06 | Recent checks card | Latest checks with relative times + HTTP + ms; badge colors correct |
| DASH-07 | Fresh DB (no incidents/problems/checks) | Three friendly empty states, no errors |

## 5. MON — monitoring (`/admin/status/monitoring`)

| ID | Steps | Expected |
|---|---|---|
| MON-01 | Queued-jobs card before/after pressing “Check now” on a service | Count rises, then falls as worker processes |
| MON-02 | Failed-jobs card | Reflects `failed_jobs` table (normally 0) |
| MON-03 | Per-service rows | Name links to service page; Status badge = DB `current_status`; Last/Next check relative times sane |
| MON-04 | Response column after a check completes | Shows real ms (not “—”); Last-error shows truncated message or empty |
| MON-05 | Force a failure (SVC-BAD check) | Row status flips, error text appears truncated ≤ ~80 chars |

## 6. SVC — services

### 6a. Index (`/admin/status/services`)

| ID | Steps | Expected |
|---|---|---|
| SVC-01 | Search by partial name | Only matches; case-insensitive |
| SVC-02 | Filter group + status + active together, then Reset | Combined filtering correct; Reset clears all |
| SVC-03 | Interval column (e.g. 300s, 3600s) | Renders “5m”, “1h” style labels |
| SVC-04 | Active/Paused badges | Match `is_active`; paused service keeps last status |
| SVC-05 | Actions → View | Service show page |
| SVC-06 | Actions → Edit | Edit form prefilled |
| SVC-07 | Actions → Check now | Flash confirmation; new check row appears in history; Monitoring “Last check” updates |
| SVC-08 | Actions → Pause → confirm it sticks → Resume | `is_active` flips; paused service is skipped by scheduler (no new checks while paused); resume re-enables |
| SVC-09 | Actions → Delete → **Cancel** the confirm dialog | Nothing deleted |
| SVC-10 | Actions → Delete → confirm | Service + its checks/history gone; flash message; audit entry |
| SVC-11 | Pagination (create 20+ services or lower per-page) | Pager works, filters persist across pages |

### 6b. Create (`/admin/status/services/create`) — all five tabs

| ID | Steps | Expected |
|---|---|---|
| SVC-12 | Submit empty form | Validation errors on name + URL, values preserved |
| SVC-13 | General: name, auto slug, description, group, URL, method, interval, sort order, active + public switches | Saved; slug auto-generated from name when blank |
| SVC-14 | URL = `http://127.0.0.1:9/x` (private IP) → save or Test | **SSRF block**: clear error, nothing stored as success |
| SVC-15 | Request tab: custom headers (add 2), header preset insert, template insert, body JSON/Form/Raw each | Persisted and shown on re-edit; Test reflects body type |
| SVC-16 | Auth tab: each type — None, Bearer (token), Basic (user/pass), API Key (header+key), Custom | Saved; secrets never shown back (•••• placeholders); blank-on-edit preserves existing secret |
| SVC-17 | Assertions tab: expected codes “200, 201”, warn 1000 ms / fail 3000 ms, body-contains, JSON-field, header asserts | Saved; violations later surface as failed assertions in history |
| SVC-18 | Advanced tab: timeouts, follow-redirects on/off, max redirects 0 and 20 bounds, min-failures-down blank (= global) and 3, SSL verify off, HTTP version, custom UA, notify on/off switches | Bounds enforced; blank min-failures shows “global default (N)” on show page |
| SVC-19 | Create with is_public **off** | Saved; absent from public home, API, and `/status/services/{slug}` 404s |
| SVC-20 | Successful create | Redirects to show page with success flash + audit-log entry |

### 6c. Test Request modal (create + edit pages)

| ID | Steps | Expected |
|---|---|---|
| SVC-21 | Valid unsaved config → Test request | Result panel: HTTP status, ms timings, pass/fail, no save |
| SVC-22 | Bad URL / unreachable host → Test | Readable error (not exception page, not saved) |
| SVC-23 | Private IP → Test | SSRF refusal message |
| SVC-24 | Config that violates own assertion → Test | Failed-assertion detail listed |
| SVC-25 | Saved service page → Test (saved-config endpoint) | Same behavior against stored config |
| SVC-26 | Spam Test 11× quickly | Throttled after 10/min |

### 6d. Show (`/admin/status/services/{id}`)

| ID | Steps | Expected |
|---|---|---|
| SVC-27 | Status card: badge, 30-day uptime %, successful/total, avg 24h response | All match DB/calculator values |
| SVC-28 | Configuration list: URL+method, group, interval/timeouts, min-failures (incl. “global default” fallback text), notify on/off, expected codes, last checked/success/failure/next | Every line accurate |
| SVC-29 | Check history: result badges, HTTP, ms, error truncation (~120 chars), failed-assertion `<details>` expands | Expandable failures list exact messages |
| SVC-30 | History pagination | Pager works |
| SVC-31 | Check now + Edit buttons | Same behavior as index; Edit button hidden for viewer role |

## 7. GRP — groups (`/admin/status/groups`)

| ID | Steps | Expected |
|---|---|---|
| GRP-01 | New group modal: empty name → submit | Validation error, modal keeps input |
| GRP-02 | Create “Websites” (slug auto), order 1, active | Row appears with service count 0 |
| GRP-03 | Edit modal: rename + reorder + deactivate | Persists; deactivated group’s services still listed under it in admin |
| GRP-04 | Assign SVC-GOOD to Websites → public home | Service renders under “Websites”; group order respected vs GRP-API |
| GRP-05 | Delete empty group → confirm | Gone + flash |
| GRP-06 | Delete group **with services** → confirm | Record actual behavior (services must not be orphaned/deleted silently — flag if they are) |
| GRP-07 | Delete → Cancel dialog | Nothing happens |

## 8. INC — incidents (the core drill lives here)

### 8a. Index / create / edit

| ID | Steps | Expected |
|---|---|---|
| INC-01 | Status filter dropdown (open/resolved/…) | Filters; auto-selected on change |
| INC-02 | Row title click → show; Edit button → edit | Correct navigation |
| INC-03 | Create: empty title/message → submit | Validation errors, input kept |
| INC-04 | Create with service + Critical impact + initial message | Show page opens; status/impact badges correct; message seeds timeline |
| INC-05 | Create with **no** service | Allowed (“Multiple services” on public) |
| INC-06 | Edit: change title/impact/status/service | Persists everywhere incl. public page |

### 8b. Show + timeline

| ID | Steps | Expected |
|---|---|---|
| INC-07 | Post update with each status (Investigating/Identified/Monitoring/Resolved) | Timeline grows newest-last, each with timestamp |
| INC-08 | Post empty message | Rejected with error |
| INC-09 | Resolving via timeline update | Incident status becomes resolved; public page + API reflect it |
| INC-10 | Edit button, Delete → Cancel, Delete → confirm | Cancel safe; confirm deletes incident **and** timeline with flash |

### 8c. FAILURE DRILL (functionality proof — do not skip)

| ID | Steps | Expected |
|---|---|---|
| INC-11 | SVC-BAD (unroutable URL, min-failures = 1): run Check now, reload show page | `current_status` = outage; history shows failed rows |
| INC-12 | After the flip: Incidents index + public home + `/api/status` | Auto-opened incident exists; public banner leaves green; API status matches |
| INC-13 | Mail catcher + webhook catcher + Deliveries page | Failure email arrived; webhook POST with valid HMAC received; delivery row `sent` with recipient count |
| INC-14 | Fix SVC-BAD URL to a healthy one, Check now | Status auto-flips Operational **without manual edit**; incident auto-resolves |
| INC-15 | Mail catcher after recovery | Exactly **one** recovery mail (resolution mail replaces duplicate service-recovered mail); Deliveries confirms |
| INC-16 | Set min-failures = 3 on SVC-BAD (broken URL), run 2 checks | Status stays previous (no flip, no incident, no mail); 3rd consecutive failure flips |

## 9. MNT — maintenance (`/admin/status/maintenances`)

| ID | Steps | Expected |
|---|---|---|
| MNT-01 | Create: end before start → submit | Rejected with validation error |
| MNT-02 | Create with no services selected → submit | Rejected (or explicit “global” behavior — record which) |
| MNT-03 | Schedule active window covering SVC-GOOD | Service shows “Scheduled Maintenance” publicly; no failure alerts fire during window; index row badge correct |
| MNT-04 | Public home during window | Blue maintenance card with title + times + description |
| MNT-05 | Edit: change times/services | Persists; public card updates |
| MNT-06 | Cancel (active/scheduled only) | Status → cancelled; button disappears afterwards |
| MNT-07 | Delete → Cancel vs confirm | Cancel safe; confirm removes + flash |
| MNT-08 | Window expiry (schedule 2-min window, wait) | Service returns to real status automatically on next check |

## 10. NTF — notifications

### 10a. Channels tab

| ID | Steps | Expected |
|---|---|---|
| NCH-01 | Tabs: Channels/Rules/Subscribers/Deliveries round-trip | Each active tab highlighted, no 404 |
| NCH-02 | Create mail channel, recipients “a@x.com, bad-address, b@x.com” | Invalid address rejected or stripped (record which); valid kept |
| NCH-03 | Create webhook channel with URL + secret | Saved; secret never displayed back |
| NCH-04 | Edit mail channel recipients | Persists |
| NCH-05 | Edit webhook channel, leave secret blank | **Old secret preserved** (send still signs correctly) |
| NCH-06 | Deactivate channel → trigger its event (INC-11 style) | No send, delivery row `skipped` (or no row — record which) |
| NCH-07 | Delete → Cancel vs confirm | Cancel safe; confirm removes + flash |

### 10b. Rules tab

| ID | Steps | Expected |
|---|---|---|
| NRL-01 | Create global rule: mail channel × service.failed | Fires for any service (prove with SVC-GOOD forced failure) |
| NRL-02 | Create service-scoped rule for SVC-GOOD only → fail SVC-BAD | **No** send for SVC-BAD; fail SVC-GOOD → sends |
| NRL-03 | Inactive rule + matching event | Nothing dispatched |
| NRL-04 | Delete → Cancel vs confirm | Cancel safe; confirm removes |

### 10c. Subscribers tab (pair with mail catcher)

| ID | Steps | Expected |
|---|---|---|
| NSUB-01 | Subscribe `t1@example.com` on public page → open verify link from mail catcher | “Subscription confirmed” flash; admin row shows verified |
| NSUB-02 | Trigger incident event | Verified subscriber receives mail **with working footer unsubscribe link** |
| NSUB-03 | Subscribe but do NOT verify → trigger event | No mail to that address (proves exclusion) |
| NSUB-04 | Toggle subscriber inactive → trigger event | No mail; toggle back → mail resumes |
| NSUB-05 | Delete → Cancel vs confirm | Cancel safe; confirm removes |
| NSUB-06 | Click footer unsubscribe in a received mail | Subscriber row deleted; “unsubscribed” flash; further events send nothing |

### 10d. Degraded drill (G5 proof)

| ID | Steps | Expected |
|---|---|---|
| NDEG-01 | Service with fail_ms low (e.g. 50 ms) against a slow URL → check | Status Degraded; `service.degraded` mail fires if a rule covers it |
| NDEG-02 | Same service recovers fast | Operational + recovery path, single mail |

### 10e. Deliveries tab

| ID | Steps | Expected |
|---|---|---|
| NDLV-01 | Event + outcome filters (incl. combined) | Correct subsets; pagination keeps filters |
| NDLV-02 | Outcome badges | sent green / failed red / skipped grey, counts match |
| NDLV-03 | Failed row (bad webhook URL drill) | Error text present, **secret nowhere** in error column |
| NDLV-04 | Recipient counts | Equal actual mails sent (spot-check vs mail catcher) |

## 11. SET — settings (`/admin/status/settings`)

| ID | Steps | Expected |
|---|---|---|
| SET-01 | Change app name → save | New name in admin header, public header, emails |
| SET-02 | Branding: upload logo + dark logo + favicon (drag-dropzone + file dialog) | Previews appear; public header + favicon update; email logo renders |
| SET-03 | Upload non-image as logo → save | Rejected with error; previous logo intact |
| SET-04 | Partial-safe: valid name + invalid `mail_port` (“abc”) → save | **Valid fields persist**, error shown only for the bad field |
| SET-05 | Mail group: fill SMTP → **Send test email** → check catcher | Arrives with branding + correct from-address |
| SET-06 | Webhook **Send test** to catcher URL | POST received with signature header |
| SET-07 | Alerts: master email switch OFF → trigger failure | Zero mails, deliveries reflect skip |
| SET-08 | Per-event flag OFF for service.recovered → run INC-14 recovery | No recovery mail (failure mail still sent) |
| SET-09 | Public toggles: `public_page_enabled` OFF → visit `/`, `/status`, service page | All 404; re-enable restores |
| SET-10 | `subscriptions_enabled` OFF → POST subscribe + visit verify link | 404 on subscribe (verify of existing token still 404-safe) |
| SET-11 | `badge_enabled` OFF → open badge URL | 404; re-enable restores SVG |
| SET-12 | Theme default + timezone change | Effective for fresh visitors; times render in zone |
| SET-13 | **Admin prefix change** (`admin` → `ops`) → save | Old URLs 404/redirect; **new `/ops/status` works after re-login**; change it back afterwards |
| SET-14 | Retention numbers saved | `status:cleanup` honors them (verify row counts or dry-run output) |

## 12. AUD — audit log (`/admin/status/audit-logs`)

| ID | Steps | Expected |
|---|---|---|
| AUD-01 | Perform: service create, incident update, settings save, subscriber delete → reload log | One entry each: actor email, action code, subject, IP, timestamp |
| AUD-02 | Filter by action substring | Narrows correctly; Reset clears |
| AUD-03 | Pagination | Works with filter retained |

## 13. Public pages + API

### 13a. Home (`/` and `/status`)

| ID | Steps | Expected |
|---|---|---|
| PHO-01 | `/` vs `/status` | Both 200, identical content, no redirect |
| PHO-02 | Banner through drill states (INC-11 → INC-14) | Green → red outage → green again, label + “last updated” correct |
| PHO-03 | Service rows | Name, “Checked X ago”, badge; SVC-PRIVATE absent everywhere |
| PHO-04 | Long badge labels (“Degraded Performance”) | Right column stays aligned, no wrap/overlap at 1440/768/375 |
| PHO-05 | 90-day bars: hover a bar | Tooltip date + exact % + success/total |
| PHO-06 | Subscribe: valid / invalid email / duplicate | Confirm-mail notice / validation error / “already subscribed” |
| PHO-07 | Polling: trigger a flip in another tab, watch home for ~70 s | Banner + badge update **without reload** |
| PHO-08 | Theme toggle + mobile 375px | Persists; bars scroll horizontally, page never overflows |

### 13b. Service / incident pages, verify, badge, refresh

| ID | Steps | Expected |
|---|---|---|
| PSV-01 | Open SVC-PRIVATE’s public URL directly | 404 (not a leak) |
| PSV-02 | Service page | Uptime %, avg ms, charts render (canvas, not blank), incidents list links work |
| PSV-03 | Service page with zero history (new service) | Friendly “no history yet” copy, no errors |
| PIN-01 | Incident page | Timeline in order, resolved stamp when resolved |
| PVU-01 | Reuse an already-consumed verify link | 404, no crash, no state change |
| PVU-02 | Garbage tokens for verify/unsubscribe | 404 pages |
| BDG-01 | `/status/badge.svg` | `image/svg+xml`, color matches current state (green/red/blue…), label text current |
| RFS-01 | `/status/refresh` JSON | Keys `status, status_label, updated_at, services[{slug,status,label}]`; values match page |
| RFS-02 | Hit refresh 61× rapidly | 61st throttled (429) — limit is 60/min (script via devtools-fetch allowed) |

### 13c. Public API

| ID | Steps | Expected |
|---|---|---|
| API-01 | `GET /api/status` | Overall status + groups/services/incidents JSON, 200 |
| API-02 | `GET /api/status/services` and `/services/{slug}` | List + detail; private service slug → 404/excluded |
| API-03 | `GET /api/status/incidents` | Incident list JSON |
| API-04 | Bad slug `/api/status/services/nope` | JSON 404, not HTML |
| API-05 | Burst past 60/min | Throttled with retry headers |

## 14. Cross-cutting (XCUT) — apply to every page above

| ID | Check |
|---|---|
| XCUT-01 | 375px: no page-level horizontal scroll; tables/cards scroll internally |
| XCUT-02 | Dark mode: every visited page readable, badges/dots visible, charts legible |
| XCUT-03 | Every delete/toggle/cancel confirm: **Cancel path leaves data untouched** |
| XCUT-04 | Every validation error: message next to field, input preserved, no 500 |
| XCUT-05 | Flash messages appear once, then disappear on next navigation |
| XCUT-06 | Audit log gained entries for the mutations just performed |
| XCUT-07 | No console errors on Test Request, charts, polling, theme toggle pages |

## 15. Results log

Copy per module; one row per ID.

| ID | Pass/Fail | Notes + screenshot ref |
|---|---|---|
| AUTH-01 | | |
| … | | |

**Bug report format:** ID(s) affected · URL · role/account · steps (numbered) ·
expected vs actual · screenshot/clip · severity (blocker/major/minor).

**Exit criteria:** all IDs Pass (or have accepted bug reports); `php artisan test`
green; `vendor/bin/pint --dirty` clean; fixes committed per module.
