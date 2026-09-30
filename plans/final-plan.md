# Final Plan — Status Monitoring System (Laravel Latest + Tabler Latest)

> Source: `plan.md` (#1–#128)
> Decisions locked: Fresh Laravel install (greenfield) · Core auth only — no Fortify/Breeze/Jetstream · Spatie Permission (authorization) · Database queue (Laragon-friendly, Redis upgrade path) · Scope **V1+V2 combined** (plan #122 + #123) · PHP Enums everywhere · Laravel best practices · Tabler v1.x premium via npm (not basic CDN) · Laravel Boost installed · Theme dark+light (default light) on admin + public · Expanded `status_settings` (app identity, mail credentials, alert toggles)

---

## 0. Objective & Design Locks (plan #128 — non-negotiable)

Build a serious Laravel + Tabler status monitoring system: public status page + admin panel + monitoring engine + incidents + maintenance + notifications + public API, with advanced HTTP/API request builder.

Locks:

1. All monitoring goes through one `CheckService` pipeline (scheduled, `Check Now`, `Test Request` share the same `RequestBuilder`).
2. Headers = presets + custom headers. Auth is a separate section from raw headers.
3. Secrets are `encrypted:array` casts, never logged, UI shows `••••••••`.
4. Assertions configurable per service (status, time, body, JSON, headers).
5. Raw response bodies NOT stored by default (only status, timings, assertion results, safe diagnostics).
6. Uptime = `successful / total_valid * 100`, maintenance checks excluded.
7. Incidents require consecutive failures (default 3 fail / 2 recover), configurable per service.
8. Scheduled checks run through queue (`status:dispatch-due` every minute via `next_check_at`).
9. Public status cached (15–30s), invalidated on change.
10. SSRF protection mandatory, including every redirect target.
11. Raw checks short retention (30–90d); daily stats long retention (1y+); incidents/maintenance kept.
12. Monitoring engine separated from controllers and Blade. Controllers only: Validate → Authorize → Call Service → Return view/JSON.
13. Admin + public both support dark and light mode (default light) via Tabler `data-bs-theme`; charts, Monaco, custom CSS must be theme-aware.
14. All app identity, mail credentials, and alert enable/disable flags live in `status_settings` (key-value + encrypted secrets), editable from admin settings UI — no hardcoded `.env`-only toggles for business settings.

**Stack:**

```text
Laravel 13.x · PHP 8.3+ · Vite · Laravel HTTP Client · Scheduler · Queue (database) ·
Events/Listeners · Spatie Permission · Laravel Boost (dev) · Pint + Pest/PHPUnit ·
Tabler v1.x via npm + Tom Select + Monaco Editor + ApexCharts
```

Tabler usage: premium components, not basic CSS — cards, datatables, badges + status-dot, timeline, modals/offcanvas, empty-states, toasts, ribbons, tabs/steps, charts. No CDN-only basic theme. Theme via `data-bs-theme="light|dark"` on `<html>`, toggle in both navbars, persisted per-browser + global default from settings.

---

## 1. Architecture (plan #1, #26, #76, #107–108, #126)

```text
                     STATUS MONITORING SYSTEM
                              │
           ┌──────────────────┼──────────────────┐
           │                  │                  │
           ▼                  ▼                  ▼
      Public Page         Admin Panel       Monitoring Engine
           │                  │                  │
           │                  │                  ├── Scheduler
           │                  │                  ├── Queue (DB)
           │                  │                  ├── HTTP Checker
           │                  │                  ├── Assertions
           │                  │                  └── Incident Detection
           │                  │
           └──────────────────┼──────────────────┘
                              │
                              ▼
                          Database
```

Routes:

```text
/status · /status/{service:slug} · /status/incidents/{incident:slug}
/admin/status · /admin/status/services · /admin/status/incidents
/admin/status/maintenances · /admin/status/monitoring · /admin/status/settings
/api/status · /api/status/services · /api/status/services/{slug} · /api/status/incidents
/status/badge.svg · /status/widget
```

Layering:

```text
ServiceController → StatusServiceManager → Model
CheckService Job → RequestBuilder → HttpChecker → AssertionEngine → StatusCalculator → Check History → IncidentManager → Notifications
Test Request → same RequestBuilder + HttpChecker (identical behavior)
```

Project skeleton:

```text
app/
├── Console/Commands/Status/{DispatchDueChecks,CalculateDailyStats,Cleanup}.php
├── Enums/Status/*.php
├── Events/Status/*
├── Jobs/Status/{CheckService,CalculateDailyStats,ProcessIncident,SendStatusNotification,CleanupOldChecks}.php
├── Models/Status/{StatusServiceGroup,StatusService,StatusCheck,StatusDailyStat,StatusIncident,StatusIncidentUpdate,StatusMaintenance,StatusMaintenanceService,StatusHeaderPreset,StatusHeaderTemplate,StatusNotificationChannel,StatusNotificationRule,StatusSubscriber,StatusAuditLog,StatusSetting}.php
├── Services/Status/{RequestBuilder,HttpChecker,AssertionEngine,JsonAssertionEngine,ResponseAnalyzer,StatusCalculator,IncidentManager,MaintenanceManager,UptimeCalculator,NotificationManager,SsrfGuard,HeaderPresetManager}.php
└── Http/{Controllers/Status,Controllers/Admin/Status,Requests/Status}/*
resources/views/{layouts/admin-tabler,layouts/public-status,status/*,admin/status/**/*}
resources/views/components/x-status-*
```

Reusable Blade components (plan #78): `x-status-badge, x-status-service-card, x-status-uptime-bars, x-status-header-row, x-status-assertion-row, x-status-incident-timeline, x-status-maintenance-banner, x-status-response-summary`.

---

## 2. Enums — `app/Enums/Status/` (string-backed)

Use enums everywhere; never raw strings in logic. Each enum exposes `label()`, `color()`, `badgeClass()` for Tabler.

```text
ServiceStatus: operational|degraded|partial_outage|major_outage|maintenance|unknown
  → Operational, Degraded Performance, Partial Outage, Major Outage, Scheduled Maintenance, Unknown (#3)
CheckResultStatus: operational|degraded|failed|maintenance|unknown
CheckErrorType: dns|timeout|connection|tls|http_4xx|http_5xx|assertion|redirect|ssrf_blocked|oversized|unknown (#84)
HttpMethod: GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS (#7)
AuthType: none|bearer|basic|api_key|custom (#15)
RequestBodyType: none|json|form|urlencoded|raw (#16)
AssertionOperator: equals|not_equals|contains|not_contains|exists|not_exists|greater_than|less_than|matches (#21)
BodyAssertionType: contains|not_contains|equals|regex (#20)
IncidentStatus: investigating|identified|monitoring|resolved (#36)
IncidentImpact: minor|major|critical (#36)
MaintenanceStatus: scheduled|active|completed|cancelled (#40)
NotificationChannelType: mail|webhook|telegram|discord|slack (#51)
NotificationEvent: service.failed|service.recovered|incident.created|incident.updated|incident.resolved|maintenance.started|maintenance.ended (#52)
CheckInterval: 60|300|600|900|1800|3600 (labels 1m/5m/10m/15m/30m/1h) (#7)
HttpVersion: auto|1_0|1_1|2_0
HeaderPresetCategory: common|api|other|custom (#10)
ThemeMode: light|dark (default light; used for settings default + per-browser override)
```

Rules:

- DB columns `string`, models cast `'status' => ServiceStatus::class`, etc.
- `CheckInterval` drives `next_check_at` + `status:dispatch-due`; no magic ints.
- `AuthType / RequestBodyType / AssertionOperator` drive `RequestBuilder`, validation, Tom Select options, and `Test Request` renderer.
- Status calculation order (plan #83): `1. Maintenance? → Maintenance · 2. Transport fail? → Failed · 3. HTTP status invalid? → Failed · 4. Assertions failed? → Failed · 5. Slow? → Degraded · 6. Else Operational`.
- Overall public status (plan #92): `major_outage > partial_outage > degraded > maintenance-only > operational`.

---

## 3. Best Practices (Laravel latest)

- **Auth (locked): no Fortify/Breeze/Jetstream — Laravel core auth only.** Session guard + `Auth::attempt()` + password broker (`Password::sendResetLink` / `Password::reset`) + `throttle` middleware for login. No auth starter-kit packages, so Tabler views stay hand-owned and the dependency tree stays minimal. Spatie Permission remains the only auth-related package (authorization, not authentication).
- **Slim controllers:** Validate (`FormRequest`) → Authorize (Spatie `status.*` + Policies, plan #68) → Call Service → Return view/Resource. No monitoring logic in controllers/Blade.
- **DTO:** `StatusRequestDefinition {method,url,query,headers,auth,body,timeout,redirects,ssl,assertions}` — `RequestBuilder::fromService(): DTO` consumed by `HttpChecker` (plan #82).
- **Models:** `$fillable` + casts (`encrypted:array` for `request_headers, authentication, channel.config`; enum casts; `datetime`), slug auto-gen + unique (#114), indexes (#113), observers bust `status:public` cache.
- **Requests/Policies/Resources:** `Store/UpdateStatusServiceRequest` (`url|required|url|starts_with:http,https` + `SsrfGuard` rule, `timeout 1–60`, valid JSON body + assertion syntax, plan #81); `StatusServicePolicy`; API Resources with `{ok:true,data:{}}` envelope (#115).
- **Jobs/Events:** `CheckService (ShouldQueue, tries 2–3, backoff 10–30s, cache lock status-check:{id} #88)` → `ServiceCheckCompleted` → `IncidentManager` + `NotificationManager` listeners. No notification code inside checker (#51). Heartbeat `status:monitor:heartbeat`, stale → `Unknown` (#89–90). First request = official result, no auto HTTP retry inflating uptime (#102).
- **Security:** `SsrfGuard` (scheme http/https only #62, DNS-resolve + block loopback/private/link-local/metadata `169.254.169.254`/IPv6 + every redirect #60–61, 1MB response cap + header caps #63, timeouts #64, UA `StatusMonitor/1.0` #65), rate-limit test/check/api/subscribe/admin (#66), redact secrets before logs/audit/history (#100, #67, #49), optional `STATUS_MONITORING_ALLOW_EXTERNAL_URLS` + Allowed Hostnames allowlist (#104–105).
- **Frontend:** Tabler via npm + Vite, Tom Select (headers), Monaco (JSON body), ApexCharts (response/uptime). Secrets never prefilled.
- **Data hygiene:** maintenance excluded from uptime denominator (#95); `status:cleanup` enforces retention (#35).
- **Quality:** Pint + Larastan, factories + seeders, `Http::fake()` in tests.

---

## 3.1 Theme — Dark + Light (default light, admin + public)

- **Mechanism (Tabler latest):** use native `data-bs-theme` support. `<html data-bs-theme="{{ $theme }}">` where `$theme` resolved as: `localStorage(status-theme) ?? auth()->user()->theme ?? StatusSetting::get('theme_default','light')`. Default `light` everywhere (layouts, emails, badge SVG stays light-safe).
- **Toggle UI (both layouts):** sun/moon icon button in admin topbar + public header (`x-theme-toggle` Blade component). Click → swap `light↔dark`, persist `localStorage`, optionally `POST /admin/status/settings/theme` or `POST /status/theme` to persist per-user; no full reload. Respect `prefers-color-scheme` only to pre-select when no stored choice AND setting `theme_allow_system=true` (default false — keep deterministic light default).
- **Implementation details:**
  1. `ThemeMode` enum + `status_settings.theme_default=light`, `theme_allow_user_toggle=true`.
  2. Central `theme.js` (Vite): `getTheme()/setTheme()/initTheme()` called before paint (inline head snippet to avoid FOUC), dispatches `theme:changed` event.
  3. ApexCharts: wrap chart options builder to read `document.documentElement.dataset.bsTheme` → grid/text/tooltip colors; re-render on `theme:changed`.
  4. Monaco (JSON body): `vs.editor.setTheme(stored === 'dark' ? 'vs-dark' : 'vs')` on toggle.
  5. Tom Select + custom CSS: only CSS vars (`var(--tblr-*)`), never hardcoded `#fff/#000`; verify uptime bars, timeline, banners in both modes.
  6. Badge SVG (`/status/badge.svg`) stays static light-readable; widget iframe inherits parent theme via query `?theme=dark`.
  7. SEO/meta: `<meta name="color-scheme" content="light dark">`, `theme-color` updated by JS.
- **Done:** toggle works on admin + public without reload, persists across visits, default light on fresh browser, charts/editor follow theme.

## 3.2 Settings System — expanded `status_settings`

- **Storage:** key-value table, NOT dozens of nullable columns (easier to extend for V3):
  `status_settings {id, key unique, value (text), type enum: string|int|bool|json, group: general|branding|monitoring|public|mail|alerts|retention, is_encrypted bool, description, updated_by, timestamps}`.
  Model `StatusSetting::get(key, default) / ::set(key, value)` with in-request + 60s cache; encrypted cast applied when `is_encrypted=true` (mail password, webhook secrets). Seeder inserts all defaults below. Audit every change (no secrets in audit).
- **Groups + keys (defaults):**
  - `general`: `app_name="Status"` · `app_tagline="Service status & uptime"` · `app_description` (SEO/meta) · `base_url` (canonical, validated `url`) · `contact_email` · `timezone="UTC"` · `date_format="M j, Y H:i"` · `theme_default="light"` · `theme_allow_user_toggle=true`.
  - `branding`: `logo_path nullable` · `logo_dark_path nullable` (used when dark) · `favicon_path nullable` · `footer_text nullable`.
  - `monitoring` (defaults for new services): `default_check_interval=300` · `default_timeout=15` · `default_connect_timeout=5` · `failure_threshold=3` · `recovery_threshold=2` · `stale_after_multiplier=3` (× interval → Unknown).
  - `public`: `public_page_enabled=true` · `public_api_enabled=true` · `subscriptions_enabled=true` · `badge_enabled=true` · `public_refresh_seconds=45` · `uptime_window_days=90`.
  - `mail` (credentials, all editable + test button): `mail_enabled=false` · `mail_mailer=smtp` · `mail_host nullable` · `mail_port=587` · `mail_username nullable` · `mail_password nullable (encrypted)` · `mail_encryption=tls` · `mail_from_address nullable` · `mail_from_name="${app_name}"`. Mailer must read these at runtime (custom `StatusMailConfig` service overriding `config('mail.*')` per send, so changing settings needs no `.env` edit/restart).
  - `alerts` (master kill-switches + per-event): `email_alerts_enabled=true` · `webhook_alerts_enabled=true` · `telegram_enabled=false` · `discord_enabled=false` · `slack_enabled=false` (stubs for V3) · `notify_on_service_failed=true` · `notify_on_service_recovered=true` · `notify_on_incident_created=true` · `notify_on_incident_resolved=true` · `notify_on_maintenance_started=true` · `notify_on_maintenance_ended=false`. `NotificationManager` checks `mail_enabled && email_alerts_enabled && event_flag && rule` before dispatch; same for webhook.
  - `webhook` (when enabled): `webhook_default_url nullable` · `webhook_secret nullable (encrypted)` · `webhook_timeout=10`.
  - `retention`: `raw_checks_retention_days=60` · `daily_stats_retention_days=540` · `audit_retention_days=365`.
- **Admin UI (`/admin/status/settings`, perm `status.settings.manage`):** Tabler tabs `General|Branding|Monitoring|Public|Mail|Alerts|Retention`. Switches for every `*_enabled`, password-type inputs with `••••` + `Show` + `Clear`, `Send test email` + `Send test webhook` buttons (rate-limited), per-field help + validation (`base_url|url`, `port 1–65535`, `from_address|email`). Show effective mail status (`configured ✓ / missing host`).
- **Wiring:** `CheckService/IncidentManager/MaintenanceManager` read thresholds/flags from `StatusSetting` (with per-service override winning); public controllers gate on `public_page_enabled/public_api_enabled/subscriptions_enabled/badge_enabled`; mail sends no-op with logged warning when `mail_enabled=false` instead of throwing.
- **Done:** fresh seed boots with light theme + sane defaults; admin can rebrand, change URL/timezone, enter SMTP creds, toggle email/webhook alerts globally + per event, all without code deploy.

---

## 4. Phase Breakdown (maps to plan Recommended Order 01–27)

### Phase 0 — Scaffold + Laravel Boost
- `laravel new status-page` (latest), `.env`, timezone, Vite + `tabler + tom-select + apexcharts + monaco-editor`.
- Layouts: `admin-tabler`, `public-status` — both theme-ready from day one: `<html data-bs-theme>`, inline pre-paint `initTheme()` snippet, `<meta name="color-scheme" content="light dark">`, `x-theme-toggle` component placeholder, CSS-vars only (§3.1); error pages 404/403/500/429 (#99) also themed; SEO defaults (#98).
- `composer require laravel/boost --dev && php artisan boost:install`; verify MCP + guidelines; use Boost for all later codegen.
- Install Spatie Permission; seed roles `super-admin, status-manager, status-viewer` + all `status.*` permissions (#68).
- Core auth only (no Fortify): `LoginController` (`Auth::attempt` + session regenerate + `throttle:5,1`), `logout` (POST), password reset via core `Password` broker with the same Tabler views (`auth/login`, `auth/forgot-password`, `auth/reset-password`), same route names (`login`, `logout`, `password.*`).
- Create folder skeleton (§1) + empty admin dashboard renders.
- **Done:** migrate ok, login ok, dashboard renders in Tabler.

### Phase 1 — Database + Models (#4, #11, #14, #31–35, #36–41, #53–55, #67, #69–70, #113–114, #127)
1. `status_service_groups {id,name,slug unique,description,sort_order,is_active}`; `status_services.group_id FK`.
2. `status_services` full fieldset (#31): identity + `url,method,check_interval,timeout,connect_timeout` + `follow_redirects,max_redirects,verify_ssl,http_version,user_agent` + `request_headers(encrypted),query_params,request_body,request_body_type,authentication(encrypted)` + `expected_status_codes,response_time_warning/failure,response_assertions,json_assertions,header_assertions` + `current_status enum,last_checked/success/failure/next_check_at` + `is_active,is_public,sort_order` + per-service `failure_threshold,recovery_threshold,auto_create/resolve,notify_on_failure/recovery` (#39).
3. `status_checks {service_id,success,status enum,http_status,response_time,connect_time,final_url,redirect_count,response_size,error_type enum,error_message,assertion_result,checked_at}` — no raw bodies (#32–33, #86).
4. `status_daily_stats unique(service_id,date) {total/success/failed,uptime_percentage,avg/min/max_response}` (#34).
5. `status_incidents {service_id,title,slug unique,status enum,impact enum,started/resolved_at,created/updated_by}` + `status_incident_updates {incident_id,status,message,created_by}` (#36–37).
6. `status_maintenances {title,description,starts/ends_at,status enum}` + pivot `status_maintenance_services` (#40–41).
7. V2-in-scope: `status_header_presets {name,header_name,description,category enum,input_type,is_sensitive,is_active,sort_order}`, `status_header_templates {name,description,headers(encrypted if secrets),is_active}`, `status_notification_channels {name,type enum,config encrypted,is_active}`, `status_notification_rules {channel_id,service_id nullable (null=all),event enum,is_active}`, `status_subscribers {email,verification_token,verified_at,is_active}`, `status_audit_logs {user_id,action,subject_type/id,old/new_values (redacted),ip,user_agent}`, `status_settings` key-value (see §3.2 — general/branding/monitoring/public/mail/alerts/webhook/retention, mail password + webhook secret encrypted, seed theme_default=light + all alert toggles + retention defaults).
- Seeders: header presets Common/API (#10) + input types (#12: Content-Type/Accept options, sensitive flags #13), default templates (JSON API/Browser/Authenticated/Internal #14), full default settings row-set (§3.2).
- Indexes (#113): `checks(service_id,checked_at)`, `services(is_active,next_check_at,current_status)`, `incidents(service_id,status,started_at)`, `maintenances(starts_at,ends_at,status)`.
- Seeders: header presets Common/API (#10) + input types (#12: Content-Type/Accept options, sensitive flags #13), default templates (JSON API/Browser/Authenticated/Internal #14), default settings.
- **Done:** `migrate:fresh --seed` + factories ready.

### Phase 2 — Request Builder Backend (#5–23, #30, #72, #82)
- `SsrfGuard`, `RequestBuilder → StatusRequestDefinition DTO`, `HttpChecker`, `AssertionEngine + JsonAssertionEngine`, `StatusCalculator`, `ResponseAnalyzer`, `HeaderPresetManager`.
- Query params builder with URL encoding (#8); header rows + suggestions + custom (#9–10) + value types (#12) + sensitive handling (#13) + templates (#14); auth types (#15); body types (#16); assertions: HTTP status list (#18), warning/failure ms thresholds (#19), body contains/not/equals/regex (#20), JSON path + 8 operators (#21), header assertions (#22); Advanced tab defaults `15s/5s/Yes/5/Yes/Auto` (#23).
- **Done:** `build(service) → normalized DTO → checker` unit-testable, no controller logic.

### Phase 3 — Monitoring Engine + Jobs/Commands/Events (#26–29, #74–75, #83–91, #101–102)
- `CheckService` 19-step pipeline (#29): load → active? → maintenance? → build URL/query/headers/auth/body → SSRF-guard → execute (timeouts, max redirects, SSL, HTTP version, UA) → capture status/timings/size/final URL/redirects → assertions → `StatusCalculator` → save `status_checks` → update service state → incident detection → events/notifications.
- `status:dispatch-due` every minute (`next_check_at <= now()` → dispatch, update heartbeat) (#27, #90); `status:calculate-daily`; `status:cleanup`; `status:recalculate`; `status:test` (CLI debug).
- Queue: Scheduler → dispatch N jobs → DB workers (concurrency-safe, no giant loop #87).
- Classify failures + store `error_type` (#84); capture DNS/connect/TLS/TTFB/total for admin (#85) but show only safe subset publicly.
- **Done:** scheduler + worker process due services end-to-end with locks + heartbeat.

### Phase 4 — Admin UI, Tabler Premium (#6–10, #12–16, #24–25, #46–50, #66, #77–81, #116–117)
- Dashboard (#46): cards Total/Operational/Degraded/Outage/Maintenance + engine heartbeat + Current Incidents + Problems + Recent Checks + Avg Response + Uptime Overview (ApexCharts sparklines, theme-aware per §3.1) + topbar `x-theme-toggle` (persist + `theme:changed` re-render).
- Service form tabs `General|Request|Auth|Assertions|Advanced` (#6–7): repeatable query rows, header builder Tom Select grouped + custom (#79), masked secrets, auth forms, Monaco JSON (follows theme), assertion rows (#80), validation front+back (#81).
- Service form tabs `General|Request|Auth|Assertions|Advanced` (#6–7): repeatable query rows, header builder Tom Select grouped + custom (#79), masked secrets, auth forms, Monaco JSON, assertion rows (#80), validation front+back (#81).
- List (#47): datatable Service/Group/Status/Response/Last Checked/Interval/Active/Actions (View/Edit/Check Now/Pause/Delete) + filters + search.
- History (#48) + failed-check detail masked (#49) + combined timeline checks+incidents (#117); Monitoring page Last/Next/Queue/Status/Last Error (#50).
- `Test Request` modal: Request/Status/Timings/Final URL/Redirects/assertion checklist + failure diff (#24) + `Test Again` (#103); `Check Now` same job (#25). Both rate-limited. Internal AJAX `POST .../test, POST .../check, GET .../history` (auth+perm+CSRF+throttle #116).
- **Done:** full service lifecycle without code.

### Phase 5 — Public Pages + Caching (#3, #42–45, #58–59, #92–96, #118–121)
- `/status` layout (#42): logo (`logo_dark_path` in dark mode)/subscribe, `x-theme-toggle`, overall banner (#118), grouped services (#4), incident banner (#119), maintenance banner (#120), 90-day bars with hover `date/%/success/total` (#44), footer (`footer_text`, #121). Branding/SEO copy from settings (`app_name, app_tagline, app_description, base_url` canonical); gates `public_page_enabled`, refresh interval `public_refresh_seconds`.
- Detail (#43): status, % uptime, response chart (theme-aware), last checked, 90-day bars, recent incidents. Incident page timeline (#37, #45).
- Cache `status:public` 15–30s + groups/summaries/incidents/maintenance; invalidate on change (#58–59). Polling `GET /api/status` 30–60s (#96–97); no websockets V1.
- **Done:** public mostly cache-served, no exception leakage.

### Phase 6 — Stats/Incidents/Maintenance/Notify/API/Settings (#34–41, #51–57, #67–70, #91)
- Daily aggregation job; retention cleanup.
- Incidents: auto-create after N consecutive fails, auto-resolve after M recoveries (#38); degraded-incident optional rule (5 slow / last 10, later); manual CRUD + timeline updates.
- Maintenance multi-service via pivot; active forces `Maintenance` + excludes from uptime.
- Notifications: generic `NotificationManager` (Email + Webhook now; Telegram/Discord/Slack stubs per #124), gated by §3.2 flags (`mail_enabled && email_alerts_enabled && notify_on_*`, same for webhook) + per-service/rule scope, events (#52), rules All/Group/Service (#54), verified subscribers (#55, gated by `subscriptions_enabled`). Runtime `StatusMailConfig` reads SMTP creds from settings; `Send test email/webhook` actions.
- Public API `GET /api/status,/services,/services/{slug},/incidents` (gated by `public_api_enabled`) + badge/widget (gated by `badge_enabled`, #56–57).
- Settings page = full §3.2 tabbed UI (perm `status.settings.manage`), audit log redacted, permissions on every admin route.
- **Done:** incident/maintenance lifecycle + API + notifications live.

### Phase 7 — Tests, Security Review, Performance (#100, #109–113)
See §5–§6. `pint + phpstan + pest` green; indexes verified; scheduler/queue behavior under 10/100 services; retention timing verified.

---

## 5. Test Plan (proper test cases)

**Unit (Pest/PHPUnit):** status calc order; JSON operators (equals/not_equals/contains/not_contains/exists/not_exists/greater/less/matches incl. `$.data.items[0].x` later); body contains/not/equals/regex; header assertions; uptime formula + maintenance exclusion; incident thresholds; maintenance override; `SsrfGuard`; interval→`next_check_at`.

**Feature:** service/incident/maintenance CRUD + Spatie perms (403s); public pages render; API envelope; `Check Now` dispatches same job; `Test Request` renders checklist (`Http::fake`); theme toggle persists + defaults light; settings tabs save + validation + test-mail/webhook buttons; mail no-ops safely when `mail_enabled=false`; gates (`public_api_enabled`, `subscriptions_enabled`, `badge_enabled`, alert flags) enforced.

**Integration (safe fakes):** GET/POST/headers/auth/JSON/redirects/timeout/assertions end-to-end through `RequestBuilder → HttpChecker`.

**Must-pass (plan #110):**

```text
200 → Operational · 500 → Outage · 404 → Failed · 200+slow → Degraded ·
200+bad JSON → Failed · 401 → Failed unless expected · 302 → follow OK ·
302 with redirects off → Fail · SSL error → Failed · Timeout → Failed ·
DNS fail → Failed · Expected 204 → Operational · Maintenance window → Maintenance ·
3 consecutive fails → Incident created · 2 recoveries → Incident resolved
```

**Security suite (plan #111):**

```text
localhost blocked · 127.0.0.1 blocked · 10.x / 172.16.x / 192.168.x blocked ·
::1 blocked · 169.254.169.254 blocked · private redirect blocked ·
file/ftp/gopher/data/php/ssh schemes blocked · oversized response blocked ·
secrets absent from logs/audit/history
```

**Logging:** record check fail, queue fail, assertion error, notification fail, SSRF rejection — redacted (#100).

---

## 6. Performance, Retention, Ops

- 10 services: trivial. 100: queue + indexes. 1000 (future): Redis/Horizon, partitions, batched stats (#112) — code must not assume single-loop scheduler.
- Retention defaults (seeded, editable): raw checks 30–90d (configurable), daily 1y+, incidents/maintenance/audit kept (#35); `status:cleanup` daily reads retention keys from settings.
- Ops: `schedule:run` every minute (Laragon cron), `queue:work --queue=status` supervisor/service; dashboard heartbeat widget alerts when stale; `Unknown / Monitoring Delayed` separates “service down” from “monitor stopped” (#89). Theme FOUC check + chart/editor theme sync verified in both modes.
- Ops: `schedule:run` every minute (Laragon cron), `queue:work --queue=status` supervisor/service; dashboard heartbeat widget alerts when stale; `Unknown / Monitoring Delayed` separates “service down” from “monitor stopped” (#89).
- Future (V3, plan #124–125, not in scope but do not block): TCP/DNS/Ping/SSL-expiry/domain/cron/heartbeat monitors behind `MonitorChecker::check(): CheckResult` (`HttpChecker/TcpChecker/DnsChecker/SslChecker`); multi-tenant `status_page_id/workspace_id` (#106).

---

## 7. Scope Confirmation — V1+V2 Combined

Included now: service groups, full CRUD, GET/POST/PUT/PATCH/DELETE (+HEAD/OPTIONS), query/headers/suggestions/custom/auth/body, all assertion types, timeout/redirect/SSL, scheduler+queue+checks, history+uptime, public + detail pages, dashboard, Check Now, Test Request, basic+auto incidents, basic maintenance, SSRF (#122) **plus** header templates, notification channels + auto notifications, email subscriptions, webhook, incident automation improvements, response charts, daily stats, 90-day history, audit logs, public API, status badge (#123). Deferred to V3: Telegram/Discord/Slack polish, SLA reports, dependency maps, TCP/DNS/Ping/SSL-expiry, multi-page/multi-tenant.

---

## 8. Execution Checklist (recommended dev order)

```text
01 migrations → 02 models+casts/enums → 03 auth/permissions (Spatie) + Boost →
04 groups → 05 service CRUD → 06 header presets → 07 request UI (Tabler+TomSelect+Monaco) →
08 auth builder → 09 assertions builder → 10 RequestBuilder/DTO → 11 SsrfGuard →
12 HttpChecker/AssertionEngine → 13 CheckService job → 14 scheduler/queue →
15 history → 16 status calc → 17 public page → 18 detail → 19 daily stats →
20 incidents → 21 maintenance → 22 dashboard → 23 notifications+subscribers →
24 public API+badge → 25 audit+settings → 26 tests (unit/feature/integration/security) →
27 perf/security review (Pint, indexes, retention, rate limits, redaction)
```
