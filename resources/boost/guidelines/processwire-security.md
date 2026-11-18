# Security & Access Control

Treat all external input (`$input->get`, `$input->post`, `$input->cookie`, URL segments, headers, uploaded files) as untrusted. Activate `pw-security-audit` when auditing or hardening endpoints.

- **Context-Specific `$sanitizer`:**
  - `$sanitizer->selectorValue($val)`: **Mandatory** before interpolating any external value into `$pages->find()`, `findOne()`, `get()`, or `count()`.
  - `$sanitizer->text($val)` / `textarea($val)` / `email($val)` / `pageName($val)` / `int($val)` / `float($val)`: Input normalization.
  - `$sanitizer->entities($val)`: HTML entity encoding for safe output rendering (prevents XSS).
  - `$sanitizer->validateFile($path)`: Executes all registered `FileValidatorModule` upload scanners.
- **RBAC & Access Leaks:** Enforce `$user->isLoggedin()` and `$user->hasPermission('...')` on custom admin/API actions. Because `$pages->get()`, `include=all`, `include=unpublished`, and `check_access=0` bypass access checks, always verify `$page->viewable()` or `$page->editable()` before exposing or mutating the page.
- **Named RBAC Numeric-ID Guard:** `$users->get()`, `$roles->get()`, and `$permissions->get()` treat numeric strings (e.g., `'41'`) as page IDs. When resolving by name from untrusted input, reject `ctype_digit($raw)` and query `name={$sanitizer->selectorValue($sanitizer->pageName($raw))}`, verifying `$item->name`.
- **Protected Core Entities:** Block destructive operations on root (`id=1`), admin, trash, 404, RBAC container pages, `Page::statusSystem*` pages, system fields (`Field::flagSystem | Field::flagPermanent | Field::flagGlobal`), system templates (`Template::flagSystem`), and built-in `superuser` / `guest` accounts and roles.
- **CSRF (`$session->CSRF`):** Render `$session->CSRF->renderInput()` in forms and call `$session->CSRF->validate()` on every state-changing `POST`/`PUT`/`DELETE` request.
- **SQL, Filesystem & JSON Bounds:**
  - Use `$database->prepare()` + `bindValue()`, `$database->escapeTable()` / `escapeCol()` for dynamic identifiers, and `beginTransaction()` / `commit()` / `rollBack()` around multi-step raw SQL writes.
  - Strip filenames with `basename()`, reject symlinks (`!is_link($path)`), and verify `realpath($path)` starts with `rtrim($realBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR`.
  - Enforce byte-size limits on raw request bodies before `json_decode($raw, true, 64, JSON_THROW_ON_ERROR)`.
