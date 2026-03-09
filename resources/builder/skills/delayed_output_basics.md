# Delayed output basics
description: Populate variables in template and render them in _main.php.
## Steps
- In template, assign placeholders like $headline, $bodycopy
- Include _main.php to output placeholders
## Request
use _main.php with headline and bodycopy
## Response
HTML document with populated regions
## Example
```php
<?php
$headline = $page->get("headline|title");
$bodycopy = $page->body;
include("./_main.php");
```
```php
<?php /* _main.php */ ?>
<!DOCTYPE html>
<html>
  <head><title><?php echo $headline; ?></title></head>
  <body>
    <h1><?php echo $headline; ?></h1>
    <?php echo $bodycopy; ?>
  </body>
</html>
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
