# Module Enable/Disable/Upgrade
description: Enable, disable and upgrade modules via CLI or MCP tools.
## When to use
- Install/enable or disable/uninstall a module; run upgrade routine
## CLI
- Enable: php vendor/bin/wire module:enable --name TextformatterAutoLinks
- Disable: php vendor/bin/wire module:disable --name TextformatterAutoLinks --force
- Upgrade: php vendor/bin/wire module:upgrade --name ProcessWireUpgrade
- List JSON: php vendor/bin/wire module:list --site --json
## MCP
- Enable: {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"pw_module_enable","arguments":{"name":"TextformatterAutoLinks"}}}
- Disable: {"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"pw_module_disable","arguments":{"name":"TextformatterAutoLinks"}}}
- Upgrade: {"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"pw_module_upgrade","arguments":{"name":"ProcessWireUpgrade"}}}
