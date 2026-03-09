# ProcessWire Core Guide
This document is generated from local core PHPDoc and best practices.
## Principles
- Use wire('pages') and native API variables
- Prefer hooks over core modifications
- Use $sanitizer for input/output safety

## Coding Style (PW guide)
- Based on PSR-1/PSR-2 with PW specifics: tabs for indent; classes StudlyCaps; methods camelCase; braces on same line.
- No hard line limit; prefer ≤120 chars.
- Use ProcessWire namespace; visibility on all methods/properties.
- Use __(), $this->_(), _n() for translatable strings; one translation call per line.

## Multi‑site (summary)
- Option #1 (core): multiple /site‑something/ directories with separate databases; route via index.config.php.
- Pros: isolation and easier maintenance/moves. Cons: data sharing requires extra work.
- Option #2: 3rd‑party Multisite modules run from same DB.

## Security (essentials)
- File permissions
  - Prefer restrictive perms based on host: start with 755 for writable dirs and 644 for writable files if Apache runs as your user.
  - More secure if supported: dirs 700, files 600. Avoid 777/666, especially in shared hosting.
  - /site/config.php should be most restrictive possible that still works (e.g., 600/640/644).
  - /site/modules writable is optional; prefer read‑only in shared environments unless installing from Admin.
- Template input safety
  - Always sanitize/validate user input. Never pass raw input to API methods/selectors.
```php
$q = wire()->sanitizer->selectorValue($input->get->q);
if($q) $items = wire()->pages->find("body%=$q");
```
