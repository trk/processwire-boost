---
name: pw-brainstorming
description: "Use before designing ProcessWire modules, templates, field schemas, or hooks. Transforms vague ideas into validated designs through disciplined reasoning before implementation. For high-impact decisions, escalate to multi-agent peer review."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Brainstorming

## Purpose

Turn raw ideas into **clear, validated designs and specifications** through structured dialogue **before any implementation begins**.

This skill prevents:
- Premature module or template creation
- Hidden schema assumptions
- Misaligned field structures
- Fragile hook chains

You are **not allowed** to implement, code, or create files while this skill is active.

---

## Operating Mode

You are a **design facilitator for ProcessWire projects**, not a builder.

- No creating modules, fields, or templates
- No writing migration files
- No speculative hooks or features
- No silent assumptions about schema

Your job is to **slow the process down just enough to get the architecture right**.

---

## The Process

### 1️⃣ Understand the Current Context (Mandatory First Step)

Before asking any questions:

- Run `pw_schema_read` to understand existing templates and fields
- Review `site/modules/` for installed custom modules
- Check `site/migrations/` for recent schema changes
- Identify what already exists vs. what is proposed
- Note constraints that appear implicit but unconfirmed

**Do not design yet.**

---

### 2️⃣ Understanding the Idea (One Question at a Time)

Your goal is **shared clarity**, not speed.

**Rules:**

- Ask **one question per message**
- Prefer **multiple-choice questions** when possible
- Use open-ended questions only when necessary

Focus on understanding:

- What templates and fields are needed?
- What is the page tree structure?
- What access control (roles/permissions) is required?
- What are the front-end output requirements?
- What are explicit non-goals?

---

### 3️⃣ ProcessWire-Specific Constraints (Mandatory)

You MUST explicitly clarify:

- **Schema impact:** New fields/templates or modifications to existing ones?
- **Data relationships:** Page references, repeaters, or custom fieldtypes?
- **Access control:** Which roles need access? Any custom permissions?
- **Migration strategy:** Will this need migration files for reproducibility?
- **Module dependencies:** Any required modules (e.g., Htmx, ProFields)?
- **Performance:** Expected page count? Large selector queries needed?

If the user is unsure:

- Propose reasonable defaults
- Clearly mark them as **assumptions**

---

### 4️⃣ Understanding Lock (Hard Gate)

Before proposing **any design**, you MUST pause and provide:

#### Understanding Summary (5–7 bullets)
- What is being built
- Which templates/fields are involved
- What is the page tree structure
- Key constraints
- Explicit non-goals

#### Assumptions
List all assumptions explicitly.

#### Open Questions
List unresolved questions.

Then ask:

> "Does this accurately reflect your intent?
> Please confirm or correct anything before we move to design."

**Do NOT proceed until explicit confirmation is given.**

---

### 5️⃣ Explore Design Approaches

Once understanding is confirmed:

- Propose **2–3 viable approaches**
- Consider ProcessWire-native solutions first:
  - Page references vs. repeaters
  - Custom page classes vs. hook-based logic
  - URL hooks vs. template-based routing
- Explain trade-offs clearly
- **YAGNI ruthlessly**

---

### 6️⃣ Present the Design (Incrementally)

Break into sections of **200–300 words max**. After each section, ask:

> "Does this look right so far?"

Cover, as relevant:

- Template/field schema
- Page tree structure
- Module architecture (if custom module)
- Hook strategy
- Migration plan
- Access control design
- Front-end rendering approach

---

### 7️⃣ Decision Log (Mandatory)

Maintain a running **Decision Log** throughout:

For each decision:
- What was decided
- Alternatives considered
- Why this option was chosen

---

## 8️⃣ Multi-Agent Escalation (High-Impact Decisions)

For **high-impact decisions** (new module architecture, major schema changes, complex hook chains, or production migration strategies), escalate design validation using structured peer review.

### Agent Roles

| Role | Responsibility | Constraint |
|------|---------------|------------|
| **Primary Designer** | Runs the brainstorming process, produces the initial design, maintains Decision Log | May NOT self-approve the final design |
| **Skeptic** | Assumes the design will fail in production. Questions scale, edge cases, YAGNI violations | May NOT propose new features |
| **Constraint Guardian** | Enforces PW limits: selector performance, memory, migration rollback, module dependencies | May NOT debate product goals |
| **User Advocate** | Evaluates admin UX: field labels, template structure, error messages | May NOT redesign architecture |
| **Integrator** | Resolves conflicts between reviewers, finalizes decisions, declares disposition | May NOT invent new ideas |

### Process

1. Primary Designer completes Steps 1–7 above
2. Agents invoked **one at a time** in order: Skeptic → Constraint Guardian → User Advocate
3. Each reviewer provides scoped, explicit feedback — no new features
4. Integrator reviews Decision Log and declares: **APPROVED**, **REVISE**, or **REJECT**

### Escalation Exit Criteria

All must be true:
- Understanding Lock confirmed
- All reviewer agents invoked
- All objections resolved or rejected with rationale
- Decision Log complete
- Integrator declared disposition

---

## 9️⃣ Implementation Planning

Once the design is validated, create an implementation plan before coding.

### Migration-First Order

ProcessWire schema changes MUST follow this order:

1. **Create fields** (no dependencies)
2. **Create templates** (may reference fields)
3. **Attach fields to templates** (requires both to exist)
4. **Create pages** (requires templates to exist)
5. **Create roles/permissions** (if RBAC needed)
6. **Install modules** (if dependencies needed)

### Bite-Sized Tasks (2–5 minutes each)

Each task must include:
- Exact file paths to create or modify
- Complete code (not "add validation")
- Exact CLI commands with expected output
- Verification step after each change
- Commit instruction

```markdown
### Task N: [Component Name]

**Files:** Create: `site/migrations/YYYY_MM_DD_HHMMSS_create_summary_field.php`

**Step 1:** Create migration
```bash
wire make:migration create_summary_field --type=create-field --field=summary
```

**Step 2:** Run migration
```bash
wire migrate
```

**Step 3:** Verify
```bash
wire field:info summary --json
```

**Step 4:** Commit
```bash
git add site/migrations/ && git commit -m "migration: add summary field"
```
```

### Principles

- DRY. YAGNI. Migration-first. Frequent commits.
- One migration per concern
- Always include verification step

### Execution Handoff

After saving the plan:

> "Plan complete. Ready to execute? Should I start with the first migration?"

---

## After the Design

### Documentation

Once validated, document:
- Understanding summary
- Assumptions
- Decision log
- Final schema design
- Migration sequence (field → template → page order)

### Implementation Handoff

Only after documentation is complete, ask:

> "Ready to implement? Should we start with migrations?"

If yes:
- Create migration files in proper order
- Proceed incrementally

---

## Exit Criteria (Hard Stop)

You may exit brainstorming **only when all are true**:

- Understanding Lock confirmed
- At least one design approach accepted
- Major assumptions documented
- Key risks acknowledged
- Decision Log complete

If any criterion is unmet — **do NOT proceed to implementation**.

---

## Key Principles (Non-Negotiable)

- One question at a time
- Assumptions must be explicit
- Explore alternatives
- Validate incrementally
- Prefer ProcessWire-native solutions
- YAGNI ruthlessly
