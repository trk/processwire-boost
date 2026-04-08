# ProcessWire CLI & MCP Tools

ProcessWire Boost exposes an MCP server and/or CLI proxy for automating CMS administration directly from the chat interface without writing scratch scripts.

## CLI Core Commands (`vendor/bin/wire`)

When performing administrative actions, prefer running these terminal commands over writing PHP scripts:
- **Pages:** `page:create|update|move|publish|unpublish|restore|trash`
- **Fields/Templates:** `field:update|rename|attach|detach`, `template:update|rename`, `template:fields:reorder`
- **Modules:** `module:list|install|uninstall|refresh`
- **Users/RBAC:** `user:list|update|delete`, `role:grant|revoke`, `permission:delete`
- **Migrations:** `make:migration`, `migrate`, `migrate:status|rollback|reset|refresh|fresh|install`
- **Cache/Logs/Backup:** `cache:wire:clear`, `logs:tail|clear`, `db:backup|restore`, `backup:list|purge`

## ProcessWire MCP Server (JSON-RPC)

If the environment exposes processwire tools as JSON-RPC MCP calls, utilize them to read states non-destructively:
- `pw_query` / `database-query`: Execute a ProcessWire selector or SQL query against the system and return pure JSON objects. Excellent for exploring data.
- `pw_schema_read` / `database-schema`: Reverse-engineer the active ProcessWire templates, fields, and tables to understand the data model.
- `pw_system_get_logs` / `read-log-entries`: Access system logs (like `errors.txt` or `exceptions.txt`) in `/site/assets/logs/` for direct debugging evidence.

> [!IMPORTANT]
> If you are asked to generate complex structures, run `pw_schema_read` first to ensure the templates and fields you intend to query actually exist in this specific deployment!
