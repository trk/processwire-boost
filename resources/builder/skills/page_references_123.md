# Page::references (3.0.123)
description: Get pages that reference the current page via Page reference fields.
## Blueprints
## Steps
- Call $page->references() to get referencing pages
- Iterate and use as needed
## Request
list referencing page IDs
## Response
Page IDs list
## Example
```php
<?php
$refs = $page->references();
echo implode(',', $refs->explode('id'));
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
