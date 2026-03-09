# Selectors basics
description: Build common selectors with AND/OR, sort and limit.
## Blueprints
- .ai/blueprints/pw_core/Pages.json
## Steps
- Combine selectors with commas (AND)
- Use OR on fields or values with pipes
- Apply sort and limit
## Request
template=basic-page with sorting and limit
## Response
list of pages
## Example
```php
<?php
$items = wire()->pages->find("template=basic-page, sort=-created, limit=10");
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
