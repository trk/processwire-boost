# Code i18n basics
description: Mark strings for translation and use placeholders and plurals.
## Blueprints
## Steps
- Use __() outside classes, $this->_() inside classes
- Use printf/sprintf placeholders for variables
- Use _n()/$this->_n() for plural forms
## Request
output translated message with count
## Response
localized singular/plural text
## Example
```php
<?php
printf(__('Created %d pages.'), $count);
// Plural-aware
printf(_n('Created %d page.', 'Created %d pages.', $count), $count);
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
