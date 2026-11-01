---
name: pw-writing-skills
description: "Use when creating or editing ProcessWire Boost skills (SKILL.md) or agent guidelines to optimize context pointers, information hierarchy, and TDD skill verification."
category: meta
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

All Boost skills live in `resources/boost/skills/pw-<topic>/SKILL.md`:

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
1. `declare(strict_types=1);` and `namespace ProcessWire;` (or the exact `Site\*` / `Totoglu\*` namespace).
2. **Tabs for indentation** (never 4 spaces).
3. **Curly braces on all control structures**, even single-line `if` / `foreach` statements.
4. Context-safe API access (`$this->wire()->apiVar` in `Wire` classes, `$event->wire()->apiVar` in hooks, `wire()->apiVar` in anonymous classes/closures).
5. CLI examples invoked via `php vendor/bin/wire <command>`.

## 4. Deployment & Verification Checklist

- [ ] `name` starts with `pw-` and matches the directory name in `resources/boost/skills/`.
- [ ] `description` starts with `"Use when..."`, is under 500 characters, and contains zero workflow summaries.
- [ ] All PHP code blocks use tabs, `declare(strict_types=1);`, and curly braces on all control structures.
- [ ] All CLI commands, options, and table names match `trk/processwire-console` and `trk/processwire-boost` implementations.
- [ ] Includes an `## Anti-Patterns & Loophole Closing` section countering real agent mistakes.

## Related Skills
- `pw-docs-architect`
- `pw-writing-plans`
