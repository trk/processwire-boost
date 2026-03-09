# Sanitize selector input
description: Sanitize user input before placing it into selectors.
## Blueprints
- .ai/blueprints/pw_core/Sanitizer.json
- .ai/blueprints/pw_core/Pages.json
## Steps
- Obtain input
- Sanitize with $sanitizer->selectorValue
- Build safe selector
## Request
search query from GET
## Response
matching pages safely
## Example
```php
<?php
$q = wire()->sanitizer->selectorValue($input->get->q);
$items = wire()->pages->find("title~={$q}, limit=20");
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
