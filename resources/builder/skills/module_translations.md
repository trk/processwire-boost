# Module translations
description: Bundle multi-language CSV translations with your module.
## Blueprints
- .ai/blueprints/pw_core/Modules.json
- .ai/blueprints/pw_core/Languages.json
## Steps
- From admin, add translatable module PHP files in Setup > Languages
- Translate and export CSV
- Copy CSV to /site/modules/YourModule/languages/
- Instruct users to install translations in module info screen
## Request
add Spanish translations to module
## Response
CSV placed in languages/ and installed
## Example
```text
/site/modules/ProcessHello/languages/es.csv
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
