# Security & Access Control

Always assume user input is hostile. ProcessWire provides robust tools for sanitation, output encoding, and Role-Based Access Control (RBAC).

## Input Sanitization (`$sanitizer`)

Never insert raw `$input->post` or `$input->get` values into the database or HTML directly.

- **`$sanitizer->text($val)`**: Strips HTML completely and reduces to a single line.
- **`$sanitizer->textarea($val)`**: Multi-line text. Can accept optional flags to strip HTML.
- **`$sanitizer->email($val)`**: Returns a validated email address or an empty string.
- **`$sanitizer->entities($val)`**: Encodes characters into HTML entities (prevent XSS output).
- **`$sanitizer->pageName($val)`**: Formats a string to be URL-safe (lowercase, hyphenated, safe chars).
- **`$sanitizer->selectorValue($val)`**: **CRITICAL**. Always sanitize user input before putting it into a `$pages->find()` selector!

Example of safe selector parsing:
```php
$q = $sanitizer->selectorValue($input->get->q);
$results = $pages->find("title%=$q");
```

## Role-Based Access Control (RBAC)

Use the `$user` object to explicitly check permissions, especially when writing custom endpoints, saving forms, or rendering sensitive content.

```php
// Check for generic roles or permissions
if ($user->hasRole('editor')) { ... }
if ($user->hasPermission('page-edit')) { ... }

// Check if the current user can edit a specific page
if ($page->editable()) { ... }

// Check if the current user can view a specific page
if ($page->viewable()) { ... }
```

## CSRF Protection

When generating custom forms on the front-end or custom admin modules, always protect them with CSRF tokens.

**Generating the Token in the Form:**
```html
<input type="hidden" name="<?= $session->CSRF->getTokenName(); ?>" value="<?= $session->CSRF->getTokenValue(); ?>" />
```

**Validating the Token on POST:**
```php
try {
    $session->CSRF->hasValidToken(); 
} catch (WireCSRFException $e) {
    die("CSRF Token Invalid!");
}
```
