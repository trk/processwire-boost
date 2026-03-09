# ProcessWire Boost Guidelines

The ProcessWire Boost guidelines are specifically curated for this application. These guidelines should be followed closely to enhance the user's satisfaction building ProcessWire applications.

## Foundational Context

This application is a ProcessWire application and its main ProcessWire ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

{{ ROSTER }}

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

{{ SKILLS_MENU }}

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files in `site/templates/` or `site/modules/` for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `$isPagePublished`, not `$p_pub`.
- Check for existing components or fields to reuse before writing a new one.

## Verification Scripts

- Use the `wire tinker` tool to verify API calls and logic before committing changes.
- Ensure selectors are efficient and use appropriate limits.

## Application Structure & Architecture

- `wire/`: Core ProcessWire files (Read-only, do not modify).
- `site/`: Application-specific files.
- `site/templates/`: Front-end template files and logic.
- `site/modules/`: Custom and third-party modules.
- Stick to existing directory structure; don't create new base folders without approval.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Command-Line Shortcuts (CLI)

- Prefer vendor/bin/wire for admin tasks:
  - Pages: `page:create|update|move|publish|unpublish|restore|trash`
  - Fields/Templates: `field:update|rename|attach|detach`, `template:update|rename`, `template:fields:reorder`
  - Modules: `module:list|install|uninstall|refresh`
  - Users/RBAC: `user:list|update|delete`, `role:grant|revoke`, `permission:delete`
  - Cache/Logs/Backup: `cache:wire:clear`, `logs:tail|clear`, `db:backup|restore`, `backup:list|purge`

## MCP Tools (Agent Integration)

- Call ProcessWire operations over JSON‑RPC:
  - Schema: `pw_schema_read`, `pw_schema_field_create`, `pw_schema_template_create`
  - Modules: `pw_module_list|info|install|uninstall|refresh`
  - Access: `pw_access_user_create|update|delete|list`, `pw_access_role_create|grant|revoke`, `pw_permission_delete`
  - System: `pw_system_get_logs`, `pw_system_cache_clear`, `pw_system_cache_wire_clear`, `pw_system_backup|backup_list|backup_purge`
  - Guarded: `pw_execute` (enabled when PW_MCP_ALLOW_EXECUTE=1)

Example (tools/call):
```
{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"pw_schema_read","arguments":{}}}
```

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
