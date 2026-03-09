# Module types & naming
description: Choose correct module class prefix so ProcessWire recognizes its type.
## Blueprints
- .ai/blueprints/pw_core/Modules.json
## Steps
- Identify module type (Fieldtype, Inputfield, Process, Textformatter, AdminTheme, WireMail, Tfa, ImageSizerEngine, FileCompiler, FileValidator)
- Prefix class name accordingly
- For other types, use recommended prefixes (Markup, Session, Jquery, LanguageSupport)
## Request
name a Textformatter module “Something”
## Response
TextformatterSomething class name
## Example
```php
<?php
// Correctly typed module class names
class TextformatterSomething extends Textformatter {}
class InputfieldSuperCheckbox extends Inputfield {}
class FieldtypeBook extends Fieldtype {}
class ProcessAcme extends Process {}
class AdminThemeCustom extends AdminTheme {}
class WireMailFoo extends WireMail {}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
