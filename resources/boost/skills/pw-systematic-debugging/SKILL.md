---
name: pw-systematic-debugging
description: "Use when encountering any bug, exception, test failure, blank screen, hook misfire, or unexpected selector result in ProcessWire before proposing fixes."
risk: safe
source: processwire-boost
date_added: "2026-04-13"
---

# ProcessWire Systematic Debugging

ProcessWire catches and logs exceptions inside hooks, template rendering, and admin actions. Guessing a fix without reading the logs or reproducing the failure state wastes time and masks root causes.

<HARD-GATE>
NO CODE MODIFICATIONS WITHOUT EVIDENCE FIRST.
Read the ProcessWire logs (`pw_system_logs_tail_last` or `vendor/bin/wire log:tail`) and reproduce the failing query or code path before proposing a fix.
</HARD-GATE>

## Phase 1: Evidence & Log Collection (Mandatory)

1. **Inspect ProcessWire Logs (`site/assets/logs/`):**
   - Via MCP: Run `pw_system_get_logs` or `pw_system_logs_tail_last` on `errors` and `exceptions`.
   - Via CLI: Run `php vendor/bin/wire log:list --json` and `php vendor/bin/wire log:tail errors --lines=30 --json` (or `php vendor/bin/wire log:read exceptions --limit=20 --json`).
   - Record the exact file, line number, and exception message from the stack trace.
2. **Reproduce with a Tight Feedback Loop:**
   - For selector bugs: Run `pw_query` (MCP) or `php vendor/bin/wire page:find "template=article, limit=10" --json` to observe what ProcessWire actually returns.
   - For runtime/module bugs: Run a minimal reproduction via `pw_execute` (MCP) or `php vendor/bin/wire test --filter=...` so you have a command that reliably goes red on the bug.
3. **Trace Silent Hooks:**
   - If a hook (`Pages::saved`, `Page::render`, URL hook) appears not to run, verify where it is registered (`site/init.php` vs `site/ready.php` vs module `init()`/`ready()`) and log its invocation:

```php
$event->wire()->log->save('debug', sprintf(
	'Hook fired for page %d (template=%s)',
	(int) $event->arguments(0)->id,
	(string) $event->arguments(0)->template?->name
));
```

## Phase 2: Root-Cause Hypothesis

Check the most frequent ProcessWire failure modes against your evidence:
- **`NullPage` dereference:** `$pages->get()` and `$pages->findOne()` return a `NullPage` object (`id === 0`) when no match is found, never `null`. Checking `if ($p !== null)` always evaluates to `true`! Always check `if ($p->id)` or `if (!$p instanceof NullPage)`.
- **Access/Status exclusion in `$pages->find()`:** `$pages->find()` and `$pages->findOne()` exclude `hidden`, `unpublished`, and restricted pages unless `include=hidden|unpublished|all` is passed.
- **Output formatting state (`$page->of()`):** Saving a loaded page on the frontend without calling `$page->of(false)` first can throw an exception or corrupt formatted field values.
- **Internal property bypass in Custom Page Classes:** Accessing `$this->template` inside a `Page` subclass can bypass lazy loading; use `$this->get('template')` when inside a custom `Page` method if needed.

## Phase 3: Minimal Fix & Regression Verification

1. Apply the smallest surgical change that addresses the proven root cause.
2. Re-run the exact reproduction command (`pw_query`, `pw_execute`, or `php vendor/bin/wire test`) and verify it passes and no new entries appear in `errors.txt` / `exceptions.txt`.
3. Never bundle unrelated refactoring into a bug fix.

## Anti-Patterns & Loophole Closing

- ❌ **Proposing a fix before reading `errors` / `exceptions` logs:** Always inspect the logs first.
- ❌ **Checking `if ($page === null)` on `$pages->get()` / `$pages->findOne()`:** Always check `if ($page->id)`.
- ❌ **Suppressing errors with `@` or empty `catch (\Throwable)` blocks:** Log unexpected exceptions via `$this->wire()->log->error($e->getMessage())`.
- ❌ **Changing multiple layers simultaneously:** Change one variable/layer at a time so you can prove which change resolved the failure.

## Related Skills
- `pw-test`
- `pw-pages`
- `pw-expert`
