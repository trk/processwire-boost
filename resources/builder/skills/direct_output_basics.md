# Direct output basics
description: Output markup directly in template files and include reusable parts.
## Steps
- Write markup in template
- Include reusable parts with include()
## Request
render body with head/foot includes
## Response
HTML document output
## Example
```php
<?php
include("./_head.php");
echo $page->body;
include("./_foot.php");
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
