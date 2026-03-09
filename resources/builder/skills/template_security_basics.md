# Template security basics
description: Sanitize and validate input in template files before using APIs.
## Steps
- Read input via $input
- Sanitize with $sanitizer (e.g., selectorValue, text, int)
- Confirm non-empty before using in selectors/queries
## Request
safe text search in body field
## Response
sanitized selector and result PageArray
## Example
```php
<?php
$text = wire()->sanitizer->selectorValue($input->get->text);
if($text){
  $items = wire()->pages->find("body%=$text");
}
```
