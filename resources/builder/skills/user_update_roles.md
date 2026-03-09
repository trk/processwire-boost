# User Update & Roles
description: List/update users and manage roles; delete non-superusers.
## When to use
- Change user email/password; assign or remove roles; list users as JSON
## CLI
- List: php vendor/bin/wire user:list --role editor --limit 20 --json
- Update: php vendor/bin/wire user:update --name editor --email editor@example.com --add-role author
- Delete: php vendor/bin/wire user:delete --id 123 --force
## Notes
- user:delete refuses superuser accounts; prefer --json for scripts
