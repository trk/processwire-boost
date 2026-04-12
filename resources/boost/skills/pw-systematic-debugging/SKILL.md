---
name: pw-systematic-debugging
description: "Use when encountering any bug, test failure, blank screen of death, or unexpected behavior in ProcessWire before proposing fixes."
risk: safe
source: processwire-boost
date_added: "2026-04-13"
---

# ProcessWire Systematic Debugging

Random fixes waste time and mask underlying issues. ProcessWire notoriously swallows exceptions inside hooks and template renders, making "guessing the bug" a guaranteed failure.

<HARD-GATE>
NO FIXES OR FILE MODIFICATIONS WITHOUT ROOT CAUSE INVESTIGATION (LOG READING) FIRST.
If you have not read the logs via MCP or checked ProcessWire context, you cannot propose fixes.
</HARD-GATE>

## Phase 1: Context & Log Gathering (Mandatory)

Before running any fix, you must retrieve the actual error stack.

1. **Read System Logs (MCP Server)**
   ProcessWire stores fatal errors and exceptions in textual log files (`site/assets/logs/`).
   - Run MCP tool: `pw_system_get_logs` to list available logs.
   - Run MCP tool: `pw_system_logs_tail_last` on `errors` or `exceptions`.
   - Never skip past stack traces. Look precisely at the failing file and line number.

2. **Verify Debug Mode**
   If logs are empty but the system is failing (e.g. HTTP 500 or Blank White Screen), ensure debug mode is on.
   Check `site/config.php` for `$config->debug = true;`.

3. **Hook Tracing**
   If an event isn't firing (e.g., `Pages::saved`), ensure your Hook isn't intentionally bypassing execution or failing silently. Insert explicit logging right inside the hook temporarily:
   ```php
   $event->wire()->log->save('debug', "Hook fired for page ID: " . $event->arguments(0)->id);
   ```

## Phase 2: Hypothesis Formation

Once evidence is gathered from the logs, form a specific hypothesis.
- Example: "The log says *Method name() does not exist or is not callable in this context*. This means `$pages->get('template=foo')` returned a `NullPage`, and we didn't verify `$page->id` before interacting with it."

## Phase 3: Minimal Fix & Verification

- Apply the **SMALLEST** possible fix to test the hypothesis (e.g., Wrap logic in `if($page && $page->id)`).
- Verify the fix using a ProcessWire validation command (`pw_execute`, CLI script, or reloading the endpoint).
- **Do NOT** bundle code refactoring ("Since I am fixing this bug, I will rewrite the whole hook class") with the bug fix.

## Anti-Patterns & Loophole Closing

- ❌ **Guess-and-Check:** Proposing "Let's change this string/variable and see if it works" before reading `errors.txt` via MCP.
- ❌ **Symptom Patching:** Using `@` (error suppressor) or burying problems in generic `try-catch` blocks without understanding *why* the ProcessWire API failed.
- ❌ **Ignoring `NullPage` Logic:** `pw-expert` rule violation. `$pages->get()` returns an empty `NullPage` object, not `null` or `false`. `if($page === null)` will fail. You must check `if($page->id)`.
- ❌ **Multiple Fixes at Once:** Changing the Template, the Hook, and the Module Controller simultaneously. You are masking which layer actually caused the crash.

## Triggering Execution
Once the root cause is successfully verified via minimal steps, either apply the fix immediately or invoke `writing-plans` if the refactor is substantial.
