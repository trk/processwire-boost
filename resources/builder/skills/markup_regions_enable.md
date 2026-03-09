# Markup Regions enable
description: Enable markup regions and set a main template file.
## Steps
- In /site/config.php, set useMarkupRegions and appendTemplateFile
- Define regions in _main.php and populate from templates
## Request
enable regions and use _main.php
## Response
regions active and ready to populate
## Example
```php
<?php
$config->useMarkupRegions = true;
$config->appendTemplateFile = '_main.php';
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
