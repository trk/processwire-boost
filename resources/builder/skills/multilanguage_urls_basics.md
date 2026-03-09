# Multi-language URLs
description: Enable per-language page names/URLs and read per-language URL.
## Blueprints
- .ai/blueprints/pw_core/Pages.json
## Steps
- Install LanguageSupportPageNames
- Set per-language names in page editor
- Use $page->url($language) to get URL
## Request
get German URL for current page
## Response
localized URL
## Example
```php
<?php
$de = wire()->languages->get('de');
echo $page->url($de);
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
