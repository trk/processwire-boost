# Check permission with page context
description: Check if the current user has a permission in the context of a given page.
## Blueprints
- .ai/blueprints/pw_core/Users.json
- .ai/blueprints/pw_core/Permissions.json
- .ai/blueprints/pw_core/Pages.json
## Steps
- Call $user->hasPermission('permission-name', $page)
## Request
check page-edit permission for this page
## Response
boolean true/false
## Example
```php
<?php
if(wire()->user->hasPermission('page-edit', $page)){
  echo 'Can edit this page';
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
