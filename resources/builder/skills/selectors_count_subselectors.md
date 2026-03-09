# Selectors count & subselectors
description: Use count selectors and subfield selectors like children.count or images.count.
## Blueprints
- .ai/blueprints/pw_core/Pages.json
## Steps
- Use children.count to test for children
- Use images.count to test for images
## Request
pages having at least one child
## Response
matching pages
## Example
```php
<?php
wire()->pages->find("children.count>0");
wire()->pages->find("images.count>0");
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
