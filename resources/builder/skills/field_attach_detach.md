# Field Attach & Detach
description: Attach/detach fields to templates and update basic field settings.
## When to use
- Add/remove a field on a template
- Quickly adjust field label/tags
## CLI
- Attach: php vendor/bin/wire field:attach --field tags --template article --position after=title
- Detach: php vendor/bin/wire field:detach --field tags --template article --force
- Update: php vendor/bin/wire field:update --name body --set "label=Body,tags=content"
- Inspect: php vendor/bin/wire field:info --name body ; php vendor/bin/wire field:list --type FieldtypeText --json
## Notes
- Positions: first | last | after=FIELD | before=FIELD ; use --dry-run to preview
