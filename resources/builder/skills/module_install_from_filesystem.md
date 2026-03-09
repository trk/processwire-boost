# Install module from filesystem
description: Install a site module uploaded to /site/modules/.
## Blueprints
- .ai/blueprints/pw_core/Modules.json
## Steps
- Upload module to /site/modules/ModuleName/
- In Admin, go to Modules
- Click “Check for new modules”, then Install
## Request
install ModuleName after upload
## Response
module installed and available
## Example
```text
Upload → /site/modules/ProcessHello/ProcessHello.module
Admin → Modules → Check for new modules → Install
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
