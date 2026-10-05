# Security Policy

## Supported Versions

PulseDeck has no tagged releases yet. Security fixes are applied to the latest
commit on the `main` branch only. Please update to the latest `main` before
reporting an issue.

| Version                | Supported          |
| ---------------------- | ------------------ |
| `main` (latest)        | :white_check_mark: |
| Older commits / forks  | :x:                |

## Reporting a Vulnerability

Please do **not** open a public issue or pull request for security problems.

Report privately using either method:

- GitHub private vulnerability reporting: the **Security** tab of the
  [PulseDeck repository](https://github.com/yeasherarafath/PulseDeck) →
  **Report a vulnerability**.
- Email the maintainer: yeasherarafath@gmail.com

Include as much of the following as you can:

- Description of the issue and its impact.
- Steps to reproduce, or a proof of concept.
- Affected commit, PHP version, and environment details.
- Any suggested fix.

### What to expect

- Acknowledgement within 3 business days.
- Status update at least every 7 days until the report is resolved.
- If accepted: a fix is prepared privately, released to `main`, and you are
  credited in the fix notes unless you prefer to stay anonymous.
- If declined: you receive an explanation of why it is not treated as a
  vulnerability.

Please allow a reasonable time to fix the issue before any public disclosure.

## Scope

Areas of particular interest:

- Authentication, roles, and permissions (admin panel).
- SSRF guard for monitors and webhooks.
- Webhook handling and subscriber flows (double opt-in, unsubscribe).
- Storage of secrets such as SMTP credentials.

Out of scope:

- Issues that require `STATUS_WEBHOOK_ALLOW_PRIVATE=true` or other deliberately
  relaxed settings.
- Default local-dev credentials (`admin@example.com` / `password`); change
  these before going live.
- Vulnerabilities in third-party dependencies with no PulseDeck-specific
  impact; report those upstream.
