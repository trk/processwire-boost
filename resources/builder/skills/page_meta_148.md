# Page::meta (3.0.148)
description: Store and retrieve page-specific metadata independent of fields.
## Blueprints
## Steps
- Set meta key/value on a Page
- Retrieve and update values as needed
## Request
increment view_count meta for current page
## Response
updated integer value
## Example
```php
<?php
$page->meta('view_count', (int)$page->meta('view_count') + 1);
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
