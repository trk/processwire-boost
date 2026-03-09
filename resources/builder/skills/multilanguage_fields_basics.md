# Multi-language fields
description: Read/write language-specific values and switch languages via API.
## Blueprints
- .ai/blueprints/pw_core/Languages.json
- .ai/blueprints/pw_core/Pages.json
## Steps
- Use current $user->language for default output
- Get/set value for a specific language with getLanguageValue/setLanguageValue
- Optionally switch $user->language
## Request
read German title and output
## Response
localized string
## Example
```php
<?php
$de = wire()->languages->get('de');
echo $page->getLanguageValue($de, 'title');
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
