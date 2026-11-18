---
name: pw-writing-skills
description: "Use when creating or editing ProcessWire Boost skills (SKILL.md) or agent guidelines to optimize context pointers, information hierarchy, and TDD skill verification."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Writing Skills & Agent Documents

Author, refine, and verify `SKILL.md` playbooks and guideline documents for the ProcessWire Boost ecosystem.

## 1. Core Principles (`writing-for-agents` + Skill TDD)

### Context Pointers & The Two Loads
- **Context load** is the permanent cost of always-loaded text (`AGENTS.md` guidelines and skill `description` frontmatter) on every turn.
- A skill's `description` is a **context pointer**: its wording alone decides when the agent loads the skill.
  - **Start with `"Use when..."`** in third-person.
  - **List distinct trigger branches** (symptoms, ProcessWire classes, CLI commands, or tasks that require the skill).
  - **Never summarize the workflow or process in `description`.** When a description summarizes the steps, agents follow the one-line summary as a shortcut and skip reading `SKILL.md`.

### Information Hierarchy, Co-location & Pruning
- **In-file steps** (ordered actions ending on a checkable completion criterion) sit at the top of the hierarchy.
- **In-file reference** (tables, API signatures, anti-patterns) is co-located under clear headings so reading a rule brings its caveats with it.
- **Progressive disclosure:** Keep `SKILL.md` under 500 lines. Push heavy 100+ line API tables or templates into sibling reference files inside the skill directory and point to them explicitly.
- **Single source of truth & pruning:** Never duplicate rules already enforced by `php.md` or `processwire-core.md` unless a domain-specific gotcha requires it. Delete no-op sentences that do not change default model behavior.

### Match the Guidance Form to the Failure Mode

| Baseline Agent Failure | Effective Form | Counter-Productive Form |
|---|---|---|
| Violates a discipline rule under pressure (skips logs, skips migrations, guesses schema) | Hard gate + prohibition + rationalization counter-table | Soft advice (`"prefer..."`, `"consider..."`) |
| Produces wrong-shaped output (wrong class structure, wrong indentation, wrong API scope) | Positive recipe / complete canonical code block | List of `"don't..."` negations |
| Omits a required piece (missing `[Type]Field.php`, missing `down()` in migration) | Explicit numbered phase or checklist slot | Prose reminder buried in a paragraph |

## 2. The Iron Law: RED -> GREEN -> REFACTOR for Skills

```text
NO SKILL OR SKILL EDIT WITHOUT OBSERVING THE BASELINE FAILURE FIRST
```

1. **RED (Baseline):** Identify the exact mistake or rationalization an agent makes without the skill (e.g., calling `$this->pages` in a module, using 4 spaces instead of tabs, or writing `$this->wire()` in an anonymous migration class).
2. **GREEN (Minimal Skill):** Write the minimal positive recipe and explicit guardrails addressing those failures.
3. **REFACTOR (Close Loopholes):** Verify with a subagent or review pass that every code block and instruction is unambiguous and free of contradictions.

## 3. SKILL.md Frontmatter & Formatting Contract

Boost skills live in one of three canonical discovery paths:
- **Core Boost package:** `resources/boost/skills/pw-<topic>/SKILL.md`
- **Module-bundled skill:** `site/modules/<Module>/.agents/skills/pw-<slug>/SKILL.md`
- **Project-local skill:** `site/.agents/skills/pw-<slug>/SKILL.md`

```yaml
---
name: pw-topic-name
description: "Use when [specific ProcessWire trigger conditions, classes, or tasks]."
risk: safe
source: processwire-boost
date_added: "YYYY-MM-DD"
---
```

### Mandatory Code Block Standards inside Skills
Agents copy code patterns directly from skills. Every PHP code snippet inside a `SKILL.md` MUST obey:
1. **Standalone PHP files (`<?php`):** Include `declare(strict_types=1);` and `namespace ProcessWire;` (or the exact `Site\*` / `Totoglu\*` namespace). Short method/statement fragments may omit the file header when context is unambiguous.
2. **Tabs for indentation** (never 4 spaces).
3. **Curly braces on all control structures**, even single-line `if` / `foreach` statements.
4. Context-safe API access (`$this->wire()->apiVar` in `Wire` classes, `$event->wire()->apiVar` in hooks, `wire()->apiVar` in anonymous classes/closures).
5. CLI examples invoked via `php vendor/bin/wire <command>`.

## 4. Deployment & Verification Checklist

- [ ] `name` starts with `pw-` and matches the skill directory name.
- [ ] `description` starts with `"Use when..."`, is under 500 characters, and contains zero workflow summaries.
- [ ] All PHP code blocks use tabs and curly braces on all control structures (and standalone `<?php` files declare `strict_types=1`).
- [ ] All documented PHP method signatures, CLI options (`configure()`), and table names are verified against the real classes (via `ReflectionClass` or source inspection).
- [ ] Includes an `## Anti-Patterns & Loophole Closing` (or `## Anti-Patterns to Avoid`) section countering real agent mistakes.
- [ ] Passes `php vendor/bin/wire boost:validate --module=<Module|site-overrides> --strict --json` with zero errors or warnings.

## Anti-Patterns & Loophole Closing

- ❌ **Summarizing workflow steps inside `description:`:** Agents treat summary descriptions as shortcuts and skip loading `SKILL.md`. Keep `description:` strictly to trigger conditions (`"Use when ..."`).
- ❌ **Space-indented PHP examples (`4 spaces`):** Agents mirror indentation from code blocks verbatim; always indent PHP blocks with tabs.
- ❌ **Unverified CLI flags or ProcessWire class names:** Verify every cited `wire/` path, `php index.php docs` target, and `vendor/bin/wire` option before saving.

## Related Skills
- `pw-docs-architect`
- `pw-writing-plans`
