# Git, Pull Request & Release Guidelines

## Branching

Branch roles:

- `main`: fixed branch for production environment.
- `develop`: fixed branch for development environment.
- `feature/*`: feature development.
- `bugfix/*`: standard bug fixes found during development or testing.
- `hotfix/*`: critical issues that must be fixed immediately in the production environment.
- `chore/*`: Often used for small, non-code fixes like updating dependencies or configuration files.

Branch naming:

```text
feature/{feature-name}
hotfix/{issue-name}
```

Example:

```text
feature/change-password
hotfix/missing-login-params
```

## Commit Messages

Follow Conventional Commits where practical:

- `fix`: bug fix.
- `feat`: new feature.
- `docs`: documentation only.
- `style`: formatting or spacing only.
- `refactor`: restructuring without behavior change.
- `test`: test additions or updates.
- `chore`: tooling or routine maintenance.

Use `!` or a `BREAKING CHANGE:` footer for breaking API changes.

Examples:

```text
feat(auth): add google login api
fix(funding): prevent duplicate withdraw submission
test(listener): cover first open trade points
```

## Pull Requests

Standard flow:

1. Branch from the appropriate mainline.
2. Develop and commit focused changes.
3. Push the branch.
4. Open a Pull Request.
5. Complete Code Review.
6. Merge after approval.

PRs must include:

- Behavior summary.
- Tests run.
- Linked issues.
- Migration impact.
- Config/env impact.
- Queue or schedule impact.
- Redis key changes.
- Kafka topic or message schema changes.
- Signature/auth changes.
- Release or rollback notes.
