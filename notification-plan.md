# Notifications & Email — Audit and Full Plan

> Scope: everything that turns a monitoring event into a message.
> Status: core pipeline built (Phases 3+6) and verified live (Mailpit + httpbin).
> This plan records what works, the gaps found on review, and the work to close them.

---

## 1. Architecture (as built)

```text
CheckService / IncidentManager / MaintenanceManager / Admin controllers
        │  domain events (no notification code inside the checker)
        ▼
ServiceWentDown, ServiceBecameDegraded, ServiceRecovered,
IncidentCreated, IncidentUpdated, IncidentResolved,
MaintenanceStarted, MaintenanceEnded
        │  listeners (AppServiceProvider)
        ▼
NotificationManager::notify(event, service?, subject, lines, url)
  checks: event setting flag → active channels with matching active rules
          (service-scoped or all-services) → mail/webhook master switches
        │  per channel: recipients = channel.to + verified subscribers (public events)
        ▼
SendStatusNotification (queued, tries 2, backoff 30/120s)
  mail    → StatusMailConfig::apply() (runtime SMTP from settings) → StatusAlertMail (markdown)
  webhook → POST JSON + X-Status-Signature (HMAC, settings secret) with timeout
  failures → Log::warning (redacted), no retry storm
```

Supporting pieces: `StatusMailConfig`, `StatusAlertMail` + `emails/status-alert`,
channels/rules/subscribers admin CRUD, public subscribe → verify → unsubscribe,
settings test-mail/test-webhook actions, per-event kill-switches in settings.

---

## 2. Verified working (evidence)

| # | Behavior | Evidence |
|---|---|---|
| 1 | Failing check → `ServiceWentDown` → mail to channel recipients + verified subscriber | Mailpit log: `To: ops@example.com, user@example.com`, subject delivered |
| 2 | Webhook delivery with signature | httpbin job `DONE`, 0 failed jobs |
| 3 | Mail failures don't crash the pipeline | `Unknown named parameter` bugs surfaced as logged warnings, fixed, job completed |
| 4 | Disabled mail (`mail_enabled=false`) skips silently | `StatusMailConfig::isConfigured` gate, no exceptions |
| 5 | Unimplemented channel types (telegram/…) skipped | `implemented()` guard |
| 6 | Subscribe → verify → confirmed; unverified never mailed | Browser-tested end to end |
| 7 | Test mail + test webhook from settings | Both actions return ok/error flashes |
| 8 | Secrets never logged | HMAC signs payload, secret itself never in logs; audit redacted |

---

## 3. Gaps (ranked)

### P0 — must fix

**G1. Verified subscribers cannot unsubscribe.**
`verification_token` is nulled on verify, but `unsubscribe/{token}` looks up by
that same column → every verified user gets 404. Alert mails also carry no
unsubscribe link (email-compliance risk).
*Spec:* add `status_subscribers.unsubscribe_token` (unique, persistent, generated
at subscribe time); `unsubscribe/{token}` matches it; append
`Unsubscribe: <url>` footer line to `StatusAlertMail` for subscriber sends
(channel sends keep no footer). Migration + backfill (`Str::random(48)` for
existing rows). Tests: token survives verify; link unsubscribes exactly one row.

**G2. Per-service `notify_on_failure` / `notify_on_recovery` are ignored.**
Columns exist on `status_services` (plan #39) but `NotificationManager` never
reads them — a paused-for-alerts service still pages everyone.
*Spec:* in `notify()`, for `service.failed` skip when
`$service->notify_on_failure` is false; for `service.recovered` skip when
`$service->notify_on_recovery` is false. Applies to channel recipients AND
subscriber fan-out. Service edit form already needs no change (fields exist in
DB; expose them on the form Advanced tab next to `min_failed_checks_down`).
Tests: matrix over flag × event.

### P1 — should fix

**G3. Double email on incident auto-recovery.**
One recovery currently emits `service.recovered` (transition) AND
`incident.resolved` (threshold) — two mails seconds apart for one event.
*Spec:* when `IncidentManager` auto-resolves in the same run, the recovery
notification collapses to the incident one: `CheckService` passes the resolved
incident (already returned by `handleResult()`) and skips `ServiceRecovered`
when an incident resolved this run. Manual resolves keep singleincident mail.
Document the rule in the plan and UI copy.

**G4. No delivery visibility.**
Today: logs + `failed_jobs` only; admins can't answer "did ops get paged?".
*Spec (lightweight):* `status_notification_deliveries`
`{id, channel_id, event, service_id nullable, recipient_count, status
(sent|failed|skipped), error nullable, created_at}` — one row per job outcome,
recipient EMAILS stored only as count (+ domain list?) for privacy; full
addresses stay in logs-off. Admin read-only page under Notifications.
Retention via `status:cleanup` (same `audit_retention_days`).

**G5. Degraded has no distinct voice.**
`ServiceBecameDegraded` reuses the `service.failed` event/wording ("is down").
*Spec:* add `service.degraded` event + `notify_on_service_degraded` flag +
rule support, subject "{name} is degraded", severity below failed. Keeps
back-compat: existing `service.failed` rules do NOT match degraded.

### P2 — later (V3)

- Per-subscriber preferences (per-service / severity opt-out).
- Telegram / Discord / Slack senders behind `implemented()`.
- Digest mode (batch flapping services into one mail).
- CAPTCHA/double-opt-in hardening on subscribe; per-IP throttle already exists.

---

## 4. Build order (when approved)

```text
Step 1  Migration: subscribers.unsubscribe_token (unique, backfill) — G1
Step 2  StatusPageController: verify keeps token; unsubscribe by new token — G1
Step 3  StatusAlertMail + manager: unsubscribe footer for subscriber sends — G1
Step 4  Manager: honor notify_on_failure/recovery + form fields — G2
Step 5  CheckService: collapse recovery double-mail (use handleResult return) — G3
Step 6  G5 degraded event + flag + rule support (migrate nothing: event string only)
Step 7  Deliveries table + admin page + cleanup wiring — G4
Step 8  Tests (below) → pint → browser re-verify → commit per step batch
```

---

## 5. Test plan (test cases: automated + browser-based)

Every gap ships with both kinds of proof. IDs are used in commit messages
(`Refs N-T3`, `Refs N-B2`) so coverage stays traceable.

### 5A. Automated test cases (Pest/PHPUnit)

| ID | Covers | Type | How | Asserts |
|---|---|---|---|---|
| N-T1 | G2 | Feature | Service with `notify_on_failure=false`; fire `ServiceWentDown` with `Queue::fake()` | No `SendStatusNotification` queued |
| N-T2 | G2 | Feature | Same for `notify_on_recovery=false` + `ServiceRecovered` | No job queued |
| N-T3 | G2 | Feature | Flags true + matching rule | Job queued with channel recipients |
| N-T4 | core | Unit | `NotificationManager`: master switch off / event flag off / no matching rule / inactive channel | No job in each case |
| N-T5 | core | Feature | Rule scoped to service A; event for service B | No job; event for A queues |
| N-T6 | core | Feature | `Mail::fake()`; verified + unverified subscribers; channel `to` list | Mail sent exactly to verified + channel recipients |
| N-T7 | core | Feature | `Http::fake()` webhook rule | Payload has event/subject/url; `X-Status-Signature` present with secret, absent without |
| N-T8 | G1 | Feature | Subscribe same email twice | One row, one token (idempotent) |
| N-T9 | G1 | Feature | Verify via token, then unsubscribe via `unsubscribe_token` | Exactly one row flipped, then deleted; old verify token 404s |
| N-T10 | G3 | Feature | Failing checks → incident opens → passing checks → auto-resolve, `Queue::fake()` | Exactly one resolution job (no separate `service.recovered` job) |
| N-T11 | G5 | Feature | Degraded check with only `service.failed` rule | No job; with `service.degraded` rule → job |
| N-T12 | G4 | Feature | Failed send (bad webhook URL, `Http::fake()` 500) | `status_notification_deliveries` row `failed` with error text, no secret leakage |
| N-T13 | core | Unit | `StatusMailConfig::apply()` with SMTP settings | `config('mail.mailers.smtp.*')` + from address reflect settings |
| N-T14 | core | Feature | `SendStatusNotification` with inactive/deleted channel | Silent no-op, no exception |

### 5B. Browser-based test cases (human, admin + Mailpit + webhook catcher)

| ID | Covers | Steps | Expected |
|---|---|---|---|
| N-B1 | G1 | Subscribe new email → open verify link from DB/Mailpit → click unsubscribe link in a later alert mail | Confirmed flash; unsubscribe removes exactly that row |
| N-B2 | G1 | Alert mail inspection in Mailpit | Subject + lines + View-link + Unsubscribe footer all render |
| N-B3 | G2 | Service edit → uncheck failure alerts → save → fire failing check (Test request can't fire events; use staging service + short threshold) → run worker | No mail in Mailpit, no webhook hit |
| N-B4 | G3 | Full fire drill on staging service: fail → incident → recover → worker drains | Exactly one failure mail + one resolution mail (no duplicates) |
| N-B5 | G4 | After N-B4, open Notifications deliveries page | Rows: sent with counts; failed drill (bad URL) shows error text |
| N-B6 | core | Channels CRUD (mail + webhook), rules CRUD, subscriber toggle/remove | All round-trips flash + persist |
| N-B7 | core | Settings → test mail to admin inbox → test webhook to catcher | Both arrive; signature verifies out-of-band |
| N-B8 | core | Disable `mail_enabled` → fire drill | No mails, webhook still fires; no errors in UI |

Each browser case records: URL path, key screenshot on pass (banner/mail/deliveries row),
and console-error count = 0 (only the Boost logger line allowed).

**Ops notes:** queue worker + scheduler mandatory (README already covers);
`failed_jobs` checked after drills; Mailpit (`localhost:1025`) is dev-only —
prod needs real SMTP in settings + `mail_enabled`.

---

## 6. Out of scope (explicitly not in this plan)

SMS/push providers, per-user notification routing, SLA breach alerts,
incident-note @mentions. These belong to V3 with the sender interface
(`NotificationChannelType::implemented()` is the seam).
