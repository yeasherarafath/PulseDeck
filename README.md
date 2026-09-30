# PulseDeck — Service Monitoring & Public Status Page

> Your own GitHub-Status / Atlassian-Statuspage, self-hosted. It watches your websites
> and APIs around the clock and shows a clean public page: **“All Systems Operational”** —
> or exactly what’s broken, with history to prove it.
>
> Live demo: [https://status.newisty.com/](https://status.newisty.com/)
> · Full capability list: [`features.md`](features.md).

---

## Who is this guide for?

- **Non-technical reader** (founder, support lead, client): start at
  [What it does](#what-it-does-plain-language) and [Everyday use](#everyday-use-no-code-needed).
  Setup itself needs a developer once (~30 minutes); afterwards everything is point-and-click.
- **Technical reader** (developer, DevOps): jump to [Requirements](#requirements),
  [Setup](#setup-step-by-step), [Admin users](#admin-users--roles) and
  [Going live](#going-live-production-checklist).

---

## What it does (plain language)

- **A public status page** — one link you share with customers. Green when everything works,
  clear banners during incidents or planned maintenance, plus 90-day uptime history per service.
- **Automatic checking** — every few minutes the system visits your websites/APIs and verifies
  they respond correctly and fast. One failed check never causes panic: a service is only
  marked “down” after several consecutive failures, and it flips back to green by itself
  the moment it recovers.
- **Incidents** — repeated failures open an incident automatically; your team posts updates
  (“investigating → identified → monitoring → resolved”) and visitors see a live timeline.
- **Maintenance windows** — schedule “Database upgrade Sunday 2–3 AM” once; affected
  services show “Scheduled Maintenance” instead of false alarms.
- **Alerts** — email and webhook notifications when services fail, degrade, or recover,
  plus incident emails to subscribed visitors (double opt-in, one-click unsubscribe).
- **Dark & light mode**, your logo and favicon, on both the admin panel and public page.

**In one paragraph:** you add a service (“Main API”) with its URL and what “healthy” looks
like. A background worker checks it on schedule, records every result, and updates its
state (Operational → Degraded → Partial/Major Outage → back to Operational). The public
page, uptime percentages, charts, and alerts all flow from those results.

---

## How it compares

> Researched September 2026. SaaS products change fast — treat this as a
> snapshot, not a contract. Legend: ✓ yes · ~ partial / planned · ✗ no · — n/a.

| Capability | PulseDeck (this project) | Atlassian Statuspage | Better Stack | Uptime Kuma | Cachet |
|---|---|---|---|---|---|
| Price | Free, self-hosted | Free plan + paid up to $1,499/mo | Free plan + paid per responder | Free, self-hosted | Free, self-hosted |
| Built-in monitoring | ✓ | ✗ relies on external tools | ✓ | ✓ | ~ basic only |
| Monitor types beyond HTTP(S) | ✗ HTTP-only | — | ✓ TCP/Ping/DNS/SSL/… | ✓ 20+ types | ✗ |
| Push / heartbeat (cron-job) monitoring | ✗ | ✗ | ✓ | ✓ | ✗ |
| Fastest check interval | 1 min | — | 30 s | 20 s | Manual |
| Flexible schedules (interval + cron expressions) | ✓ both | — | ~ intervals only | ~ intervals only | ✗ |
| Rich assertions (JSON / headers / body / time) | ✓ | — | ✓ | ~ keyword / JSON-query | ✗ |
| Auto open + resolve incidents | ✓ | Via integrations | ✓ | ✗ | ✗ |
| Incident templates | ✗ | ✓ | ~ AI drafts | ✗ | ✗ |
| Public incident timeline | ✓ | ✓ | ✓ | ~ state only | ✓ |
| Maintenance windows | ✓ | ✓ | ✓ | ✓ | ✓ |
| Email subscriber notifications | ✓ double opt-in | ✓ | ✓ | ✗ admin alerts only | ✓ basic |
| SMS / voice alerts | ✗ | ✓ | ✓ unlimited | ~ via providers | ✗ |
| Slack / Discord / Telegram alerts | ~ planned | ✓ | ✓ | ✓ 90+ providers | ~ |
| Private / internal pages | ✗ | ✓ | ✓ | ✓ | ✗ |
| On-call schedules + escalations | ✗ | Via JSM / Opsgenie | ✓ built-in | ✗ | ✗ |

PulseDeck's pitch in one line: **Statuspage-style communication + real built-in
HTTP monitoring, free and self-hosted** — without the per-subscriber pricing or
the "monitoring not included" gap. See [`features.md`](features.md) for the full
capability catalog and [`todo.md`](todo.md) for what's planned next.

## Requirements

| Need | Minimum | Notes |
|---|---|---|
| PHP | 8.3+ | 8.4 recommended |
| Composer | 2.x | PHP dependency manager |
| Node.js | 20+ | Only needed to build frontend assets |
| Database | MySQL 8 / MariaDB 10+ **or** SQLite | MySQL recommended for production |
| Web server | Laragon / Nginx / Apache | Laragon is the easiest path on Windows |

---

## Setup, step by step

### 1. Get the code

```bash
git clone <your-repo-url> status-page
cd status-page
composer install
npm install
```

Or all-in-one (installs, generates key, migrates, builds assets):

```bash
composer run setup
```

### 2. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env`. **Option A — MySQL/MariaDB** (recommended, and pre-wired for Laragon):

```env
APP_URL=https://status.yourcompany.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=status-page
DB_USERNAME=root
DB_PASSWORD=
```

Create the empty database first (in Laragon: right-click → Quick create, or HeidiSQL).
**Option B — SQLite** (simplest for a first try): set `DB_CONNECTION=sqlite` and create
an empty `database/database.sqlite` file.

> Subdomain hosting: point e.g. `status.yourcompany.com` at the app, set `APP_URL`
> to match, and the public status page is served directly at `/` — no redirect.

### 3. Create tables + default data

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link   # required: exposes uploaded logos/favicons (see "Storage link" below)
```

#### Storage link (uploads)

Uploaded branding (logo, dark-mode logo, favicon, social image) is stored on the
`public` disk (`storage/app/public`) and served via `public/storage`. That
symlink does not exist on a fresh clone, so create it once per environment:

```bash
php artisan storage:link
```

Verify it worked: `public/storage` should now be a symlink to
`../storage/app/public`. If logos upload fine in Settings → Branding but never
render (404), re-run the command and confirm your web server follows symlinks.
On production deploys that rebuild the directory, re-create the link on every
deploy.

Seeding installs: roles & permissions, a local admin login (see below), header
presets/templates for the request builder, and every default in Settings.

### 4. Build the frontend

```bash
npm run build        # production assets (run again after any CSS/JS change)
# or: npm run dev    # live rebuild while developing
```

> Seeing *“Unable to locate file in Vite manifest”*? You skipped this step — run
> `npm run build`.

### 5. Run it (three moving parts)

| Part | Command | Purpose |
|---|---|---|
| Web app | `composer run dev` **(easiest: runs all three)** | Serves the site |
| — or individually | `php artisan serve` | Site at `http://localhost:8000` |
| Queue worker | `php artisan queue:work` | Actually performs the checks |
| Scheduler | `php artisan schedule:run` every minute (cron, see below) | Dispatches due checks, nightly stats + cleanup |

Without the **queue worker**, no checks ever run (the Monitoring page heartbeat will
show “Stale”). Without the **scheduler**, nothing is dispatched on time.

**Scheduler cron (Linux production):**

```cron
* * * * * cd /path/to/status-page && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker (production):** keep `php artisan queue:work --tries=3` alive with
Supervisor/systemd. On Laragon/Windows dev, one terminal with `composer run dev`
covers everything.

### 6. Log in

- **Local dev:** email `admin@example.com`, password `password`
  (created by the seeder, local environments only — change it immediately:
  log in → top-right menu → update profile/password).
- **Production:** the seeder never creates users there — add your first admin
  (see [Admin users](#admin-users--roles)).

Admin panel lives at `/admin/status` (prefix changeable in Settings → General).

---

## Admin users & roles

There is intentionally **no click-to-create-admin UI** (fewer attack paths). Manage
admins with the seeder + one-liners:

```bash
# Create (or find) a user and make them super-admin
php artisan tinker --execute '$u = App\Models\User::firstOrCreate(["email" => "you@company.com"], ["name" => "Your Name", "password" => Hash::make("Choose-A-Strong-Password")]); $u->assignRole("super-admin");'

# Give an existing user a different role
php artisan tinker --execute 'App\Models\User::where("email", "teammate@company.com")->first()->syncRoles(["status-manager"]);'
```

| Role | Can do |
|---|---|
| `super-admin` | Everything, including Settings, audit log, admins, and roles |
| `status-manager` | Everything **except** Settings, admins, and roles |
| `status-viewer` | View services, monitoring, incidents, maintenance (read-only) |

Under the hood these map to 19 granular `status.*` permissions (Spatie), enforced
on every admin route. Manage admins and roles at Admin → Admins / Roles; every
admin can update their own name, email, and password at Profile.

---

## Everyday use (no code needed)

1. **Add your first service** — Admin → Services → New service. Fill in the name and
   URL, keep the defaults, save, then press **Check now**. Green badge = you’re monitored.
2. **Make the check meaningful** — Edit the service → Assertions: expected status codes
   (e.g. `200`), max response time, text the page must contain. Use **Test Request**
   to preview the result before saving.
3. **Set up alerts** — Settings → Mail: enter SMTP credentials → **Send test email**.
   Then Notifications → Channels/Rules: decide who gets emailed (or webhook-called)
   for failures, recoveries, and incidents. Visitors can also self-subscribe on the
   public page.
4. **Brand it** — Settings → Branding: upload logo (+ dark variant) and favicon,
   set the app name. It appears on the public page, admin panel, and emails.
5. **Schedule maintenance** — Maintenance → Schedule: affected services automatically
   show “Scheduled Maintenance” during the window instead of alerting.
6. **Handle an incident** — open ones appear on the Dashboard; post updates as you
   investigate; resolving notifies subscribers automatically.
7. **Watch the engine** — Monitoring shows queue depth, failed jobs, and heartbeat.
   Heartbeat “Stale” = your queue worker/scheduler isn’t running (see step 5).

**Public links to share:** `/` (status home) · `/api/status` (JSON API) ·
`/status/badge.svg` (embeddable badge) · per-service pages linked from the home page.

8. **Use the API** — the admin sidebar has an **API docs** page (Configure → API docs) with every
   public endpoint, request samples in cURL / JavaScript / PHP / Python, example responses, error codes,
   rate limits, and a **Try it** button that calls your live server.

---

## Going live (production checklist)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` = real https URL, then
      `php artisan config:cache && php artisan route:cache && npm run build`.
- [ ] HTTPS enabled (required — auth cookies are secure). Generated URLs are forced to
      https only when `APP_URL` starts with `https://`.
- [ ] MySQL/MariaDB (not SQLite), with backups.
- [ ] Cron runs `schedule:run` every minute; queue worker supervised and restarted
      on deploy (`php artisan queue:restart`).
- [ ] First admin created via tinker (above); local `admin@example.com` does not exist
      in production by design.
- [ ] Settings → Mail filled + test email received; alert rules reviewed.
- [ ] Settings → General: app name, timezone, public-page toggles; Branding uploaded.
- [ ] `storage:link` in place so logos resolve.
- [ ] Public page cached 15–30 s and auto-invalidated on state flips — no action needed.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Heartbeat “Stale”, checks never run | Start `php artisan queue:work` and the every-minute scheduler |
| “Vite manifest” error | Run `npm run build` |
| Login page loops / 419 | `APP_URL` must match the URL you visit; clear cookies |
| Logos don’t show | Run `php artisan storage:link` |
| No emails arrive | Settings → Mail: verify SMTP, use Test email; check Mailpit (`localhost:1025`) in dev; check Deliveries page for `failed` rows (errors logged without secrets) |
| Webhook rejected as private/reserved address | Webhook targets pass the SSRF guard; set `STATUS_WEBHOOK_ALLOW_PRIVATE=true` in `.env` (see `.env.example`) only if you must reach internal hosts |
| A service flaps up/down | Raise its “min failures before down” (per-service or global) |
| Public page looks outdated | Cached up to 30 s; any change to a service, group, incident, maintenance window or setting clears it immediately, so only "checked x ago" can lag |
| A public service is missing from the page | Services without a group are listed under **Other services**; services set to *not public* are never shown |
| Maintenance window starts at the wrong hour | Start/end are entered in the timezone from Settings → General (shown under the field), not in UTC or your browser's timezone |

---

## For developers (technical)

**Stack:** Laravel 13 · PHP 8.4 · MySQL/MariaDB · database queue · Tabler v1 (npm +
Vite) + Tom Select + Monaco + ApexCharts · Spatie Permission · PHPUnit 12 · Pint.

**Architecture:** slim controllers (Validate → Authorize → Service → View);
`StatusRequestDefinition` DTO shared by scheduled checks and Test Request;
`app/Services/Status/*` (`RequestBuilder`, `HttpChecker`, `AssertionEngine`,
`StatusCalculator`, `IncidentManager`, `SsrfGuard`, `PublicStatusService`, …);
`app/Jobs/Status/CheckService` on the `database` queue; events
(`ServiceCheckCompleted`, `ServiceWentDown`, `ServiceBecameDegraded`,
`ServiceRecovered`, `IncidentCreated/Resolved`, …) decoupling notifications.
Typed PHP enums for every status. Secrets stored `encrypted:array`, redacted in
logs/audit/test output. Global helpers in `app/helper/helper.php`
(`setting()`, `admin_base_path()`, `branding_asset()`, …).

**Key routes:**

```text
/                                     public home (subdomain-ready)
/status · /status/services/{slug} · /status/incidents/{incident}
/status/{subscribe,verify/{token},badge.svg,refresh}
/status/unsubscribe/{token}          GET = confirmation page, POST = unsubscribe (scanners cannot unsubscribe anyone)
/api/status · /api/status/services · /api/status/services/{slug} · /api/status/incidents
/admin/status/{dashboard,monitoring,services,groups,incidents,maintenances,notifications/{channels,rules,subscribers,deliveries},settings,audit-logs,api-docs}
```

**Commands:** `status:dispatch-due` (every minute) · `status:calculate-daily` (00:10) ·
`status:cleanup` (01:00) · `status:recalculate` · `status:test --service=ID`.

```bash
php artisan test            # full suite (76 tests, must stay green)
vendor/bin/pint --dirty     # code style — run before finalizing PHP changes
```

**Docs:** [`features.md`](features.md) capability catalog ·
[`final-plan.md`](plans/final-plan.md) build spec · [`plans/browser/full-test-plan4.md`](plans/browser/full-test-plan4.md) page-by-page browser test plan with results · [`notification-plan.md`](plans/notification-plan.md)
notification deep-dive · [`plan.md`](plans/plan.md) original brainstorm.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for setup, workflow, code style, and tests.

## License

Built on the [Laravel framework](https://laravel.com) (MIT). Application code follows
the repo’s own license once added.
