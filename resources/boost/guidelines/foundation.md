# ProcessWire Boost Guidelines

The ProcessWire Boost guidelines are specifically curated for this application to improve AI Agent context, efficiency, and safety.

## System Identity & Core Directives

- **Primary AI Identity:** You are operating within a ProcessWire ecosystem. Prioritize analytical depth and technical excellence in all interactions.
- **CRITICAL DOCUMENTATION RULE:** Before writing any processwire code, you MUST consult the local API documentation. You MUST NOT hallucinate API methods.
- **LANGUAGE RULE:** ALL code, documentation, and file contents MUST strictly be written in English. Ensure inner code strings are always in English and wrapped in translation functions.

## Context Resolution Landscape

When you need more context, always check these primary locations:

- **Skills (Task Playbooks):** `.agents/skills/`
- **Blueprints:** `.agents/blueprints/*.json` (class/method summaries)
- If your client supports MCP, use the ProcessWire MCP server tools to query data.

## Foundational Context

This application is a ProcessWire CMS/CMF instance. You are evaluating code in a PHP environment mapped with following ProcessWire ecosystem packages and versions. You must strictly abide by these package versions.

{{ ROSTER }}

## Skills Activation

This project implements domain-specific skills (Playbooks). You MUST activate the relevant playbook skill whenever you work in that domain—do not wait until you're stuck to use them. Use the `view_file` tool or your native skill resolution engine to read the markdown.

{{ SKILLS_MENU }}

## ProcessWire MCP Integration

This project exposes an MCP server for intelligent context discovery. You might have access to:

1. `processwire`: Built-in JSON-RPC server handling structural tasks (`pw_schema_read`, `pw_query`, `pw_system_get_logs`, etc). You can and should use this to safely read state and debug code.

> [!TIP]
> ALWAYS run `pw_schema_read` before guessing template names, fields, or database designs. Do not guess API structures.

## Documentation Resources

ProcessWire provides a complete, multi-layered documentation discovery ladder. ALWAYS consult local documentation before guessing API methods:

1. **Built-in Core CLI Documentation (`php index.php docs`):**
   ProcessWire core includes a built-in CLI documentation reader that accesses `API.md` definitions natively. Use `-text` suffix for human/agent-readable plain text:
   - `php index.php docs list-text` — List all classes with API documentation.
   - `php index.php docs get-text <class>` — Retrieve complete API.md documentation for a class (e.g., `Pages`, `Page`, `Sanitizer`, `Process`, `Fieldtype`, `FileValidatorModule`).
   - `php index.php docs methods-text <class>` — List all public methods of a class (`*` prefix = hookable method).
   - `php index.php docs method <class> <method>` — Details and parameter signature for a single method (JSON).
   - `php index.php docs vars-text` — List all API variables and their corresponding classes.
2. **Local Core Markdown Documentation (`wire/**/API.md`):**
   Over 115 core classes and first-party modules ship with dedicated `API.md` files:
   - Core API: `wire/core/Pages/API.md`, `wire/core/Page/API.md`, `wire/core/Sanitizer/API.md`, `wire/core/WireCache/API.md`, `wire/core/WireDatabase/API.md`, `wire/core/WireHooks/API.md`
   - Modules: `wire/core/Module/API.md`, `wire/core/Module/Process/API.md`, `wire/core/Module/FileValidatorModule/API.md`, `wire/core/Fieldtype/API.md`, `wire/core/Inputfield/API.md`
   - Fieldtypes: `wire/modules/Fieldtype/<Type>/API.md` (e.g. `FieldtypeText`, `FieldtypeRepeater`, `FieldtypePage`)
   - Inputfields: `wire/modules/Inputfield/<Type>/API.md` (e.g. `InputfieldForm`, `InputfieldText`)
   - Markup: `wire/modules/Markup/MarkupAdminDataTable/API.md`
3. **Generated Boost Documentation (`.agents/docs/`):**
   When generated via `wire boost:build:docs`, the `.agents/docs/` directory contains structured markdown summaries.
4. **ProcessWire MCP Server:**
   Use `pw_schema_read` to inspect active templates, fields, and flags, and `pw_query` to test selectors non-destructively.

## Conventions & Rules

- **Indentation:** **Tabs, not spaces** (strictly match ProcessWire core conventions).
- **Follow surrounding architecture**: Always look at sibling files in `site/templates/` or `site/modules/` before creating a new architectural pattern.
- **CamelCase variables**: Use descriptive variables (e.g., `$isPagePublished`, not `$p_pub`).
- **Reuse**: Look for existing UI components or helper functions before writing custom HTML/PHP.
- **Scoped API access**: In Wire-derived classes, use `$this->wire()->pages`. For multiple calls in loops, assign a locally scoped variable: `$pages = $this->wire()->pages;`. Never use `$this->pages` (slow `__get()` magic methods and property collisions).

## Verification Scripts

Do not guess if a `$pages->find()` query or PHP array logic works. If your environment has Tinker or `pw_execute` available, verify it.
Otherwise, create a scratch file in the root, execute it with `php scratch.php` to verify output, then clean it up upon completion.

## Application Structure & Architecture

- `wire/`: Core ProcessWire engine (Read-only, NEVER MODIFY).
- `site/`: Application-specific workspace.
- `site/templates/`: Front-end outputs, controller logic.
- `site/modules/`: Custom third-party or local module extensions.
- `site/migrations/`: Schema migration files (timestamped PHP, managed via `make:migration` / `migrate` CLI).
- Stick to the existing layout; do not introduce foreign framework folder structures (`app/`, `routes/`) without explicit user permission.

## Align With Upstream ProcessWire (AGENTS.md / CLAUDE.md)
ProcessWire core ships its own `AGENTS.md` and `CLAUDE.md` files with conventions for:
- API orientation and common patterns (`$pages->findOne()`, `$pages->findMany()`, `$page->setAndSave()`)
- API.md documentation structure for Fieldtypes
- `[Type]Field.php` settings typing pattern for IDE autocompletion
- `$database` being `WireDatabasePDO` (PDO-like wrapper) and accessed as a property (`$this->wire()->database`)
- Tabs indentation (not spaces)
- Multi-instance safety (`$this->wire()->apiVar` vs global variables)

Boost guidelines are designed to be 100% compatible with upstream ProcessWire conventions across all AI agents.
