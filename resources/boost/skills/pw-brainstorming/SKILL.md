---
name: pw-brainstorming
description: "Use when brainstorming or designing ProcessWire modules, templates, field schemas, custom page classes, or hooks to resolve ambiguity and validate architecture before implementation."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Brainstorming

Turn feature ideas into **validated ProcessWire specifications** through grounded discovery and structured dialogue before touching code or running migrations.

## When to Use
- Before starting any non-trivial ProcessWire feature that introduces or alters templates, fields, custom page classes, URL hooks, permissions, or modules.
- Whenever a user request contains architectural ambiguity (data modeling, RBAC, routing, or UI approach).

<HARD-GATE>
Do NOT write implementation code, scaffold modules, or run migrations until you have explored the live schema, presented a concise design, and received user approval.
</HARD-GATE>

## Ordered Workflow

### 1. Ground in Live ProcessWire Context (Mandatory)
Before asking the user questions, inspect the current deployment:
- Run `pw_schema_read` (MCP) or read `.agents/map.json` to see existing templates, fields, roles, and permissions.
- Check `site/modules/` and `site/classes/` for existing domain models and modules.
- If the request spans multiple independent subsystems, decompose them and scope brainstorming to the first subsystem.
- **Completion criterion:** You know which templates, fields, and modules already exist and have zero unanswered factual questions that the codebase itself can answer.

### 2. Clarify Decisions One Question at a Time
Facts are your job; design decisions belong to the user:
- Ask **one focused question per turn** (prefer multiple-choice options with a recommended default).
- Clarify: page tree hierarchy (`parent` / `template`), field relationships (`FieldtypePage` vs `FieldtypeRepeater` vs `WireEntries` / custom `Fieldtype`), access control (roles/permissions), and explicit non-goals.

### 3. Lock Understanding (Soft Gate)
Summarize the agreed scope in 5-10 lines:
1. Target ProcessWire components (templates, fields, page classes, modules, hooks).
2. Constraints & explicit non-goals.
3. Explicit assumptions.
Ask: *"Does this accurately capture what we are building?"*

### 4. Propose Approaches & Present Design
- Present 2-3 viable ProcessWire architectures (e.g., Custom Page Class + Template vs. URL Hook service vs. Process Module), leading with your recommended choice and its trade-offs.
- Present the chosen design in concise sections (data model, hooks/routing, security/RBAC, verification plan).

### 5. Write Spec & Self-Review
Save the approved specification to `docs/specs/YYYY-MM-DD-<topic>.md` and verify inline:
- **Zero placeholders:** No `TODO` or `TBD` sections.
- **Schema accuracy:** Every referenced template or field either already exists in `pw_schema_read` or is explicitly listed as a new migration item.
- **Scoping & Security:** Every endpoint or form defines its `$sanitizer`, RBAC (`$user->hasPermission()`), and CSRF strategy.

### 6. Handoff to Planning
Once the user approves the specification, invoke `pw-writing-plans` to produce the step-by-step migration and implementation plan.

## Anti-Patterns & Loophole Closing

- ❌ **Asking the user what templates or fields exist:** Never ask the user a question that `pw_schema_read` or `.agents/map.json` can answer directly.
- ❌ **Wall of questions:** Asking 4-5 open-ended questions in a single turn stalls decision-making; ask one structured question at a time.
- ❌ **Writing migrations during brainstorming:** Schema mutations belong in the implementation phase after `pw-writing-plans`.

## Related Skills
- `pw-writing-plans`
- `pw-migrations`
- `pw-custom-page-classes`
