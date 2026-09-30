# Contributing to PulseDeck

Thanks for helping out. This guide keeps contributions consistent and reviewable.

## Ways to contribute

- Report bugs (include steps to reproduce, expected vs actual, environment).
- Propose or build features from [`todo.md`](todo.md).
- Improve docs (`README.md`, `features.md`, in-app API docs).

## Setup

Full setup is in [`README.md`](README.md). Short version:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer run dev   # web + queue worker + scheduler
```

## Workflow

1. Create a branch from `main`: `feat/short-name`, `fix/short-name`, or `docs/short-name`.
2. Keep changes focused — one concern per pull request.
3. Write tests for behavior changes (see below).
4. Run the checks (see below) before pushing.
5. Open a PR describing **what** changed and **why**. Link related issues / todo items.

Commit messages follow `<type>(<scope>): <short description>` (e.g. `feat(status): add
push monitors`, `fix(public): correct last-updated time`). Types: `feat`, `fix`,
`docs`, `refactor`, `test`, `chore`; scope is the area touched; description is
imperative, lowercase, no trailing period.

## Checks (must pass)

```bash
php artisan test            # full suite — must stay green
vendor/bin/pint --dirty     # PHP code style — run before finalizing
npm run build               # after any CSS/JS change, commit the rebuilt assets
```

## Code conventions

- PHP 8.4: constructor property promotion, explicit return types and parameter
  type hints, curly braces always, `TitleCase` enum keys.
- Laravel way: use `php artisan make:` for new classes/controllers/models/tests;
  slim controllers (Validate → Authorize → Service → View); business logic lives
  in `app/Services/Status/*`, never in controllers or jobs directly.
- Stick to the existing directory structure; don't add new top-level folders or
  dependencies without discussing first.
- Reuse existing Blade components and admin UI patterns (Tabler) before building new ones.
- Follow sibling files for structure and naming; descriptive names
  (`isRegisteredForDiscounts`, not `discount()`).

## Tests

- Most tests should be **feature** tests: `php artisan make:test {Name}Test`.
- Use model factories for test data; check for existing factory states first.
- Narrow runs while iterating: `php artisan test --filter=testName`.
- Security-sensitive areas (SSRF guard, auth, webhooks, unsubscribe flow) need
  regression tests with every fix.

## Security

- Never commit `.env`, credentials, tokens, or real SMTP/webhook secrets.
- Secrets in code use `encrypted:array` casts and must be redacted in logs,
  audit trails, test output, and API responses.
- New URL-fetching features (monitors, webhooks) must go through the SSRF guard.
- Found a vulnerability? Contact the maintainer privately instead of opening a
  public issue.

## Docs

- User-facing changes need a matching update in `README.md` and/or `features.md`.
- New public API surface must be added to the in-app API docs page too.

## Release notes

Once the project is adopted, changes are released with SemVer git tags
(`v1.0.0`, …) plus a `CHANGELOG.md` entry — see [`todo.md`](todo.md).
