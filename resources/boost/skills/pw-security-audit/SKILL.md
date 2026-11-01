---
name: pw-security-audit
description: "Use when auditing ProcessWire modules, hooks, templates, URL endpoints, or CLI/MCP handlers for OWASP vulnerabilities, selector injection, XSS, RBAC bypass, CSRF, SQL injection, or path traversal."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Security Audit

Audit ProcessWire applications for exploitable vulnerabilities, access-control gaps, and unsafe input handling using ProcessWire-native mitigations (`$sanitizer`, `$user`, `$page->viewable()`, `$page->editable()`, `$session->CSRF`, `$database->prepare()`, `FileValidatorModule`).

## Documentation Reference (Source of Truth)

- Core CLI: `php index.php docs get-text Sanitizer`, `php index.php docs get-text Session`, `php index.php docs get-text Users`, `php index.php docs get-text FileValidatorModule`
- Core Markdown: `wire/core/Sanitizer/API.md`, `wire/core/Session/API.md`, `wire/core/Users/API.md`, `wire/core/WireDatabase/API.md`

## Core Audit Checklist

### 1. Selector Injection (`$sanitizer->selectorValue()`)
Every external value interpolated into a `$pages->find()`, `$pages->findOne()`, `$pages->get()`, `$pages->count()`, or `$page->children()` selector string MUST be sanitized with `$sanitizer->selectorValue()` (or cast to `(int)` for numeric IDs):

```php
// SAFE
$q = $sanitizer->selectorValue((string) $input->get('q'));
$results = $pages->find("template=product, title|summary%={$q}, limit=50");

// UNSAFE — attacker can inject ", include=all, template=user"
$q = $input->get('q');
$results = $pages->find("title%={$q}");
```

### 2. Role-Based Access Control (RBAC) & `include=all` Leaks
- Does the code use `$pages->get()` or `include=all` / `include=unpublished` / `check_access=0` with user-controlled IDs or paths and then render or mutate the page without checking `$page->viewable()` or `$page->editable()`?
- Are custom URL hooks (`$wire->addHook('/api/...', ...)`) and Process module actions enforcing `$user->isLoggedin()` and `$user->hasPermission('...')`?

```php
// SAFE
$target = $pages->get((int) $input->post('page_id'));
if (!$target->id || !$target->editable()) {
	throw new WirePermissionException($this->_('You do not have permission to edit this page.'));
}

// UNSAFE — $pages->get() bypasses access control and unpublished checks
$target = $pages->get((int) $input->post('page_id'));
$target->setAndSave('title', $sanitizer->text((string) $input->post('title')));
```

### 3. Cross-Site Request Forgery (CSRF)
Every state-changing POST/PUT/DELETE handler in templates, URL hooks, or custom admin forms must validate a CSRF token via `$session->CSRF`:

```php
// In HTML form:
echo $session->CSRF->renderInput();

// In POST handler:
try {
	$session->CSRF->validate();
} catch (WireCSRFException $e) {
	throw new Wire404Exception();
}
```

### 4. SQL Injection (`$this->wire()->database`)
Raw SQL queries executed on `$this->wire()->database` must use prepared statements (`prepare()` + `bindValue()`) and escape dynamic table/column identifiers via `$database->escapeTable()` / `$database->escapeCol()`:

```php
// SAFE
$database = $this->wire()->database;
$table = $database->escapeTable($tableName);
$stmt = $database->prepare("SELECT id, data FROM `{$table}` WHERE pages_id = :pageId");
$stmt->bindValue(':pageId', $page->id, \PDO::PARAM_INT);
$stmt->execute();

// UNSAFE — direct interpolation
$stmt = $database->query("SELECT id, data FROM {$tableName} WHERE pages_id = {$page->id}");
```

### 5. Cross-Site Scripting (XSS) & Output Formatting
- Are request parameters (`$input->get`, `$input->post`, URL segments) encoded with `$sanitizer->entities()` before being echoed into HTML or attributes?
- Are plain-text fields configured with `TextformatterEntities`, or escaped via `$sanitizer->entities((string) $page->getUnformatted('field'))` when output formatting is off?
- In `Inputfield::render()` or `Process` modules, are attribute values escaped via `$sanitizer->entities()` or `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`?

```php
// SAFE
$searchTerm = $sanitizer->entities((string) $input->get('q'));
echo "<h2>Results for {$searchTerm}</h2>";

// UNSAFE — reflected XSS
echo "<h2>Results for {$input->get->q}</h2>";
```

### 6. Path Traversal & File Upload Validation
- File names derived from user input must be stripped with `basename()` and resolved with `realpath()` to confirm they stay within the intended base directory.
- Uploaded files handled outside native `InputfieldFile` must be validated with `$sanitizer->validateFile($tmpPath)` and restricted to an explicit allowlist of extensions.

## Risk Classification & Reporting Structure

| Severity | Criteria |
|---|---|
| **Critical** | Remote code execution, SQL injection, authentication bypass, arbitrary file write/read |
| **High** | Privilege escalation (editing/viewing restricted pages via `$pages->get()`), missing CSRF on state-changing endpoints |
| **Medium** | Reflected/stored XSS, selector injection, unvalidated redirects |
| **Low / Info** | Missing rate limits, verbose error leaks, defense-in-depth improvements |

Report each finding with:
1. **Location & Severity** (`file:line` + level)
2. **Exploit Scenario** (concrete input that triggers the flaw)
3. **ProcessWire-Native Fix** (drop-in replacement code)

## Related Skills
- `pw-module-filevalidator`
- `pw-url-routing`
- `pw-pages`
