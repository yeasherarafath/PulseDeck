# PulseDeck — Feature Catalog

> The complete list of what PulseDeck does today. For setup instructions see
> [`README.md`](README.md). For the original build spec see [`final-plan.md`](plans/final-plan.md);
> for the notification deep-dive see [`notification-plan.md`](plans/notification-plan.md).

## 1. Public status page (your visitors see this)

| Feature | Details |
|---|---|
| Home at `/` | Served directly, no redirect — drop it on a subdomain (e.g. `status.yourcompany.com`) and it just works. `/status` URLs keep working too. |
| Overall banner | One glance state: All Operational, Degraded, Partial/Major Outage, or Maintenance, with “last updated” time. |
| Service groups | Services organized under group headings (Websites, APIs, …) with per-service status badges and “checked X ago”. |
| Active incidents | Red-highlighted card listing open incidents with timeline links. |
| Scheduled maintenance | Blue card with upcoming windows so visitors know downtime is planned. |
| 90-day uptime history | Per-service daily bars with exact % tooltips (scrollable on phones). |
| Service detail pages | Current status, 90-day uptime %, average response time, uptime chart, response-time chart, recent incidents. |
| Incident pages | Full public timeline: investigating → identified → monitoring → resolved. |
| Email subscriptions | Visitors subscribe, confirm via verification link, and get incident emails with a one-click unsubscribe footer. |
| Status badge | Embeddable SVG (`/status/badge.svg`) for dashboards/GitHub READMEs. |
| Public JSON API | `/api/status`, `/api/status/services`, `/api/status/services/{slug}`, `/api/status/incidents` (rate-limited). |
| Live refresh | Page polls for updates every ~60 s without reloads. |
| Dark / light mode | One-click toggle, remembers the visitor’s choice (default: light). |
| Branding | Your logo (light + dark variants), favicon, and app name everywhere including notification emails. |

## 2. Monitoring engine (the part that watches your sites)

- **Scheduled checks** — every minute, due services are dispatched as queue jobs (`status:dispatch-due`).
- **Full HTTP checks** — GET/POST/PUT/PATCH/DELETE/HEAD with custom headers, auth (None, Bearer, Basic, API Key, fully Custom), and bodies (JSON, Form, Raw).
- **Assertions** — a healthy response means: expected status codes, max response time, body contains/not-contains, JSON field equals, header present/equals.
- **Smart statuses** — each result maps to Operational, Degraded (slow but answering), or Failed; failures only flip a service to “down” after N consecutive failures (per-service setting, else global default) so one blip never pages anyone.
- **Auto recovery** — first good check after an outage flips the service back to Operational automatically and fires a single recovery email.
- **Maintenance-aware** — services under a maintenance window report “Scheduled Maintenance”, not false alarms, and uptime stats stay fair.
- **Retried safely** — jobs retry with backoff; concurrent runs are lock-guarded; invalid configs produce a visible failed check instead of crashing the worker.
- **SSRF protection** — private/internal URLs are blocked (including sneaky redirects), connections are pinned to the validated IP (no DNS rebinding), schemes allow-listed, response sizes capped. Webhook targets use the same guard.
- **Engine health** — the Monitoring page shows queued jobs, failed jobs, and a worker heartbeat (“Running” vs “Stale”).

## 3. Services & request builder (admin)

- Service CRUD with groups, check interval, timeouts, public/private visibility, sort order, pause/resume.
- Tabbed request builder: General / Request / Auth / Assertions / Advanced.
- Header presets & reusable templates; per-service expected status codes.
- Per-service alert switches: notify on failure / notify on recovery.
- **Test Request modal** — try any configuration instantly and see the redacted result before saving.
- **Check Now** — queue an immediate check for one service.
- Full check history per service with timing, HTTP status, errors, and failed-assertion details.

## 4. Incidents

- **Automatic** — N consecutive failures open an incident; M consecutive recoveries resolve it (configurable).
- **Manual** — create/edit incidents any time, link to a service or mark multi-service.
- **Updates timeline** — post timestamped updates with per-update status (Investigating, Identified, Monitoring, Resolved).
- **Impact levels** — None / Minor / Major / Critical, shown as badges everywhere.
- Public timeline pages + incident emails to subscribers on create/update/resolve.

## 5. Maintenance windows

- Schedule title, description, start/end, affected services.
- Affected services show “Scheduled Maintenance” on the public page during the window.
- Cancel or delete windows; past windows stay in history.

## 6. Notifications

- **Channels** — Email (SMTP, configured in Settings) and Webhook (JSON payload + HMAC signature), each with an on/off switch.
- **Rules** — route events to channels, globally or scoped to one service.
- **Events** — service failed, service degraded, service recovered, incident created/updated/resolved, maintenance started/ended.
- **Global + per-event + per-service switches** — master toggles in Settings, per-event flags, and per-service failure/recovery flags.
- **Verified subscribers** — double opt-in; only verified addresses ever receive mail; unverified ones are excluded automatically.
- **Per-recipient unsubscribe** — every email carries its own unsubscribe link (opens a confirmation page, so mail scanners cannot unsubscribe anyone). Private services never email public subscribers.
- **Delivery log** — every send is recorded (sent / failed / skipped, recipient count, error without secrets) on the Deliveries page.
- **One recovery, one email** — an auto-resolved incident sends its resolution mail instead of a duplicate service-recovered mail.
- **Test buttons** — “Send test email” and “Send test webhook” right in Settings.

## 7. Admin panel

- **Dashboard** — service counts by state, engine heartbeat, active incidents, problem services, recent checks.
- **Monitoring** — queue depth, failed jobs, heartbeat, per-service last/next check, response times, last errors.
- **Services, Groups, Incidents, Maintenance** — full CRUD with search/filter.
- **Notifications** — Channels, Rules, Subscribers, Deliveries tabs.
- **Settings** — 8 groups (see below), logo/favicon drag-and-drop upload with preview, partial-safe saving (one bad SMTP port never wipes the rest).
- **Audit log** — who changed what, when, from which IP.
- **Roles & permissions** — `super-admin` (everything), `status-manager` (everything except settings), `status-viewer` (read-only). 18 granular `status.*` permissions via Spatie.
- Dark / light mode with sticky top bar.

## 8. Settings reference (`status_settings`)

| Group | Covers |
|---|---|
| General | App name, tagline, public URL, timezone, theme default, admin URL prefix |
| Branding | Logo, dark-mode logo, favicon, footer text |
| Monitoring | Check defaults, failure threshold before “down”, incident open/resolve thresholds |
| Public | Page toggles (page, API, subscriptions, badge), refresh interval, history window |
| Mail | Enable switch, SMTP host/port/user/pass/encryption/from-address, test email |
| Alerts | Email/webhook master switches, per-event notify flags |
| Webhook | Default timeout, retry behavior |
| Retention | How long raw checks, daily stats, audit logs, and delivery records are kept (auto-cleaned nightly) |

## 9. Artisan commands

| Command | Purpose |
|---|---|
| `status:dispatch-due` | Queue checks for due services (runs every minute via scheduler) |
| `status:calculate-daily` | Nightly uptime aggregation |
| `status:cleanup` | Nightly purge per retention settings |
| `status:recalculate` | Recompute statuses from latest checks |
| `status:test --service=ID` | One-off check with redacted output for debugging |

## 10. Planned (V3, structure-ready, not built yet)

Telegram / Discord / Slack channels · SLA reports · TCP / DNS / Ping / SSL-expiry monitors
behind a `MonitorChecker` interface · multi-page / multi-tenant support.
