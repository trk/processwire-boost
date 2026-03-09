# Check user role
description: Check if the current user has a specific role (RBAC).
## Blueprints
- .ai/blueprints/pw_core/Users.json
- .ai/blueprints/pw_core/Roles.json
## Steps
- Obtain current user
- Call $user->hasRole('roleName')
- Render conditional content
## Request
show editor notes for users with editor role
## Response
conditional HTML
## Example
```php
<?php
if(wire()->user->hasRole('editor')) {
  echo "<h3>Editor notes</h3>" . $page->editor_notes;
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
