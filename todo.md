# PulseDeck — TODO

> Live demo: [https://status.newisty.com/](https://status.newisty.com/)
> Capability catalog: [`features.md`](features.md) · Setup: [`README.md`](README.md)

## Done

- [x] Flexible check schedules per service — fixed interval (custom count + unit:
      minutes, hours, days, weeks, months, years) or 5-part cron expression
      (`schedule_type` + `cron_expression` on `status_services`, single
      `CheckScheduler` owner for next-run, human labels, and stale grace).
- [x] Public demo link + storage-link docs in `README.md`.

## Next

### Monitoring

- [ ] SSL-expiry / TCP / DNS / Ping monitors behind the `MonitorChecker` interface
      (no interface in code yet — HTTP-only today).
- [ ] Push / heartbeat monitors (dead-man-switch for cron jobs & backups).
- [ ] SMTP / WebSocket / gRPC / DB-connect checks.
- [ ] Later: multi-region checks, Playwright / real-browser synthetics.

### Notifications & subscribers

- [ ] Telegram / Discord / Slack notification channels.
- [ ] SMS / voice-call alerting (e.g. Twilio) for wake-me-up outages.
- [ ] Per-service subscriptions + RSS / iCalendar feeds.

### Public page & incident communication

- [ ] Incident templates (canned investigating → resolved texts).
- [ ] Public incident archive + postmortem field on resolved incidents.
- [ ] Announcements (non-incident posts).
- [ ] Maintenance reminders + recurring windows.
- [ ] Backdated incident creation.
- [ ] Custom CSS / custom domain / white-label; status embed widget.
- [ ] Third-party component status (AWS / Stripe / …).

### API & automation

- [ ] Write API (incidents, components, maintenance) + personal API tokens.
- [ ] Prometheus metrics endpoint; backup/restore UI; Statuspage/Cachet import.

### Access, enterprise & trust

- [ ] SLA reports + multi-page / multi-tenant support (see `features.md` §10).
- [ ] Private status pages (password / SSO / IP allowlist).
- [ ] 2FA for admins.
- [ ] Artisan command to create a super-admin account (e.g. `status:create-admin`),
      replacing the tinker one-liners in `README.md` — no click-to-create-admin UI
      by design, command-line only.
- [ ] Public-page i18n (no `lang/` directory yet).
- [ ] On-call schedules + escalation chains (later — a second product, after the above).
- [ ] Per-service cron timezone override (currently uses Settings → General timezone).
- [ ] Bulk actions on Services (pause/resume, group move).
- [ ] Version tagging once people start using it — SemVer git tags (`v1.0.0`,
      `v1.1.0`, …), keep `APP_VERSION` in sync, add `CHANGELOG.md`, and publish
      GitHub Releases so deployments are traceable.

## Notes

- The dispatcher (`status:dispatch-due`) ticks every minute, so 1 minute is the
  finest granularity in both interval and cron modes.
- Interval window: 1 minute – 1 year. Months ≈ 30 days, years ≈ 365 days.
- Changing a service's schedule re-queues it immediately (`next_check_at = now`).
