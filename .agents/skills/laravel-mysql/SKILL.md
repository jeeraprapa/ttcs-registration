---
name: laravel-mysql
description: Develop, debug, and review this UH Registration repository using Laravel and MySQL. Use for its public registration flow, admin portal, Blade UI, booking transactions, consent, mail, and related tests.
---

# UH Registration development

Resolve paths below from this skill directory, or use their equivalent repository-root paths.

## Load maintained project guidance

1. Read [project rules](../../../AGENTS.md).
2. Read [product requirements](../../../.ai/agent.md) and [technical workflow](../../../.ai/skills/laravel-mysql/SKILL.md) before implementation.
3. Apply the [senior developer role](../../../.ai/agents/senior-dev.md) for implementation, debugging, architecture, and review.
4. Read [system design](../../../.ai/system-design.md) for affected architecture, booking concurrency, admin authorization, or email processing. Distinguish optional proposals and historical status from confirmed requirements and current implementation.

If `.ai/rules` exists, read its index, all matching rules, and search task keywords before editing. Keep the `.ai` sources authoritative rather than duplicating their full contents here. If a referenced file is missing, report the missing source and continue only work that does not depend on it.

## Apply repository-specific constraints

- Confirm installed PHP package versions with `composer show --direct` or `composer show <vendor/package>`; inspect `package.json` for JS versions before relying on APIs.
- Follow the latest schedule in `.ai/agent.md`: 112 shared sessions on the confirmed November/December 2026 dates, one active booking per session. Guests remain 1-4 including the registrant. Cancellation releases the slot; cancelled QR codes cannot check in.
- Preserve the production policy gate. Title/country/consent/timezone values remain provisional until confirmed; do not infer Mainland applicability from phone codes.
- Keep booking idempotency, consent snapshots, transaction rollback, and confirmation ownership intact. Local email preview is not evidence of real delivery.
- Follow the repository's PHPUnit convention. Before writing tests, read `testing-best-practices` if available; otherwise locate its project instructions and report if unavailable. Run the narrowest meaningful affected tests. Verify MySQL-specific behavior on an isolated MySQL test database.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Build affected frontend assets when needed, and compare changed UI with the original references on desktop and mobile.
- Check current code and test results; historical test totals in `.ai` do not establish current success. Report what was actually verified.
