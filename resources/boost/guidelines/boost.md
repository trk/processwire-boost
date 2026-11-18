# ProcessWire CLI & MCP Tools

Prefer `vendor/bin/wire` CLI commands and `processwire` MCP server tools over ad-hoc PHP scripts.

- **CLI (`php vendor/bin/wire <command>`):** Every command (except `boost:mcp`) supports `--json` (`{"ok": true, "data": ...}` / `{"ok": false, "error": ...}`). Mutation commands support `--dry-run`, and destructive commands require `--force` with `--json` or `--no-interaction`.
  - **Families:** `version`, `down`, `up`, `tinker`, `cache:clear`, `cache:wire:clear` | `page:*`, `field:*`, `template:*`, `module:*`, `user:*`, `role:*`, `permission:*` | `make:module|migration|seeder|queue|task|test` | `migrate*`, `db:seed|backup|restore`, `backup:*`, `queue:*`, `schedule:run`, `log:*`, `test`, `test:e2e` | `boost:install`, `boost:update`, `boost:validate`, `boost:add-skill`, `boost:mcp`.
- **MCP (`processwire`):** When connected, use native MCP tools directly (`pw_schema_read`, `pw_query`, `pw_execute`, `pw_schema_*`, `pw_module_*`, `pw_access_*`, `pw_system_*`). Run `pw_schema_read` before authoring selectors, templates, or migrations.
