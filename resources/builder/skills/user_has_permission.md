# Check user permission
description: Check if the current user has a specific permission.
## Blueprints
- .ai/blueprints/pw_core/Users.json
- .ai/blueprints/pw_core/Permissions.json
## Steps
- Call $user->hasPermission('permission-name')
## Request
check if user can edit pages
## Response
boolean true/false
## Example
```php
<?php
if(wire()->user->hasPermission('page-edit')){
  echo 'Can edit';
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
