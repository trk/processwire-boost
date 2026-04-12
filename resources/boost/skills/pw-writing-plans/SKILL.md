---
name: pw-writing-plans
description: "Use when creating implementation plans from approved specifications to ensure ProcessWire-native architecture, safe migration structures, and strict test-driven task execution."
risk: safe
source: processwire-boost
date_added: "2026-04-13"
---

# ProcessWire Writing Plans

Write comprehensive implementation plans assuming the engineer has zero context for our codebase. Document everything they need to know: exact files, proper ProcessWire API logic, and step-by-step verification commands.

## Core Concepts

**Announce at start:** "I'm using the `pw-writing-plans` skill to create the implementation plan."

**Save plans to:** `docs/specs/plans/YYYY-MM-DD-<feature-name>.md`

### File Structure & Scope
- If a spec describes multiple independent subsystems, break it down. Write the plan for the *first* subsystem only.
- Splitting by responsibility: Identify which Hooks go into `site/ready.php`, which schema changes go into `site/migrations/`, etc.

### Bite-Sized Task Granularity (The TDD Proxy)
Each task must be a single action (2-5 minutes). Rather than `pytest`, ProcessWire tasks verify implementation via CLI checks (`wire field:info`, `pw_query` MCP) or functional scratch scripts.

## Plan Document Header (Mandatory)

**Every plan MUST start with this header:**

```markdown
# [Feature Name] Implementation Plan

> **For agentic workers:** Execute this plan task-by-task. Steps use checkbox (`- [ ]`) syntax. Do not skip verification steps.

**Goal:** [One sentence describing what this builds]

**Architecture:** [2-3 sentences about the ProcessWire implementation approach]
---
```

## Task Structure Template

Use this format for every ProcessWire task. Ensure you never output placeholders.

````markdown
### Task 1: Create Summary Field

**Files:**
- Create: `site/migrations/2026_04_13_000000_create_summary_field.php`

- [ ] **Step 1: Write Migration Command**

```bash
wire make:migration create_summary_field --type=create-field --field=summary
```

- [ ] **Step 2: Implement Code (e.g., configuring field in migration file)**

```php
<?php
// ... inside migration up() method ...
$field = $this->wire()->fields->get('summary');
$field->type = $this->wire()->modules->get('FieldtypeTextarea');
$field->label = 'Article Summary';
$field->save();
```

- [ ] **Step 3: Run & Verify Implementation**

Run: `wire migrate`
Run Verification: `wire field:info summary --json`
Expected: Output showing the field is created with label "Article Summary".

- [ ] **Step 4: Commit**

```bash
git add site/migrations/
git commit -m "migration: add summary field"
```
````

## No Placeholders (Hard Rule)

Every step must contain the **ACTUAL** content an engineer needs. NEVER write these plan failures:
- ❌ "TBD", "TODO", "implement later"
- ❌ "Add appropriate ProcessWire error handling" (Write the actual `$sanitizer` logic instead)
- ❌ "Update the hook logic here" (Without providing the exact `addHookAfter` code block)
- ❌ "Run ProcessWire verification commands" (Without explicitly providing `wire config:get` or similar)

## Self-Review Checklist

After writing the complete plan, look at it with fresh eyes. Fix issues inline before confirming.

**1. Spec coverage:** Can you point to a task that implements every aspect of the brainstormed specification? 
**2. Placeholder scan:** Search your plan for "TODO" or "TBD". Fix them.
**3. ProcessWire Type Consistency:** Did you accidentally use `$this->pages` in a template context? Fix it to `$pages` or `$this->wire()->pages` depending on the file type.

## Anti-Patterns & Loophole Closing

- ❌ **Skipping Migrations:** Recommending manual UI configuration instead of migration files. All schema must be code-driven.
- ❌ **Telling instead of coding:** Step instructions without actual code blocks or bash scripts.
- ❌ **Massive Monolith Tasks:** Bundling field creation, template generation, and Hook assignments into a single 20-minute step. Break it down!

## Execution Handoff

After saving the plan, say:

**"Plan complete and saved to `<filename>.md`. Ready to execute task by task?"**
