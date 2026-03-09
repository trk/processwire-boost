# Autoload module & hooks
description: Load module on boot and add hooks.
## Blueprints
- .ai/blueprints/pw_core/Modules.json
- .ai/blueprints/pw_core/Pages.json
## Steps
- Set 'autoload' => true in getModuleInfo
- Add hooks in init/ready
## Request
log when a page is saved
## Response
entry in /site/assets/logs/hello.txt
## Example
```php
<?php namespace ProcessWire;
class Hello extends WireData implements Module {
  public static function getModuleInfo() {
    return ['title'=>'Hello','summary'=>'Autoload demo','version'=>1,'autoload'=>true];
  }
  public function init() {
    $this->addHookAfter('Pages::saved', function($event){
      $page = $event->arguments(0);
      wire()->log->save('hello', "Saved $page->path");
    });
  }
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
