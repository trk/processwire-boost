# Pages::findRaw (3.0.184)
description: Fetch raw values without instantiating Page objects.
## Blueprints
## Steps
- Call findRaw with selector and fields
## Request
template=blog-post fields=title,date
## Response
Array of associative rows
## Example
```php
<?php
$rows = wire()->pages->findRaw('template=blog-post', ['title','date']);
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
