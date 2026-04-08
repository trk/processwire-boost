---
name: pw-writing-skills
description: "Use when creating or improving Boost skills for ProcessWire agents. Covers skill architecture, YAML frontmatter, CSO optimization, and testing."
category: meta
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Writing Skills

Create, improve, and validate agent skills for the ProcessWire Boost ecosystem.

## When to Use

- Creating a NEW skill for `resources/boost/skills/`
- Improving an EXISTING Boost skill that agents ignore
- Debugging why a skill isn't being triggered
- Standardizing skills across the ProcessWire ecosystem

## Skill Directory Structure

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

### Naming Convention

All Boost skills MUST use the `pw-` prefix (e.g., `pw-pages`, `pw-module-development`, `pw-brainstorming`).

## YAML Frontmatter (Required)

Every `SKILL.md` MUST start with:

```yaml
---
name: skill-name              # Must match directory name
description: "Use when..."    # Starts with 'Use when' — this is the trigger
risk: safe                    # safe | unknown | critical
source: processwire-boost     # Always processwire-boost for Boost skills
date_added: "YYYY-MM-DD"
---
```

### Description Best Practices

The `description` field is how AI agents discover skills. Write it as a trigger:

```yaml
# ✅ Good — trigger-based
description: "Use when building custom Fieldtype and Inputfield modules in ProcessWire."

# ❌ Bad — summary-based
description: "A comprehensive guide to ProcessWire Fieldtype development."
```

## Skill Content Structure

```markdown
# ProcessWire [Skill Title]

## When to Use
- [Specific trigger condition]
- [Another trigger condition]

## [Core Content]
- ProcessWire-specific patterns and examples
- Code samples using `wire()`, `$pages`, `$sanitizer`
- CLI commands using `vendor/bin/wire`

## Anti-Patterns
- What NOT to do (with ❌ markers)

## Related Skills
- Links to complementary pw- skills
```

## Quality Checklist

Before deploying any skill:

- [ ] `name` field matches directory name exactly
- [ ] `SKILL.md` filename is ALL CAPS
- [ ] Description starts with "Use when..."
- [ ] All code examples use ProcessWire API (not Laravel/Symfony)
- [ ] PHP examples use `declare(strict_types=1)` and `namespace ProcessWire`
- [ ] CLI examples use `vendor/bin/wire` or `wire` alias
- [ ] No references to foreign frameworks unless explicitly needed
- [ ] `source` set to `processwire-boost`
- [ ] Total SKILL.md under 500 lines (split into references/ if larger)

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Laravel examples in PW skills | Use ProcessWire API equivalents |
| Description summarizes content | Use "Use when..." triggers |
| Generic PHP examples | Add `namespace ProcessWire;` and PW context |
| Missing anti-patterns section | Add ❌ markers for common mistakes |
| Referencing `opencode` paths | Use `resources/boost/skills/` paths |
