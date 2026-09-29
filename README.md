# Status Page — Service Monitoring & Public Status

> A self-hosted status monitoring system (think GitHub Status / Atlassian Statuspage, but yours).
> It watches your websites and APIs around the clock, and shows a clean public page saying
> **“All Systems Operational”** — or exactly what’s broken, with history to prove it.

---

## For everyone (non-technical)

**What does it do?**

- **Public status page** — one link you share with customers: green when everything works,
  clear banners when there’s an incident or planned maintenance, plus 90-day uptime history.
- **Automatic checking** — every few minutes the system visits your websites/APIs and verifies
  they respond correctly and fast.
- **Incidents** — when something fails repeatedly, an incident is opened automatically;
  your team posts updates (“investigating → identified → monitoring → resolved”) and the
  public page shows the timeline.
- **Maintenance windows** — schedule “Database upgrade Sunday 2–3 AM” once; affected services
  show “Scheduled Maintenance” instead of false alarms, and uptime stats stay fair.
- **Alerts** — get notified by email or webhook when services go down or recover
  (each event can be switched on/off in Settings).
- **Dark & light mode** — both the admin panel and the public page support dark and light
  themes (default: light), with a one-click toggle.

**How it works, in one paragraph:** you add a service (e.g. “Main API”) with its URL and what
a “healthy” response looks like. A background worker checks it on schedule, records every
result, and updates the service state (Operational → Degraded → Partial/Major Outage).
The public page, uptime percentages, charts, and alerts all flow from those check results —
cached for speed, so visitors never slow down monitoring.

---

## Features (V1 + V2 scope)

| Area | What’s included |
|---|---|
| Public page | Overall banner, grouped services, active incidents, maintenance notices, 90-day uptime bars, service detail pages with response-time charts, incident timelines, status badge (`/status/badge.svg`), public API |
| Admin panel (Tabler) | Dashboard, service CRUD with tabbed request builder (General / Request / Auth / Assertions / Advanced), header suggestions + custom headers + templates, auth (None/Bearer/Basic/API-Key/Custom), request bodies (JSON/Form/Raw), Test Request modal, Check Now, check history, monitoring overview, maintenance scheduler, notification rules, settings |
| Monitoring engine | Scheduler → queue jobs → HTTP checker → assertion engine (status codes, response-time thresholds, body/JSON/header assertions) → status calculator → incident detection (N consecutive failures open, M recoveries close) |
| Safety | SSRF protection (incl. redirects), scheme allowlist, response-size caps, timeouts, encrypted secrets, redacted logs/audit, rate-limited test/check/API endpoints |
| Settings (`status_settings`) | App name/tagline/URL, branding (logo incl. dark variant, favicon, footer), timezone, monitoring defaults, public-page/API/subscription/badge toggles, full SMTP credentials + test email, email/webhook master switches + per-event flags, retention periods, theme default |

Full specification: [`final-plan.md`](final-plan.md) · Original brainstorm: [`plan.md`](plan.md)

---

## For developers (technical)

**Stack:** Laravel 13 · PHP 8.4 · MariaDB/MySQL · Database queue · Tabler v1 (npm + Vite) +
Tom Select + Monaco + ApexCharts · Spatie Permission · Laravel Boost (dev) · Pest/PHPUnit · Pint

**Architecture:** slim controllers (Validate → Authorize → Service → View/Resource),
`StatusRequestDefinition` DTO shared by scheduled checks and Test Request,
`app/Services/Status/*` (RequestBuilder, HttpChecker, AssertionEngine, StatusCalculator,
IncidentManager, SsrfGuard, …), `app/Jobs/Status/CheckService` on the `database` queue,
events (`ServiceCheckCompleted`, `IncidentCreated/Resolved`, …) decoupling notifications.
Typed PHP enums for all statuses (`ServiceStatus`, `IncidentStatus`, `MaintenanceStatus`, …).
Public status cached 15–30s and invalidated on state change.

**Routes:**

```text
/status · /status/{service} · /status/incidents/{incident}
/admin/status/{dashboard,services,incidents,maintenances,monitoring,settings}
/api/status · /api/status/services · /api/status/services/{service} · /api/status/incidents
```

---

## Quick start (Laragon, Windows)

Database `status-page` already exists (user `root`, empty password). `.env` is pre-wired:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=status-page
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 1. Install dependencies (if fresh clone)
composer install
npm install

# 2. Tables (status-* tables come with Phase 1 migrations)
php artisan migrate

# 3. Frontend
npm run dev        # dev, or: npm run build

# 4. Run the app + background checking (two terminals)
php artisan serve                      # http://localhost:8000
php artisan queue:work --queue=default # processes CheckService jobs
# Scheduler (every minute dispatches due checks):
php artisan schedule:run               # or a cron calling it each minute
```

**Next build steps** follow [`final-plan.md`](final-plan.md) §8 checklist:
migrations → models/enums → auth/permissions → groups → service CRUD → request UI →
RequestBuilder → SsrfGuard → HttpChecker → CheckService → scheduler/queue → public pages →
incidents/maintenance → notifications/API → settings → tests → perf/security review.

## Testing

```bash
php artisan test            # full suite (Pest/PHPUnit)
vendor/bin/pint --dirty     # code style (run before finalizing PHP changes)
```

Must-pass behaviors are listed in `final-plan.md` §5 (status outcomes, incident thresholds,
SSRF blocks, secret redaction).

## Roadmap

- **Now (V1+V2):** everything in the table above.
- **Later (V3, structure-ready):** Telegram/Discord/Slack channels, SLA reports, TCP/DNS/Ping/
  SSL-expiry monitors behind a `MonitorChecker` interface, multi-page / multi-tenant support.

## License

Built on the [Laravel framework](https://laravel.com) (MIT). Application code follows the
repo’s own license once added.
