---
name: git-workflow
description: Generates branch names, commit messages, and PR descriptions following the project's git conventions. Use when creating branches, writing commits, or opening pull requests.
---

# Git Workflow

Generates branch names, commit messages, and PR descriptions that follow the project's conventions.

## When to Use This Skill

Use when:
- Creating a new branch for a feature, bugfix, hotfix, or chore.
- Writing or reviewing commit messages.
- Drafting a pull request description.

## Instructions

### Branching

Branch roles:

| Prefix | Purpose |
|--------|---------|
| `main` | Fixed branch — production environment |
| `develop` | Fixed branch — development environment |
| `feature/*` | New feature development |
| `bugfix/*` | Bug fixes found during development or testing |
| `hotfix/*` | Critical production issues requiring immediate fix |
| `chore/*` | Non-code changes (dependencies, config, tooling) |

Branch naming pattern:

```
{type}/{short_description}
```

Rules:
- Use lowercase, separate words with hyphens.
- Keep the description concise but descriptive.

Examples:

```
feature/change-password
hotfix/missing-login-params
bugfix/duplicate-order-creation
chore/upgrade-laravel-11
```

### Commit Messages

Follow Conventional Commits:

| Type | When to use |
|------|-------------|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `style` | Formatting or spacing (no logic change) |
| `refactor` | Restructuring without behavior change |
| `test` | Test additions or updates |
| `chore` | Tooling, deps, or routine maintenance |

Format:

```
type(scope): short imperative description
```

Rules:
- Scope is optional but encouraged — use the module or domain (e.g., `auth`, `funding`, `copy_trading`).
- Use imperative mood ("add", "fix", "remove", not "added", "fixes", "removed").
- Keep the subject line under 72 characters.
- Use `!` after the type/scope or a `BREAKING CHANGE:` footer for breaking API changes.

Examples:

```
feat(auth): add google login api
fix(funding): prevent duplicate withdraw submission
test(listener): cover first open trade points
refactor(order): extract position sizing to dedicated service
chore: upgrade phpunit to v13
feat(copy_trading)!: change follower profit split response format
```

### Pull Requests

PR title should match the primary commit message format:

```
feat(auth): add google login api
```

PR body must include the following sections (omit sections that don't apply):

```markdown
## Summary

[Concise description of what changed and why]

## Tests

- [ ] Unit tests added/updated
- [ ] Feature tests added/updated
- [ ] Manual testing performed

## Impact Assessment

- **Migrations**: [Yes/No — describe schema changes]
- **Config/Env**: [Yes/No — new or changed env vars]
- **Queue/Schedule**: [Yes/No — new jobs, changed dispatch, schedule changes]
- **Redis keys**: [Yes/No — new or changed keys, TTL changes]
- **Kafka topics/schemas**: [Yes/No — new topics, message format changes]
- **Signature/Auth**: [Yes/No — auth flow or signing changes]
- **Linked issues**: [Issue references]

## Release Notes

[User-visible changes for changelog, or "N/A" for internal-only changes]

## Rollback Plan

[How to revert if something goes wrong, or "Standard revert" for simple changes]
```

PRs should target `main` by default unless explicitly told otherwise.

When drafting a PR, review the staged commits and the diff against the target branch to populate each section accurately. Leave out sections that are entirely not applicable to keep the description focused.
