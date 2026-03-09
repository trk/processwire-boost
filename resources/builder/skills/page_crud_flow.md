# Page CRUD Flow
description: Manage page creation, updates, moves and status changes via CLI/MCP.
## When to use
- Create, update, move, publish/unpublish, trash or restore pages
- Automate short series of page operations
## CLI
- Create: php vendor/bin/wire page:create --parent /blog --template post --title "My Post"
- Update: php vendor/bin/wire page:update --id 123 --set "title=New,headline=Short" --status publish
- Move: php vendor/bin/wire page:move --path /blog/my-post --parent /archive
- Publish/Unpublish: php vendor/bin/wire page:publish --path /blog/my-post ; php vendor/bin/wire page:unpublish --id 123
- Trash/Restore: php vendor/bin/wire page:trash --id 123 --force ; php vendor/bin/wire page:restore --id 123
- Query JSON: php vendor/bin/wire page:find "template=post, limit=5" --json
## MCP
- {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"pw_query","arguments":{"selector":"template=post, limit=5"}}}
## Notes
- Prefer --json for programmatic use; add include=all when needed
