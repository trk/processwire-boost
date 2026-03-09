# Repeater basics
description: Use a Repeater field and iterate items in templates.
## Blueprints
- .ai/blueprints/pw_core/FieldtypeRepeater.json
- .ai/blueprints/pw_core/Pages.json
## Steps
- Add Repeater field and subfields in admin
- Add field to template and populate
- Iterate items in output
## Request
render building items with title and year
## Response
HTML list/blocks
## Example
```php
<?php
foreach($page->buildings as $b){
  echo "<h3>$b->title</h3>";
  echo "<p>Year: {$b->year_built}</p>";
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
