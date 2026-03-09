# Security & Sanitization in ProcessWire

Always sanitize input and check permissions.

## Using $sanitizer

### Common Methods

- `$sanitizer->text($val)`: Strips HTML, reduces to single line.
- `$sanitizer->textarea($val)`: Multi-line text with optional HTML stripping.
- `$sanitizer->email($val)`: Returns valid email or empty string.
- `$sanitizer->digits($val)`: Returns only digits.
- `$sanitizer->pageName($val)`: Validates for URL use.

### Specific Types

- `$sanitizer->bool($val)`
- `$sanitizer->int($val, $min, $max)`
- `$sanitizer->selectorValue($val)`: CRITICAL for dynamic selectors.

## Access Control

### Role & Permission Check

```php
if($user->hasRole('editor')) { ... }
if($user->hasPermission('page-edit')) { ... }
```

### Viewable/Editable

Convenience methods on Page objects.

```php
if($page->viewable()) { ... }
if($page->editable()) { ... }
```
