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

- [ ] SSL-expiry / TCP / DNS / Ping monitors behind the `MonitorChecker` interface.
- [ ] Telegram / Discord / Slack notification channels.
- [ ] SLA reports + multi-page / multi-tenant support (see `features.md` §10).
- [ ] Per-service cron timezone override (currently uses Settings → General timezone).
- [ ] Bulk actions on Services (pause/resume, group move).

## Notes

- The dispatcher (`status:dispatch-due`) ticks every minute, so 1 minute is the
  finest granularity in both interval and cron modes.
- Interval window: 1 minute – 1 year. Months ≈ 30 days, years ≈ 365 days.
- Changing a service's schedule re-queues it immediately (`next_check_at = now`).
