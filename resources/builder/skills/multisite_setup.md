# Multisite setup (Option #1)
description: Configure multiple /site-*/ directories with separate databases.
## Blueprints
- .ai/blueprints/pw_core/Config.json
## Steps
- Create /site-something/ with its own database (copy from a fresh install or existing site)
- Move /wire/index.config.php to /index.config.php and configure domain→site mapping
- Point alternate domain/subdomain and test
## Request
add a second site at site-example
## Response
requests mapped to /site-example/ with its DB
## Example
```text
/site-example/ (templates, modules, config.php → DB creds)
/index.config.php → map domain to site-example
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
