# Cache::renderFile (3.0.148)
description: Render a PHP file with full API variables and cache the output.
## Blueprints
## Steps
- Provide path, vars, cache key and expire
- Use returned HTML string
## Request
render and cache partial template
## Response
cached HTML output
## Example
```php
<?php
$html = wire()->cache->renderFile('/path/to/file.php', [], 'cache-key', 3600);
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
