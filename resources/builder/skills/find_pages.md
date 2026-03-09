# Find pages
description: Fetch a list of pages by selector.
## Blueprints
## Steps
- Prepare selector expression
- Fetch with wire('pages')->find()
- Iterate results and access fields
## Request
template=basic-page, limit=5
## Response
List of page id and title
## Example
```php
<?php
$items = wire('pages')->find("template=basic-page, limit=5");
foreach($items as $p){ echo $p->id.' '.$p->title; }
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
