# Backup & Restore
description: Create, list, purge and restore database backups safely.
## When to use
- Manage backups or restore from site/assets/backups/database
## CLI
- Create: php vendor/bin/wire db:backup
- List: php vendor/bin/wire backup:list --limit 20
- Purge: php vendor/bin/wire backup:purge --days 30 --force
- Restore (dry-run): php vendor/bin/wire db:restore --file site/assets/backups/database/backup.sql --dry-run
## MCP
- Backup: {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"pw_system_backup","arguments":{}}}
- List: {"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"pw_system_backup_list","arguments":{}}}
- Purge: {"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"pw_system_backup_purge","arguments":{"days":30}}}
- Restore (guarded): set PW_MCP_ALLOW_RESTORE=1; {"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"pw_system_restore","arguments":{"file":"site/assets/backups/database/backup.sql"}}}
## Notes
- Restore only accepts readable .sql files under site/assets/backups/database
