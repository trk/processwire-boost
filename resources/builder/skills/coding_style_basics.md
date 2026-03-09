# Coding style basics
description: Follow ProcessWire PHP coding style highlights.
## Blueprints
## Steps
- Use tabs for indent; StudlyCaps classes; camelCase methods
- Braces on same line for classes/methods/control structures
- Use __() / $this->_() / _n() for translatable strings (one call per line)
## Request
apply PW coding style to module class
## Response
styled class skeleton
## Example
```php
<?php namespace ProcessWire;
class Sample extends WireData implements Module {
  public static function getModuleInfo() {
    return ['title'=>'Sample','summary'=>'Demo','version'=>1];
  }
  public function hi() {
    return sprintf($this->_('Hello %s'), wire()->user->name);
  }
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
