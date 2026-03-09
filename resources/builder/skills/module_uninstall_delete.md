# Uninstall and delete module
description: Uninstall a module and remove files safely.
## Blueprints
- .ai/blueprints/pw_core/Modules.json
## Steps
- Admin: Modules → Site → Module → Uninstall
- If writable, click Delete to remove files
- Otherwise delete via SFTP
## Request
remove a site module completely
## Response
module uninstalled and files removed
## Example
```text
Admin → Modules → Site → {Module} → Uninstall → Delete (if available)
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
