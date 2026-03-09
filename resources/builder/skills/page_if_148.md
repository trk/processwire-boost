# Page::if (3.0.148)
description: Conditionally render a string or execute a closure when an expression matches.
## Blueprints
## Steps
- Provide a selector/expression
- Pass a closure or string to render when it matches
## Request
render UPPERCASE title if template=basic-page
## Response
transformed string output
## Example
```php
<?php
echo $page->if("template=basic-page", fn() => strtoupper($page->title));
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
