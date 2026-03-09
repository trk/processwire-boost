# Create custom permission
description: Create a new Permission object via API for assignment to roles.
## Blueprints
- .ai/blueprints/pw_core/Permissions.json
## Steps
- Add permission via $permissions->add('name')
- Set title/description and save
- Assign to roles from Admin (Access > Roles)
## Request
create permission can-publish-news
## Response
permission page created
## Example
```php
<?php
$p = wire()->permissions->add('can-publish-news');
$p->title = 'Can publish news';
$p->save();
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
