# ProcessWire CLI & MCP Tools

Prefer native `vendor/bin/wire` CLI commands and ProcessWire MCP server tools over ad-hoc PHP scripts when inspecting state or administering the CMS.

## CLI Core Commands (`vendor/bin/wire`)

Every core console command supports `--json` for structured machine-readable output (`{"ok": true, "data": ...}` or `{"ok": false, "error": {"code": "...", "message": "..."}}`). Many mutation commands also support `--dry-run` (safe preview) and `--force` (required when invoking destructive commands with `--json`).

- **System & Config:** `version`, `down`, `up`, `tinker`, `cache:clear`, `cache:wire:clear`
- **Pages:** `page:list`, `page:find`, `page:create`, `page:update`, `page:move`, `page:publish`, `page:unpublish`, `page:trash`, `page:restore`
- **Fields:** `field:list`, `field:info`, `field:update`, `field:rename`, `field:attach`, `field:detach`, `field:delete`
- **Templates:** `template:list`, `template:info`, `template:update`, `template:rename`, `template:delete`, `template:fields:reorder`
- **Modules:** `module:list`, `module:install`, `module:uninstall`, `module:refresh`, `module:upgrade`, `make:module`
- **Users & RBAC:** `user:list`, `user:create`, `user:update`, `user:delete`, `role:list`, `role:create`, `role:grant`, `role:revoke`, `permission:list`, `permission:create`, `permission:delete`
- **Migrations:** `make:migration`, `migrate`, `migrate:status`, `migrate:rollback`, `migrate:reset`, `migrate:refresh`, `migrate:fresh`, `migrate:install`
- **Seeders & Backups:** `make:seeder`, `db:seed`, `db:backup`, `db:restore`, `backup:list`, `backup:purge`
- **Queues & Scheduling:** `make:queue`, `queue:table`, `queue:work`, `queue:failed`, `queue:retry`, `queue:clear`, `make:task`, `schedule:run`
- **Logs & Testing:** `log:list`, `log:read`, `log:tail`, `log:clear`, `log:clear-all`, `make:test`, `test`
- **Boost:** `boost:install`, `boost:update`, `boost:add-skill`, `boost:mcp`

## ProcessWire MCP Server (`processwire`)

When the `processwire` MCP server is connected, use its 28 JSON-RPC tools directly:

- **Inspection & Execution:**
  - `pw_schema_read`: Inspect all templates, fields (including nested repeater/matrix/combo schemas via `FieldSchemaExtender`), roles, and permissions.
  - `pw_query`: Run a ProcessWire selector (`template=basic-page, limit=10`) and receive JSON page results.
  - `pw_execute`: Execute a PHP snippet inside the booted ProcessWire runtime (when `PW_MCP_ALLOW_EXECUTE=1` is set).
- **Schema Mutation:** `pw_schema_field_create`, `pw_schema_template_create`
- **Modules:** `pw_module_list`, `pw_module_info`, `pw_module_install`, `pw_module_uninstall`, `pw_module_refresh`, `pw_module_upgrade`
- **Access Control (RBAC):** `pw_access_user_list`, `pw_access_user_create`, `pw_access_user_update`, `pw_access_user_delete`, `pw_access_role_create`, `pw_access_role_grant`, `pw_access_role_revoke`, `pw_permission_delete`
- **Logs, Cache & Backups:** `pw_system_get_logs`, `pw_system_logs_tail_last`, `pw_system_logs_clear`, `pw_system_cache_clear`, `pw_system_cache_wire_clear`, `pw_system_backup`, `pw_system_backup_list`, `pw_system_backup_purge`, `pw_system_restore`

> [!IMPORTANT]
> Run `pw_schema_read` before writing selectors, templates, or migrations to verify that target templates and fields exist in this deployment.
