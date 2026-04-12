---
name: pw-writing-skills
description: "Use when creating or improving Boost skills for ProcessWire agents to ensure optimal CSO formatting and testing requirements."
category: meta
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Writing Skills

Create, improve, and validate agent skills for the ProcessWire Boost ecosystem.

**Writing skills IS Test-Driven Development applied to process documentation.**

## 1. The Iron Law (TDD for Skills)

```
NO SKILL WITHOUT A FAILING TEST FIRST
```

This applies to NEW skills AND EDITS to existing skills.

If you didn't watch an agent fail without the skill, you don't know if the skill teaches the right thing. 

**RED -> GREEN -> REFACTOR Cycle:**
1. **RED (Baseline):** Run a pressure scenario with subagents *without* the skill. Document the exact behavior and rationalizations the agent uses to skip best practices.
2. **GREEN (Minimal Skill):** Write the specific skill addressing those rationalizations. Verify the agent now complies.
3. **REFACTOR (Close Loopholes):** If the agent finds a new workaround, add an explicit counter to the skill.

**Violating the letter of the rules is violating the spirit of the rules.**
Agents will rationalize under pressure ("It's too simple to test", "I'll test later"). Do not allow this. Add explicit anti-patterns to prevent loopholes.

## 2. When to Use & Skill Directories

- Creating a NEW skill for `resources/boost/skills/`
- Improving an EXISTING Boost skill that agents ignore
- Debugging why a skill isn't being triggered

All Boost skills live in `resources/boost/skills/<skill-name>/`:

```
resources/boost/skills/
├── pw-module-development/         # Core module scaffold & PHP patterns
│   └── SKILL.md
├── pw-pages/                      # Selectors, CRUD, page lifecycle
│   └── SKILL.md
└── pw-brainstorming/              # Design process & implementation planning
    └── SKILL.md
```

**Naming Convention:** All Boost skills MUST use the `pw-` prefix (e.g., `pw-pages`, `pw-module-development`).

## 3. YAML Frontmatter (CSO Optimization)

Every `SKILL.md` MUST start with:

```yaml
---
name: skill-name              # Must match directory name
description: "Use when..."    # Starts with 'Use when' — this is the trigger
risk: safe                    # safe | unknown | critical
source: processwire-boost     # Always processwire-boost for Boost skills
---
```

### Description Best Practices (Critical for Discovery)
The `description` field is how AI agents discover skills. **Never summarize the skill's workflow or process in the description.** Describe ONLY the triggering conditions.

```yaml
# ✅ Good — trigger-based
description: "Use when building custom Fieldtype and Inputfield modules in ProcessWire."

# ❌ Bad — summary-based / shortcut
description: "A comprehensive guide to ProcessWire Fieldtype development."
```
If your description summarizes the workflow, the AI might skip reading the file and just hallucinate the steps!

## 4. Skill Content Structure

```markdown
# ProcessWire [Skill Title]

## When to Use
- [Specific trigger condition]

## Core Concepts
- ProcessWire-specific patterns and examples
- Code samples using `wire()`, `$pages`, `$sanitizer`
- CLI commands using `vendor/bin/wire`

## Anti-Patterns & Loophole Closing
- What NOT to do (with ❌ markers)
- Explicit counters for AI rationalizations

## Related Skills
- Links to complementary pw- skills
```

## 5. Quality & Deployment Checklist

Before deploying any skill, run through this TDD-adapted checklist:

**Testing (Mandatory):**
- [ ] Created pressure scenarios for the skill
- [ ] Watched an agent fail without the skill (Baseline/RED)
- [ ] Verified an agent complies with the new skill (GREEN)
- [ ] Closed newly discovered rationalization loopholes (REFACTOR)

**Formatting:**
- [ ] `name` field matches directory name exactly
- [ ] `SKILL.md` filename is ALL CAPS
- [ ] Description starts with "Use when..." and does NOT summarize workflows
- [ ] All code examples use ProcessWire API (not Laravel/Symfony)
- [ ] PHP examples use `declare(strict_types=1)` and `namespace ProcessWire`
- [ ] CLI examples use `vendor/bin/wire` or `wire` alias
- [ ] Total SKILL.md under 500 lines (split into references/ if larger)

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Skipping the RED phase | Always test baseline behavior before writing the file |
| Laravel examples in PW skills | Use ProcessWire API equivalents |
| Description summarizes content | Use "Use when..." triggers |
| Missing anti-patterns section | Add ❌ markers to block AI shortcuts and workarounds |
