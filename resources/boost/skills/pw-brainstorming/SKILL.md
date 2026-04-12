---
name: pw-brainstorming
description: "Use before designing ProcessWire modules, templates, field schemas, or hooks to resolve ambiguity and validate architectural decisions prior to implementation."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Brainstorming

Help turn raw ideas into **clear, validated designs and specifications** in ProcessWire through structured dialogue.

<HARD-GATE>
Do NOT invoke any implementation skill, write any code, scaffold any project, or run any migrations until you have presented a design and the user has approved it. This applies to EVERY project regardless of perceived simplicity.
</HARD-GATE>

## The Process

You must complete these items in order. Do NOT skip steps or combine them.

### 1️⃣ Explore Current ProcessWire Context (Mandatory)
Before asking any questions, explore the existing state:
- Run `pw_schema_read` via MCP to understand existing templates and fields (never hallucinate schema).
- Review `site/modules/` for installed custom modules.
- If the request describes multiple independent subsystems, decompose the project first. Brainstorm the first sub-project only.
**Do not design yet.**

### 2️⃣ Understanding the Idea (One Question at a Time)
Your goal is **shared clarity**, not speed.
- Ask **one question per message** (prefer multiple-choice).
- Understand: templates/fields needed, page tree structure, access control (RBAC), and explicit non-goals.

### 3️⃣ ProcessWire Constraints Check
Identify hidden complexities:
- **Schema Impact:** Brand new fields vs. modifying existing?
- **Data Relationships:** Page references vs. Repeaters vs. Custom Fieldtypes?
- **Module Dependencies:** Does this require HTMX, ProFields, or specific Textformatters?
If the user is unsure, propose reasonable ProcessWire defaults, but mark them explicitly as assumptions.

### 4️⃣ Understanding Lock (Soft Gate)
Before proposing any design, summarize what you know:
1. What is being built / Affected ProcessWire components
2. Key constraints & Explicit non-goals
3. List all **Assumptions**
Then explicitly ask: *"Does this accurately reflect your intent?"*

### 5️⃣ Propose Approaches & Present Design
Once confirmed, propose 2-3 technical approaches (e.g., Hook-centric vs. Process Module vs. Custom Page Class). Lead with your recommended option.
Then present the detailed design in sections (200-300 words). Ask *"Does this look right so far?"* after each section.

### 6️⃣ Write Design Doc & Spec Self-Review
Write the approved design to a tracking markdown file (e.g. `docs/specs/YYYY-MM-DD-<topic>.md`).
Before asking the user to review it, **Review yourself inline**:
- **Placeholder scan:** Any "TODO" or vague requirements? Fix them inline.
- **Internal consistency:** Does the architecture match ProcessWire's Data Model limits?
- **Ambiguity check:** Fix anything interpreted two ways.

### 7️⃣ User Review Gate
Ask the user to review the written spec. Proceed only once approved.

### 8️⃣ Transition to Implementation
- **Terminal State:** Invoke the `writing-plans` skill to create the actual implementation and migration plan.
- **Do NOT** write implementation code or migrations while brainstorming.

---

## Anti-Patterns

- ❌ **"This is too simple to need a design"**: Every project needs confirmation. "Simple" hooks are where unexamined assumptions cause the most wasted work.
- ❌ **Multiple questions at once**: Never overwhelm the user with a wall of 5 questions.
- ❌ **Writing migrations during brainstorming**: Implementation belongs strictly to `writing-plans` (or `pw-migrations`). Brainstorming creates the spec.
- ❌ **Assuming schema structures**: Always run `pw_schema_read` first to base designs on reality, not guesswork.
- ❌ **Multi-Agent Roleplay**: Do not hallucinate review personas (Skeptics, Integrators); rigorously Self-Review your Spec instead.

## Related Skills
- `pw-writing-skills`: Use for formatting AI skill files.
- `writing-plans`: Triggered immediately AFTER brainstorming completes.
