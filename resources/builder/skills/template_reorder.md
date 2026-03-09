# Template Reorder & Update
description: Reorder template fields and update simple template settings (tags/flags).
## When to use
- Change field order on a template or adjust tags
## CLI
- Reorder: php vendor/bin/wire template:fields:reorder --template article --order "title,body,images"
- Update: php vendor/bin/wire template:update --name article --set "tag=blog"
- Inspect: php vendor/bin/wire template:info --name article ; php vendor/bin/wire template:list --tag blog --json
## Notes
- Listed fields move to the front in the given order; others keep relative order
