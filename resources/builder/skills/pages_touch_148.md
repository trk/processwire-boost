# Pages::touch (3.0.148)
description: Update page timestamps with a specific date type (modified, created, published).
## Blueprints
## Steps
- Choose a date type
- Call touch with page and type
## Request
touch modified date for a page
## Response
updated page timestamp
## Example
```php
<?php
wire()->pages->touch($page, 'modified'); // 'created' or 'published' also valid
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
