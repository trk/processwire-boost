# Module
### PagesVersions (3.0.244)
- Core PagesVersions module provides API to save, list and restore page versions.
- Supports partial save/restore for selected fields, including repeaters.
- Works with most Fieldtypes and ProFields.

```php
$pv = wire()->modules->get('PagesVersions');
```

### Module types & naming
- Predefined types must use a prefix in the class name so ProcessWire can recognize them without loading:
  - Fieldtype… (custom field types)
  - Inputfield… (form input types)
  - Process… (admin processes/apps)
  - Textformatter… (format text)
  - AdminTheme… (admin themes)
  - WireMail… (mail transports)
  - Tfa… (two-factor auth types)
  - ImageSizerEngine… (image resize engines)
  - FileCompiler… (file compiler modules)
  - FileValidator… (file validators)
- Recommended prefixes for other categories:
  - Markup… (markup generation), Session… (session modules), Jquery… (jQuery plugins), LanguageSupport… (multilanguage helpers).

Examples:
- TextformatterSomething, InputfieldSuperCheckbox, FieldtypeBook, ProcessSomething, AdminThemeCustom, WireMailFoo.

### Module development basics
- Create a PHP class that implements Module and typically extends WireData (if not a predefined type).
- File name should match class and end with .module or .module.php, placed under /site/modules/ModuleName/ModuleName.module.php.
- Provide module info via static getModuleInfo(): title, summary, version (and optionally autoload=true).

```php
<?php namespace ProcessWire;
class Foo extends WireData implements Module {
  public static function getModuleInfo() {
    return ['title'=>'Foo test module','summary'=>'Example module','version'=>1];
  }
}
```

### Site modules vs core modules
- Site modules live in /site/modules/ and are specific to your site; core modules live in /wire/modules/ (do not modify core modules).
- Each module should be in its own directory named after the module class: /site/modules/ProcessDatabaseBackups/ProcessDatabaseBackups.module.
- For admin-managed installs, /site/modules may be writable (optional; assess hosting security).

### Installing modules
- From Admin (Modules > New): install from Modules Directory, upload ZIP, or paste a ZIP URL.
- From filesystem: upload to /site/modules/ModuleName/, then Modules > Check for new modules, then Install.

### Uninstalling and removing
- Admin: Modules > Site > select module > Uninstall.
- If filesystem is writable, a Delete button removes files; otherwise remove manually via SFTP.

### Autoload modules & hooks
- Autoload modules load at boot to add hooks or behavior: set 'autoload' => true in getModuleInfo().
- Add hooks to extend/alter core behavior in init/ready or constructor contexts.

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

### Add methods via hooks
- Autoload modules can expose new methods to existing classes using hooks.

```php
$this->addHook('Page::hello', function($event){ $event->return = "Hello ".$event->object->title; });
```

### Module translations (multi-language)
- Add translations via admin (Setup > Languages) and export CSV, then bundle under /site/modules/YourModule/languages/.
- Instruct users to install translations from the module info screen (Languages row → install translations).
