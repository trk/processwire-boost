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

## Dual MCP Integration

This project uses dual MCP integration. You might have access to:

1. `laravel-boost`: Provides semantic search across ecosystem documentation (`search-docs`), codebase analysis tools, and database schema interrogation (`application-info`, `database-schema`). You can and should use this to lookup ProcessWire module docs!
2. `processwire-boost`: (If implemented natively) Built-in JSON-RPC server mapped over CLI to handle raw structural tasks (`pw_schema_read`, `pw_query` etc).

> [!TIP]
> If available, ALWAYS run the `search-docs` tool querying ProcessWire or specific ProcessWire modules before guessing API structures.

## Documentation Resources

- **Core API Reference:** The `.agents/docs` directory contains the complete generated ProcessWire Core API documentation. ALWAYS use the `grep_search` and `view_file` tools to search this directory before hallucinating ProcessWire methods or classes.

## Conventions & Rules

- **Follow surrounding architecture**: Always look at sibling files in `site/templates/` or `site/modules/` before creating a new architectural pattern.
- **CamelCase variables**: Use descriptive variables (e.g., `$isPagePublished`, not `$p_pub`).
- **Reuse**: Look for existing UI components or helper functions before writing custom HTML/PHP.

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
