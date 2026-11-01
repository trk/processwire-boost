---
name: pw-writing-plans
description: "Use when creating step-by-step implementation plans from approved ProcessWire specifications before writing code or running migrations."
risk: safe
source: processwire-boost
date_added: "2026-04-13"
---

# ProcessWire Writing Plans

Write unambiguous, bite-sized implementation plans for an engineer or subagent who has not seen the conversation history. Lock in exact file paths, ProcessWire API signatures, migration commands, interface contracts, and verification commands.

**Announce at start:** "I'm using the `pw-writing-plans` skill to create the implementation plan."

**Save plans to:** `docs/specs/plans/YYYY-MM-DD-<feature-name>.md` (unless the user specifies another path).

## Scope & Decomposition Rules

- **One subsystem per plan:** If a specification spans multiple independent subsystems, split it into separate plans that each produce working, testable software on their own.
- **Map files before tasks:** Identify upfront which changes belong in `site/migrations/`, `site/classes/`, `site/modules/<Name>/`, `site/templates/`, or `site/ready.php`.
- **Task right-sizing:** Each task is the smallest unit that carries its own verification cycle (migration + `wire field:info --json`, or Pest test + `wire test --filter=...`).

## Plan Document Header (Mandatory)

Start every plan with this exact structure:

```markdown
# [Feature Name] Implementation Plan

> **For agentic workers:** Execute this plan task-by-task. Steps use checkbox (`- [ ]`) syntax. Run every verification command before marking a step complete.

**Goal:** [One sentence describing the observable outcome]

**Architecture:** [2-3 sentences on the ProcessWire components: templates, fields, custom page classes, hooks, or modules]

**Spec:** [Path to the spec document this plan implements]

## Global Constraints

- PHP strict typing (`declare(strict_types=1);`), tab indentation, and curly braces on all control structures.
- Scoped API access (`$this->wire()->apiVar` in `Wire` classes, `$event->wire()->apiVar` in hooks, `wire()->apiVar` in anonymous migration classes).
- [Project-specific version, module, or RBAC constraints from the spec]

## Review Focus

- [Top edge cases or failure modes implied by the spec — e.g., `NullPage` return when a referenced page is trashed, unauthenticated access to URL hooks, output formatting state `$page->of(false)` before save — each mapped to the task that tests it]

---
```

## Task Structure Template

````markdown
### Task 1: Create Summary Field and Attach to Article Template

**Files:**
- Create: `site/migrations/YYYY_MM_DD_HHMMSS_create_summary_field.php`
- Test: `site/tests/Feature/ArticleSchemaTest.php`

**Interfaces:**
- Consumes: Existing `article` template
- Produces: Field `summary` (`FieldtypeTextarea`) attached to `article`

- [ ] **Step 1: Scaffold the migration**

Run: `php vendor/bin/wire make:migration create_summary_field --type=create-field --field=summary --fieldtype=FieldtypeTextarea --label="Article Summary"`

- [ ] **Step 2: Implement `up()` and `down()` in the migration file**

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

return new class {
	public function up(): void
	{
		$fields = wire()->fields;
		$templates = wire()->templates;

		$field = $fields->get('summary');
		if (!$field) {
			$field = new Field();
			$field->type = wire()->modules->get('FieldtypeTextarea');
			$field->name = 'summary';
		}
		$field->label = 'Article Summary';
		$fields->save($field);

		$template = $templates->get('article');
		if ($template && $template->fieldgroup && !$template->fieldgroup->hasField($field)) {
			$template->fieldgroup->add($field);
			$template->fieldgroup->save();
		}
	}

	public function down(): void
	{
		$fields = wire()->fields;
		$templates = wire()->templates;
		$field = $fields->get('summary');
		if (!$field || !$field->id) {
			return;
		}

		$template = $templates->get('article');
		if ($template && $template->fieldgroup && $template->fieldgroup->hasField($field)) {
			$template->fieldgroup->remove($field);
			$template->fieldgroup->save();
		}

		if ($field->getFieldgroups()->count() === 0) {
			$fields->delete($field);
		}
	}
};
```

- [ ] **Step 3: Run migration and verify schema**

Run: `php vendor/bin/wire migrate --json`
Verify: `php vendor/bin/wire field:info summary --json`
Expected: `"ok": true` with `"name": "summary"` and `"type": "FieldtypeTextarea"`.

- [ ] **Step 4: Commit**

```bash
git add site/migrations/
git commit -m "feat(schema): add summary field to article template"
```
````

## Self-Review Checklist (Run Before Presenting the Plan)

1. **Spec coverage:** Every requirement in the spec maps to a concrete task.
2. **Zero placeholders:** No `TODO`, `TBD`, `"add appropriate validation"`, or `"write tests for the above"`.
3. **Scope accuracy:** Confirm anonymous migration classes use `wire()->apiVar` (never `$this->wire()`, which does not exist on anonymous classes), `Wire`-derived classes use `$this->wire()->apiVar`, and hooks use `$event->wire()->apiVar`.
4. **Interface consistency:** Method names, field names, and template names match identically across all tasks.
5. **Review Focus pinned:** Every failure mode listed in `## Review Focus` is exercised by a verification step or test in its owning task.

## Anti-Patterns & Loophole Closing

- ❌ **Manual Admin UI instructions instead of migrations:** Every schema change (fields, templates, roles, permissions, module installs) must be expressed as a migration or CLI command.
- ❌ **Calling `$this->wire()` inside a migration anonymous class:** Migration files return `new class { ... }` which does not extend `Wire`. Always use `wire()->fields`, `wire()->templates`, `wire()->pages`.

## Related Skills
- `pw-brainstorming`
- `pw-migrations`
- `pw-test`
