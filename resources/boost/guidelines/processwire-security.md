# Security & Access Control

Treat all external input (`$input->get`, `$input->post`, `$input->cookie`, URL segments, headers, and uploaded files) as untrusted. Activate `pw-security-audit` when reviewing or hardening endpoints.

## Input Sanitization & Output Encoding (`$sanitizer`)

Sanitize every input according to its target context:

- **`$sanitizer->selectorValue($val)`**: **Mandatory** before interpolating any user input into a `$pages->find()`, `$pages->findOne()`, or `$pages->get()` selector string.
- **`$sanitizer->text($val)`**: Single-line plain text with HTML stripped.
- **`$sanitizer->textarea($val)`**: Multi-line plain text with HTML stripped.
- **`$sanitizer->email($val)`**: Returns a valid email address or `''`.
- **`$sanitizer->pageName($val)`**: URL-safe ProcessWire page slug (`a-z0-9-_`).
- **`$sanitizer->int($val)` / `$sanitizer->float($val)`**: Numeric casting.
- **`$sanitizer->entities($val)`**: HTML entity encoding for safe output rendering (prevents XSS).
- **`$sanitizer->validateFile($path)`**: Runs all registered `FileValidatorModule` scanners on an uploaded file.

```php
$q = $sanitizer->selectorValue($input->get('q'));
$results = $pages->find("template=product, title|summary%={$q}, limit=25");
```

## Role-Based Access Control (RBAC)

Enforce explicit access checks on custom endpoints, mutations, and protected views:

```php
if (!$user->isLoggedin() || !$user->hasPermission('page-edit')) {
	throw new WirePermissionException($this->_('Access denied.'));
}

if (!$page->viewable()) {
	throw new Wire404Exception();
}

if (!$page->editable()) {
	throw new WirePermissionException($this->_('You cannot edit this page.'));
}
```

## CSRF Protection (`$session->CSRF`)

Protect every state-changing POST/PUT/DELETE request in custom forms and admin actions with ProcessWire's CSRF token manager:

```php
// In HTML form:
// <?= $session->CSRF->renderInput() ?>

// On POST handling:
$session->CSRF->validate(); // Throws WireCSRFException when invalid
```

## SQL Injection & Path Traversal Prevention

- **Prepared Statements:** When using `$this->wire()->database`, always use `$database->prepare()` with `bindValue()` and `$database->escapeTable()` for dynamic table identifiers.
- **Path Traversal:** Never concatenate raw request parameters into file paths; strip directory components with `basename()` and verify the resolved path stays inside the expected directory.
