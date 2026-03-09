# Pages::has(selector) (3.0.164)
description: Check existence efficiently; returns ID of first matching page.
## Blueprints
## Steps
- Call has(selector) to avoid loading pages
## Request
template=basic-page, title%=Design
## Response
Integer ID or 0
## Example
```php
<?php
$id = wire()->pages->has('template=basic-page, title%=Design');
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
