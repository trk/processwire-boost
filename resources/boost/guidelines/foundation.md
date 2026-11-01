# ProcessWire Boost Guidelines

ProcessWire Boost curates project-specific context, documentation pointers, and safety rules for AI agents operating in this ProcessWire CMS/CMF codebase.

## System Identity & Core Directives

- **Primary Identity:** You are operating within a ProcessWire 3 CMS/CMF ecosystem. Prioritize analytical depth, type safety, and ProcessWire-native architecture over generic framework abstractions.
- **Documentation First:** Consult local ProcessWire API documentation and the active schema before writing code. Never guess or hallucinate ProcessWire methods, field names, or template names.
- **Language Rule:** Write all repository-authored content strictly in English: code identifiers, comments, PHPDoc, documentation, validation messages, logs, tests, and default UI text. Wrap user-facing strings in translation calls (`$this->_('...')` inside `Wire`-derived classes or `__('...', __FILE__)` in templates).

## Context & Documentation Resolution Ladder

Resolve unknown APIs, schema structures, and module behavior in this order:

1. **Active Schema & Runtime Inspection (MCP & CLI):**
   - Run the `pw_schema_read` MCP tool (and `pw_module_list`) to inspect existing templates, fields (including nested repeater schemas), modules, roles, and permissions before writing selectors or migrations.
   - Use `pw_query` to test ProcessWire selectors non-destructively and `pw_system_get_logs` / `pw_system_logs_tail_last` to inspect runtime logs.
2. **Domain Skills (`.agents/skills/<skill-name>/SKILL.md`):**
   - Activate the matching playbook skill listed in **Skills Activation** below as soon as a task touches that domain.
3. **Built-in Core CLI Documentation (`php index.php docs`):**
   - `php index.php docs list-text` — List all classes with `API.md` documentation (`php index.php docs list-text 'Wire*'` to filter by wildcard).
   - `php index.php docs vars-text` — List all API variables and their corresponding classes.
   - `php index.php docs get-text <Class>` — Retrieve full `API.md` documentation for a class (e.g., `Pages`, `Page`, `Sanitizer`, `Process`, `Fieldtype`, `Inputfield`, `FileValidatorModule`).
   - `php index.php docs toc-text <Class>` / `php index.php docs chapter-text <Class> <num|title>` — Inspect specific chapters of large class docs.
   - `php index.php docs methods-text <Class>` — List all public methods of a class (`*` prefix indicates a hookable `___method`).
   - `php index.php docs method <Class> <method>` — Retrieve detailed parameter signature and description for a single method (JSON).
4. **Local `API.md` Files (`wire/**/API.md` & `site/modules/**/API.md`):**
   - Core classes (`wire/core/<Class>/API.md`) and modules (`wire/modules/<Type>/<Module>/API.md`) ship with developer-facing `API.md` files alongside their source code.

## Foundational Context

This application runs on the following ProcessWire ecosystem versions:

{{ ROSTER }}

## Skills Activation

Activate the relevant playbook skill immediately when your task enters its trigger domain. Use your file-reading tool (`view_file` / `read_file`) or native skill loader to read `.agents/skills/<skill-name>/SKILL.md`:

{{ SKILLS_MENU }}

## Application Structure & Architecture

- `wire/`: Core ProcessWire engine (**Read-only — NEVER MODIFY**).
- `site/config.php`: Environment configuration, database credentials, debug flags, and `$config->usePageClasses`.
- `site/init.php` & `site/ready.php`: Early boot hooks (`init.php`) and API-ready hooks (`ready.php`, URL hooks, page lifecycle hooks).
- `site/templates/`: Frontend template controllers (`{template}.php`).
- `site/classes/`: Custom Page classes (`[PascalCaseTemplate]Page.php`, requires `$config->usePageClasses = true`).
- `site/modules/`: Custom and third-party modules (`Module`, `Process`, `Fieldtype`, `Inputfield`, `Textformatter`, `FileValidatorModule`).
- `site/migrations/`: Timestamped schema migration files managed via `vendor/bin/wire make:migration` and `vendor/bin/wire migrate`.
- `site/seeders/`, `site/queue/`, `site/schedule/`: Database seeders (`*Seeder.php`), background jobs (`*Queue.php`), and cron tasks (`*Task.php`).
- Preserve ProcessWire's native directory structure; never introduce foreign framework directories (`app/`, `routes/`, `resources/views/`) unless explicitly requested.

## Verification Discipline

Verify selector queries, schema changes, and module logic before claiming completion:
- Use `pw_query` or `pw_execute` via MCP, or run `vendor/bin/wire <command> --json` from the terminal.
- Run Pest suites via `vendor/bin/wire test`.
- If a one-off verification script is required, execute it and delete any temporary scratch file before finishing.
