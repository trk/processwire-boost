# Field dependencies (show-if / require-if)
description: Show or require a field based on conditions using selector-like expressions.
## Blueprints
- .ai/blueprints/pw_core/Inputfield.json
## Steps
- Set showIf on an Inputfield to control visibility
- Set required and requiredIf for conditional validation
## Request
show field when other_field=1 and require when name is not blank
## Response
conditional visibility and validation
## Example
```php
<?php
$f = $modules->get('InputfieldText');
$f->showIf = "other_field=1";
$f->required = 1;
$f->requiredIf = "name!=''";
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
