# ProcessWire Boost Guidelines

The ProcessWire Boost guidelines are specifically curated for this application to improve AI Agent context, efficiency, and safety.

## Foundational Context

This application is a ProcessWire CMS/CMF instance. You are evaluating code in a PHP environment mapped with following ProcessWire ecosystem packages and versions. You must strictly abide by these package versions.

{{ ROSTER }}

## Skills Activation

This project implements domain-specific skills (Playbooks). You MUST activate the relevant playbook skill whenever you work in that domain—do not wait until you're stuck to use them. Use the `view_file` tool or your native skill resolution engine to read the markdown.

{{ SKILLS_MENU }}

## Dual MCP Integration

This project uses dual MCP integration. You might have access to:
1. `laravel-boost`: Provides semantic search across Laravel/Ecosystem documentation (`search-docs`), codebase analysis tools, and database schema interrogation (`application-info`, `database-schema`). You can and should use this to lookup ProcessWire module docs!
2. `processwire-boost`: (If implemented natively) Built-in JSON-RPC server mapped over CLI to handle raw structural tasks (`pw_schema_read`, `pw_query` etc). 

> [!TIP]
> If available, ALWAYS run the `search-docs` tool querying ProcessWire or specific ProcessWire modules before guessing API structures.

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
- Stick to the existing layout; do not introduce Laravel/Symfony folder structures (`app/`, `routes/`) without explicit user permission.

More Examples:
```
# Enable a module (MCP)
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"pw_module_enable","arguments":{"name":"TextformatterAutoLinks"}}}

# List installed modules (MCP)
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"pw_module_list","arguments":{}}}

# Query pages by selector (MCP)
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"pw_query","arguments":{"selector":"template=post, limit=5"}}}

# Tail last N lines from errors log (MCP)
{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"pw_system_logs_tail_last","arguments":{"name":"errors","lines":150}}}

# Guarded DB restore (MCP) – set PW_MCP_ALLOW_RESTORE=1
{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"pw_system_restore","arguments":{"file":"site/assets/backups/database/backup.sql"}}}
```
