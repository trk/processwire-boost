# ProcessWire Boost Guidelines

You are operating in a **ProcessWire 3 CMS/CMF** codebase (`{{ STACK_SUMMARY }}`). Prioritize analytical depth, strict type safety, and ProcessWire-native APIs over generic framework abstractions.

{{ PROJECT_PROFILE }}

## Core Directives & Documentation Ladder

- **Never Guess Schema or APIs:** Resolve unknown schemas, modules, and method signatures in this order:
  1. {{ RESOLUTION_STEP_1 }}
  2. **Task Playbooks (Skills):** Activate the matching `pw-*` skill as soon as a task enters its trigger domain.
  3. **Core CLI Docs:** Query `php index.php docs list-text`, `vars-text`, `get-text <Class>`, `methods-text <Class>`, or `method <Class> <method>`.
  4. **Local `API.md` Files:** Read `wire/core/<Class>/API.md`, `wire/modules/**/<Module>/API.md`, and `site/modules/<Module>/API.md` (indexed below).
- **Language & i18n:** Write all code identifiers, comments, PHPDoc, documentation, logs, tests, and default UI text in English. Wrap user-facing strings in `$this->_('...')` (inside `Wire` classes) or `__('...', __FILE__)` (in templates).
- **Application Structure:**
  - `wire/`: Core ProcessWire engine (**Read-only — NEVER MODIFY**).
  - `site/config.php`, `site/init.php`, `site/ready.php`: Configuration, early boot hooks, and API-ready / URL hooks.
  - `site/templates/` & `site/classes/`: Template controllers and `[PascalCaseTemplate]Page.php` custom page classes.
  - `site/modules/`: Custom and third-party modules.
  - `site/migrations/`, `site/seeders/`, `site/queue/`, `site/schedule/`: Managed CLI artifacts (`*Seeder.php`, `*Queue.php`, `*Task.php`). Never introduce foreign framework folders (`app/`, `routes/`, `resources/views/`).
- **Verification Gate:** Verify changes with `vendor/bin/wire <cmd> --json` (or `pw_query`), `vendor/bin/wire test`, and (when configured) `vendor/bin/wire test:e2e`. Delete any temporary scratch scripts before finishing.

{{ SKILLS_SECTION }}
