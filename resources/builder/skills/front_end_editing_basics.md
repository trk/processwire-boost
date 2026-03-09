# Front-end editing basics
description: Enable PageFrontEdit and make fields editable on the front-end.
## Steps
- Install PageFrontEdit (Core)
- Ensure permissions: page-edit and page-edit-front
- Choose editing method: automatic, API call, <edit> tags, attributes
## Request
make body field editable in place
## Response
double-click to edit body in front-end
## Example
```php
<?php
// Option B: API call
echo $page->edit('body');
```
## Blueprints
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
