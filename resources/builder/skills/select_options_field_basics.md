# Select Options field
description: Use FieldtypeOptions to store one or more selected options.
## Blueprints
- .ai/blueprints/pw_core/Fields.json
## Steps
- Define options in field settings
- Assign to template
- Output selected options
## Request
display selected option titles
## Response
comma-separated titles
## Example
```php
<?php
echo implode(', ', $page->options->explode('title'));
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
