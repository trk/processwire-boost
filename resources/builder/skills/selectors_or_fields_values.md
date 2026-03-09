# Selectors OR (fields/values)
description: Match one field or another, or one value or another.
## Blueprints
- .ai/blueprints/pw_core/Pages.json
## Steps
- Use field pipes: title|name
- Use value pipes: template=a|b
## Request
match by title OR name equals 'products'
## Response
matching pages
## Example
```php
<?php
wire()->pages->find("title|name=products");
wire()->pages->find("template=basic-page|article");
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
