# Role Grant & Revoke
description: Grant or revoke permissions on roles; manage custom permissions.
## When to use
- Adjust role permissions and inspect current RBAC
## CLI
- Grant: php vendor/bin/wire role:grant --role editor --permission page-edit
- Revoke: php vendor/bin/wire role:revoke --role editor --permission page-edit
- Delete custom permission: php vendor/bin/wire permission:delete --name can-publish-news --force
- List: php vendor/bin/wire role:list --json ; php vendor/bin/wire permission:list --json
## Notes
- Core permissions can be assigned/revoked on roles but not removed from core
