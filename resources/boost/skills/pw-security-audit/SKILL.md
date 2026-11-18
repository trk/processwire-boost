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

### 1. Selector Injection & Top-Level Limit Enforcement (`$sanitizer->selectorValue()`)
- Every external value interpolated into a `$pages->find()`, `$pages->findOne()`, `$pages->get()`, `$pages->count()`, or `$page->children()` selector string MUST be sanitized with `$sanitizer->selectorValue()` (or cast to `(int)` for numeric IDs).
- Enforce a positive `limit=N` (`N >= 1`) at the **top level** of `$pages->find()` queries: `limit=0` disables pagination (unlimited), and nested `limit=` clauses inside `[...]` sub-selectors (e.g., `items=[limit=5]`) or `(...)` groups do **not** bound the outer query.

```php
// SAFE
$q = $sanitizer->selectorValue((string) $input->get('q'));
$results = $pages->find("template=product, title|summary%={$q}, limit=50");

// UNSAFE — attacker can inject ", include=all, template=user"
$q = $input->get('q');
$results = $pages->find("title%={$q}");
```

### 2. RBAC, Numeric Name Resolution & Core Entity Guards
- Does the code use `$pages->get()` or `include=all` / `include=unpublished` / `check_access=0` with user-controlled IDs or paths and then render or mutate the page without checking `$page->viewable()` or `$page->editable()`?
- Are custom URL hooks (`$wire->addHook('/api/...', ...)`) and Process module actions enforcing `$user->isLoggedin()` and `$user->hasPermission('...')`?
- **Numeric-String ID Trap on RBAC Repositories:** `$users->get()`, `$roles->get()`, and `$permissions->get()` treat numeric strings (e.g., `'41'`) as page IDs! When looking up by name from untrusted input, reject `ctype_digit($raw)` and verify `$item->name`:
- **Protected System Entities:** Block destructive actions (`trash`, `delete`, `rename`, `uninstall`, role revocation) on root (`id=1`), admin, trash, 404, RBAC container pages (`$config->usersPageID`, `rolesPageID`, `permissionsPageID`), `Page::statusSystem | Page::statusSystemID` pages, `Field::flagSystem | Field::flagPermanent | Field::flagGlobal` fields, `Template::flagSystem` templates, and built-in `superuser` / `guest` users and roles.

```php
// SAFE — page edit check + safe named user lookup
$target = $pages->get((int) $input->post('page_id'));
if (!$target->id || !$target->editable()) {
	throw new WirePermissionException($this->_('You do not have permission to edit this page.'));
}

$rawUser = trim((string) $input->post('username'));
$cleanUser = $sanitizer->pageName($rawUser);
if ($cleanUser === '' || ctype_digit($cleanUser)) {
	throw new WireException($this->_('Invalid username.'));
}
$account = $users->get('name=' . $sanitizer->selectorValue($cleanUser));
if (!$account->id || $account->name !== $cleanUser) {
	throw new Wire404Exception();
}

// UNSAFE — $pages->get() bypasses access control; $users->get('41') resolves superuser ID 41!
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

### 4. SQL Injection & Transaction Integrity (`$this->wire()->database`)
- Raw SQL queries executed on `$this->wire()->database` must use prepared statements (`prepare()` + `bindValue()`) and escape dynamic table/column identifiers via `$database->escapeTable()` / `$database->escapeCol()`.
- Wrap multi-step raw SQL mutations in `$database->beginTransaction()` / `commit()` / `rollBack()` (avoiding DDL statements inside active MySQL transactions, which trigger implicit commits).

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

### 6. Path Traversal, Symlinks, Upload Validation & JSON Payload Bounds
- Strip directory components with `basename()`, reject symbolic links (`!is_link($candidate)`), and verify `realpath($candidate)` starts with `rtrim($realBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR` (trailing separator prevents sibling-prefix bypasses such as `/var/site/assets-evil`).
- Uploaded files handled outside native `InputfieldFile` must be validated with `$sanitizer->validateFile($tmpPath)` and restricted to an explicit allowlist of extensions.
- Enforce maximum byte-size checks on raw JSON request bodies before calling `json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR)` with an explicit recursion depth limit.

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
- `pw-module-development` (`references/filevalidator.md`)
- `pw-url-routing`
- `pw-pages`
