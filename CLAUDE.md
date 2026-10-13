# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel API project for Blockmatrix. Application code lives in `app/`, with common layers split into `Http/Controllers`, `Http/Requests`, `Services`, `Repositories`, `Jobs`, `Rules`, and `Models`. Routes are grouped under `routes/`, configuration is in `config/`, database artifacts are under `database/`, localized messages live in `resources/lang/<locale>/`, and project notes live in `docs/`. Tests are organized in `tests/Unit` and `tests/Feature`.

## Architecture & Coding Conventions

Follow existing Laravel style: PSR-4 namespaces, 4-space indentation, typed signatures for methods and parameters, and descriptive method names. Keep controllers thin: validate with a Request, call a Service, and return `succeed()`, `fail()`, `failWithData()`, or an existing response helper. Put business orchestration, transactions, locks, idempotency, events, and jobs in Services. Put query construction and lock helpers in Repositories. Keep Models limited to mappings, relations, casts, constants, and small model-derived helpers.

Use the existing project structure and sibling-file conventions. Do not create new base folders, introduce new dependencies, or change framework-level patterns unless explicitly required.

## Database & API Rules

Database changes must use Laravel migrations with the correct project connection. This repository uses multiple database constants; choose the connection from the owning model, migration, or closest existing implementation instead of assuming a single default.

- Use `DB_BM` / `Schema::connection(DB_BM)` for Blockmatrix-owned tables.
- Use `DB_API` for shared API, user, funding, IB, point, and copy-trading tables where existing code does.
- Use `DB_TS` for trading-system reads and joins where existing repositories do.

API responses must use the project response wrapper. Do not return bare arrays or strings. User-visible errors must use existing translated messages where the surrounding module does so. New or changed endpoints must keep Scramble annotations aligned with request rules and response shapes.

## Testing Guidelines

Current baseline is PHP 8.5+, Laravel 13.x, PHPUnit 11.5, and Scramble 0.13.x. Use `composer.json` and `composer.lock` as the source of truth for package versions.

Tests use PHPUnit with Mockery. Name test classes after the subject and use `test_...` methods. Bug fixes should include regression coverage when practical. For documentation-only or agent-config-only changes, tests are usually not required.

Use the smallest relevant verification command:

```bash
composer test
php artisan test
php artisan test --filter=TestName
```

Upgrading PHPUnit to 13 is a separate dependency change and should not be bundled with agent or documentation setup unless explicitly requested.

## Commit & Pull Request Guidelines

Prefer Conventional Commits: `feat(auth): add google login api`, `fix(funding): prevent duplicate withdraw submission`, `test(listener): cover first open trade points`. Keep commits scoped to one logical change. PRs must summarize behavior changes, list tests run, and call out migrations, config, Redis/cache changes, queue/schedule impacts, signature/auth changes, and release notes.

Follow the branch and PR guidelines in `docs/development/git-release.md`.

## Security & Configuration Tips

Do not commit secrets, real credentials, local logs, tokens, signatures, wallet private keys, or payment credentials. Environment differences belong in `.env`; code should read settings via `config()`, not direct `env()` outside `config/*.php`. Log with structured context, stable messages, business IDs, and masked sensitive values.

## High-risk Boundaries

Do not perform any of the following without explicit human confirmation: production deployment or rollback; destructive database migrations; production data modifications; broad changes to account balances, funding, trading, permissions, billing, or customer data; production-key or CI production-secret operations; broad cross-module rewrites; dependency upgrades; or removal of monitoring, audit, security checks, or rollback paths.

## Mandatory Development Checklist

For every non-trivial code change, consider the affected areas before implementation and again before finishing:

| Impact area | Required checks |
| --- | --- |
| API / RPC / webhook / event | Update Scramble annotations, request/response contracts, translated error messages, compatibility handling, and relevant Feature or contract coverage. |
| Database / migration / data fix | Assess connection, schema, indexes, backfill, rollback, backup, and release-window risk. |
| Transaction / lock / idempotency | Assess transaction boundaries, locking, idempotency keys, retry policy, and duplicate-execution protection. |
| Job / queue / schedule | Assess retry behavior, idempotency, schedule concurrency, and operational impact. |
| Logging / observability | Assess `request_id` and business IDs, log masking, alerts, and troubleshooting evidence. |
| Security / config / secret | Assess secrets, permissions, sensitive data, configuration exposure, and release-time configuration changes. |
| Tests | Select the appropriate Unit, Feature, regression, or smoke coverage. |

When the change is complete, summarize relevant checklist items in the final response or PR description. If a required check could not be completed, state why.

## Review Agent Instructions

When performing code review, use the checklist above as the review rubric. In addition to correctness bugs, review for Laravel layer violations, missing Request validation or translated errors, missing transactions/locks/idempotency, unsafe database changes, missing or misplaced regression tests, stale Scramble docs, logging/security risks, and queue/schedule/config/release impacts.

Review findings must be specific, actionable, and include file and line references. If no issues are found, state that clearly and mention residual risk or tests not run.
