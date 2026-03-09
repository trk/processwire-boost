# URL segments basics
description: Enable URL segments and route requests in a template.
## Steps
- Enable URL segments on the template (Admin)
- Use $input->urlSegment1..3 or urlSegmentStr
- Throw 404 for unknown/extra segments
## Request
route /photos or /map under current page
## Response
different content by segment, 404 otherwise
## Example
```php
<?php
if(strlen($input->urlSegment2)) throw new Wire404Exception();
switch($input->urlSegment1) {
  case '':
    break;
  case 'photos':
    // render gallery
    break;
  case 'map':
    // render map
    break;
  default:
    throw new Wire404Exception();
}
```
## Blueprints
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
