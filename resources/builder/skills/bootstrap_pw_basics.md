# Bootstrap ProcessWire
description: Include index.php from CLI or another PHP script to use the API.
## Steps
- Add namespace ProcessWire
- Include /path/to/index.php
- Use pages(), wire() or $wire->pages
## Request
list children of homepage from CLI
## Response
lines with child titles
## Example
```php
<?php namespace ProcessWire;
include("/path/to/index.php");
foreach(pages()->get('/')->children as $child){
  echo $child->title . PHP_EOL;
}
```
## Blueprints
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
