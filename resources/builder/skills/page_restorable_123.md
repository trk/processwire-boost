# Page::restorable (3.0.123)
description: Check if a trashed page can be restored to its original location.
## Blueprints
## Steps
- Call $page->restorable() for a page in trash
- Render UI option when true
## Request
check if page is restorable
## Response
boolean true/false
## Example
```php
<?php
if($page->restorable()){
  echo 'Restorable';
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
