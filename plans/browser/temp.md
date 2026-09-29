# Other Modules — Detailed Guide (same depth as Notifications)

> Sidebar root: `resources/views/layouts/admin-tabler.blade.php:38-132` — `Monitor / Respond / Configure` sections.
> Companion: notification module doc + `features.md`. Routes: `routes/web.php`, `routes/api.php`.

---

## 1. Public Status Page — _what visitors see_

| URL | Controller | Notes |
|---|---|---|
| `GET /` + `GET /status` | `StatusPageController@index` | Same payload, subdomain-ready, no redirect |
| `GET /status/services/{slug}` | `showService` | Only `is_public`, 404 otherwise |
| `GET /status/incidents/{slug}` | `showIncident` | Full timeline |
| `GET /status/refresh` | `refresh` | Polling JSON, `throttle:60,1` |
| `GET /status/badge.svg` | `badge` | Embeddable SVG, gated by `badge_enabled` |
| `POST /status/subscribe` | `subscribe` | `throttle:10,1` |
| `GET /status/verify/{token}`, `unsubscribe/{token}` | `verify/unsubscribe` | Double opt-in + one-click out |

Core service: `app/Services/Status/PublicStatusService.php`:

* `payload()` cached 30s under `status:public-v2` — groups → services → overall banner → active incidents → maintenances → 90-day bars. All public pages + polling + API read through here, no per-visitor DB storm.
* `servicePayload(id)` cached per-service `status:service-v2:{id}` — daily stats window (`uptime_window_days`, default 90), last 100 response times, last 5 incidents, 90-day %.
* `overallStatus()`: `MajorOutage > PartialOutage > Degraded > Maintenance(all) > Operational`. Invalidated in `CheckService:144-147` only when `current_status` flips.
* Views: `resources/views/status/index.blade.php`, `service.blade.php`, `incident.blade.php`, layout `public-status.blade.php`. Live refresh ~`public_refresh_seconds` (default 45s), dark/light toggle, branding via `setting('logo_path')`.

## 2. Monitoring Engine — _the watcher_

Entry: `status:dispatch-due` every minute (`app/Console/Commands/Status/DispatchDueChecks.php`):

```text
syncStates() maintenance Scheduled→Active→Completed (+events)
  → heartbeat cache status:monitor:heartbeat (600s TTL)
  → StatusService::due() (is_active && next_check_at<=now) chunk 100 → CheckService::dispatch(id)
```

Job: `app/Jobs/Status/CheckService.php` (`tries=2, backoff 10/30s`, lock `status-check:{id}` 120s):

1. Skip if `!is_active && !manual` (still bumps `next_check_at`).
2. `MaintenanceManager::isUnderMaintenance()` → passed to calculator.
3. `RequestBuilder::fromService() + validateDefinition()` → `HttpChecker::check()` → `AssertionEngine::run()` → `StatusCalculator::calculate()`.
4. Invalid stored config → visible failed `StatusCheck` row, never crash worker.
5. Write `status_checks` row: `success, status, http_status, response_time/connect_time, final_url, redirect_count, response_size, error_type/message, assertion_result JSON, checked_at`.
6. `recordCheckResult()` updates `current_status/last_checked/success/failure/next_check_at (+check_interval)`.
7. `IncidentManager::handleResult()` → `fireTransitionEvents()` → `ServiceCheckCompleted` → bust public cache if flipped.

### Pipeline pieces

* `RequestBuilder` — builds `StatusRequestDefinition` from `StatusService` (method/URL/headers/query/body/auth/expected codes/timeouts/assertions). `normalizeServiceAttributes()` used by create/update. Secrets encrypted (`request_headers, authentication` casts `encrypted:array`).
* `HttpChecker` — Laravel HTTP, `allow_redirects=false`, manual redirect loop (max `max_redirects`) so `SsrfGuard` validates **every** hop. Caps body at 1 MB (`Oversized`), records connect/total ms via `on_stats`. Bodies: `none/json/form/urlencoded/raw`.
* `SsrfGuard` — `assertSafeUrl()` on original + each redirect: scheme `http/https` only, blocks `localhost`, private/reserved ranges (`NO_PRIV_RANGE|NO_RES_RANGE`), resolves **all** A/AAAA to defeat DNS rebinding.
* `AssertionEngine + JsonAssertionEngine` — status codes, `response_time_warning` → `warnings` (degraded), `response_time_failure` → `failures`, body `contains/not-contains/equals/regex`, JSON path + operators (`equals/not-equals/contains/exists/...`), header `exists/not-exists/equals/...`. Returns `{passed, failures[], warnings[]}` stored on check.
* `StatusCalculator` — fixed order: `maintenance → transport error → unexpected HTTP status → failed assertions → too slow(failed) → warning(degraded) → operational`.
* Flap protection: `effectiveStatus()` — public status flips to down only after `min_failed_checks_down` (per-service else `min_failed_checks_down` setting) consecutive `success=false`. Check rows always written.

Admin: `Monitoring` page (`MonitoringController`, `monitoring/index.blade.php`) — queued jobs count (`jobs` table), failed jobs (`failed_jobs`), heartbeat Running/Stale, per-service last/next check, response time, last error.

## 3. Services & Request Builder (admin) — _what to watch_

Sidebar: `Monitor > Services`, `Groups`. Routes `admin.status.services.*`, `groups.*`.

Model `StatusService` (`status_services`): group, name/slug, URL/method, `check_interval/timeout/connect_timeout`, redirects/SSL/version/user-agent, headers/query/body/auth (encrypted), `expected_status_codes, response_time_warning/failure`, 3x assertion JSONs, `current_status/last_*/next_check_at`, `failure_threshold(3)/recovery_threshold(2)/min_failed_checks_down`, `auto_create/resolve_incidents`, `notify_on_failure/recovery`, `is_active/is_public/sort_order`.

* CRUD via `ServiceController + StatusServiceManager` (audited, secrets redacted — blank means keep). `pause` toggles `is_active`. `check` sets `next_check_at=now + dispatch(manual=true)`.
* Form: tabbed `General/Request/Auth/Assertions/Advanced` (`services/_form.blade.php`), header presets/templates (`HeaderPresetManager`), expected statuses CSV, Advanced holds thresholds + notify flags.
* `Test Request` modal: `POST test` (saved) / `test-unsaved` (unsaved) → `StatusServiceManager::testRequest()` runs live check, returns redacted JSON, **never writes history, never fires events**.
* `show`: 25/page check history + 30-day uptime + 1-day avg response (`UptimeCalculator`).
* Groups: `StatusServiceGroup` (`name/slug/is_active/sort_order`), `active()->ordered()`, services sorted inside.

## 4. Incidents — _when down becomes official_

Sidebar: `Respond > Incidents`. Model `StatusIncident` (`status_incidents` + `status_incident_updates`): `service_id nullable, title/slug, status[investigating/identified/monitoring/resolved], impact[minor/major/critical], started/resolved_at, created/updated_by`.

* Auto: `IncidentManager::maybeOpen()` — needs `auto_create_incidents`, no active incident, last N checks all failed (`failure_threshold`, default 3). Creates + `Investigating` update + `IncidentCreated`. `maybeResolve()` — needs `auto_resolve_incidents`, last M successes (`recovery_threshold`, default 2) → `resolve()` + update + `IncidentResolved`. Helper `activeForService()`.
* Manual: `IncidentController store/update/destroy/storeUpdate` — multi-service allowed (`service_id=null`), slug `title-Ymd-Hi-s`, audit-logged, dispatches `IncidentCreated/Updated/Resolved` (resolve only on transition to resolved).
* Public: active incidents card (10 latest) + `/incidents/{slug}` timeline, `PublicStatusService::incidentRow()`. Emails via notification module on create/update/resolve.
* Admin `show`: service link, status/impact badges, timestamped updates form.

## 5. Maintenance Windows — _planned downtime ≠ outage_

Sidebar: `Respond > Maintenance`. Models `StatusMaintenance` + pivot `status_maintenance_service`.

Fields: `title/description/starts_at/ends_at/status[scheduled/active/completed/cancelled]/created_by`. CRUD in `MaintenanceController` (`services[] min:1` required, `ends>starts`, `cancel` sets cancelled, audit-logged).

Engine: `MaintenanceManager::syncStates()` (called by scheduler) flips `scheduled→active`, `active→completed` + fires `MaintenanceStarted/Ended` (service=null, global rules only). `isUnderMaintenance(service)` checked per-check → `StatusCalculator` returns `Maintenance`, uptime calculators exclude it.

Public: blue card (upcoming 10), affected services show `Scheduled Maintenance` badge, not red.

## 6. Subscribers (public opt-in) + Channels/Rules/Deliveries

Covered in notification doc — subscriber half summarized: `StatusSubscriber(email, verification_token one-time, unsubscribe_token persistent, verified_at, is_active)`. Flow `subscribe→firstOrCreate→verify mail→verify(nulls token, keeps unsubscribe)→alert mails (individual, footer)→unsubscribe(deletes row)`. Admin `Subscribers` tab: enable/disable/delete, paginated 25.

## 7. Settings — _8 groups, partial-safe_

Sidebar: `Configure > Settings` (`permission:status.settings.manage`). Model `StatusSetting(key,value,type[group],is_encrypted,description)` + `SettingGroup` enum (General/Branding/Monitoring/Public/Mail/Alerts/Webhook/Retention) + `SettingType(string/integer/boolean/json)`.

* `StatusSetting::get(key,default)` cached 60s, decrypts + casts; `::set()` re-encrypts + busts cache.
* `SettingsController update`: builds rules from DB types, validates URLs/emails/ports, **full-form missing checkbox = OFF, partial payload touches only submitted keys**, blank encrypted secrets keep stored, branding uploads (`logo_path/logo_dark_path/favicon_path` → `storage/app/public/branding`, drag-drop + preview + remove).
* `testMail/testWebhook` actions (throttled 5/min) with ok/error flashes.
* Key settings: `app_name/tagline/base_url/timezone/theme_default/admin_prefix`, monitoring defaults + thresholds, public toggles + `public_refresh_seconds/history_window`, SMTP full set + `mail_enabled`, 8x `notify_on_*` + master `email/webhook_alerts_enabled`, webhook timeout/retries, retention days (`checks/daily_stats/audit/deliveries`).

## 8. Dashboard, API, Auth, Ops

* **Dashboard** (`DashboardController`): counts by state, top 10 problems, 5 active incidents, 10 recent checks, 24h avg response, heartbeat. No params.
* **Public API** (`routes/api.php`, `StatusApiController`, `throttle:60,1`, gated by `public_api_enabled`): `GET /api/status`, `/services`, `/services/{slug}` (+30d uptime), `/incidents` (25 latest). Envelope `{ok,data}`.
* **Auth/Roles:** session auth (login/forgot/reset, no starter kit), Spatie permissions `status.*` (18 granular). Roles: `super-admin` all, `status-manager` all except settings, `status-viewer` read-only. Sidebar `@can()` gates, routes `permission:*` middleware, `admin_prefix` editable via setting.
* **Audit log:** `StatusAuditLog::record(action, model, before, after)` — service/incident/maintenance/channel/rule/subscriber/settings changes with user + IP. Read-only page, retention-purged.
* **Artisan/Queue:** `status:dispatch-due` (every min, needs scheduler + worker), `status:calculate-daily` (nightly `status_daily_stats`), `status:cleanup` (retention purge), `status:recalculate`, `status:test --service=ID` (redacted debug). Uptime: `UptimeCalculator` (raw checks, excludes maintenance) for admin + `StatusDailyStat` aggregation for public bars.
