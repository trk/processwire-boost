# Create simple module
description: Implement a basic module class and register module info.
## Blueprints
- .ai/blueprints/pw_core/Modules.json
## Steps
- Create class implementing Module, extend WireData
- Add static getModuleInfo with title, summary, version
- Save as /site/modules/Foo/Foo.module.php
## Request
create Foo module that returns “Hi”
## Response
string like “Hi there guest”
## Example
```php
<?php namespace ProcessWire;
class Foo extends WireData implements Module {
  public static function getModuleInfo() {
    return ['title'=>'Foo test module','summary'=>'Example','version'=>1];
  }
  public function hi() {
    return "Hi there " . wire()->user->name;
  }
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
