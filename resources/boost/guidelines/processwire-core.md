# ProcessWire Core Logic & Variables

ProcessWire is built around a hierarchical page tree where every piece of content is a `Page` object whose fields are defined by its `Template` (`Fieldgroup`). Use ProcessWire API variables rather than raw SQL for content and schema operations. For advanced CLI and scoping patterns, activate the `pw-expert` skill.

## Core Concepts & API Variables

| API Variable | Class | Purpose |
|---|---|---|
| `$pages` | `Pages` | Page retrieval and lifecycle (`find`, `findOne`, `findMany`, `get`, `count`, `has`, `new`, `add`, `save`, `trash`, `delete`, `newNullPage`) |
| `$page` | `Page` | Current page in template context |
| `$fields` | `Fields` | Field schema definitions (`$fields->get('name')`, `$fields->save($field)`) |
| `$templates` | `Templates` | Template definitions (`$templates->get('name')`, `$templates->save($tpl)`) |
| `$modules` | `Modules` | Module loading and configuration (`$modules->get('ModuleName')`, `$modules->isInstalled()`) |
| `$user` / `$users` | `User` / `Users` | Current user (`$user->isLoggedin()`, `$user->hasRole()`, `$user->hasPermission()`) and user management |
| `$roles` / `$permissions` | `Roles` / `Permissions` | Role and permission definitions |
| `$input` | `WireInput` | Request data (`$input->get('q')`, `$input->post('title')`, `$input->urlSegment(1)`) |
| `$sanitizer` | `Sanitizer` | Input sanitization and escaping (`text`, `textarea`, `email`, `pageName`, `selectorValue`, `entities`, `validateFile`) |
| `$session` | `Session` | Session state, redirects (`$session->redirect()`), flash notices, and CSRF (`$session->CSRF`) |
| `$cache` | `WireCache` | Time- or template-invalidated caching (`$cache->get('key', 3600, fn() => ...)`) |
| `$database` | `WireDatabasePDO` | PDO database wrapper — always access as property `$this->wire()->database`, never as a method |
| `$files` | `WireFileTools` | Safe filesystem operations (`mkdir`, `rmdir`, `copy`, `rename`, `tempDir`) |
| `$datetime` | `WireDateTime` | Date formatting and parsing |
| `$log` | `WireLog` | Application logging (`$log->save('channel', 'message')`, `$log->error()`) |
| `$languages` | `Languages` | Installed languages (present when `LanguageSupport` is installed) |
| `$config` | `Config` | System configuration, filesystem paths (`$config->paths->*`), and URLs (`$config->urls->*`) |

## API Variable Scoping & Multi-Instance Safety

- **Inside `Wire`-derived classes (Modules, Custom Page classes):** Always use `$this->wire()->apiVar` (e.g., `$this->wire()->pages`). Never use `$this->pages` (triggers slow `__get()` magic lookups and risks property collisions).
- **Local caching in methods and loops:** When accessing an API variable multiple times in a method or loop, cache it in a local variable at the top of the method:

```php
$pages = $this->wire()->pages;
$sanitizer = $this->wire()->sanitizer;
```

- **Inside Hook callbacks (`site/ready.php`, `site/init.php`, Modules):** Use `$event->wire()->apiVar`.
- **Inside Template files (`site/templates/*.php`):** Use injected variables (`$page`, `$pages`) or the Functions API (`page()`, `pages()` when `$config->useFunctionsAPI === true`).
- **Inside standalone closures or anonymous migration classes:** Use `wire()->apiVar` or `wire('apiVar')` (anonymous migration classes do not extend `Wire`, so `$this->wire()` is unavailable).
- **Never overwrite API variable names:** Never assign local variables named `$pages`, `$page`, `$user`, `$users`, `$input`, `$files`, `$config`, `$session`, `$sanitizer`, `$log`, `$cache`, `$database`, `$fields`, `$templates`, or `$modules` in template or global scope.
- **NullPage instantiation:** Always call `$this->wire()->pages->newNullPage()` — never `new NullPage()` directly (an unwired `NullPage` lacks the ProcessWire instance).

## Working with Pages and Field Values

```php
// Reading field values
echo $page->title;
echo $page->body;

// Single and multi-image fields
$image = $page->images->first();
if ($image) {
	echo $image->size(800, 600)->url;
}

// Page reference fields
$category = $page->category; // Single Page or NullPage
if ($category->id) {
	echo $category->title;
}
foreach ($page->related_products as $related) {
	echo $related->title;
}

// PageArray::each rendering
echo $page->children('sort=title')->each("<li><a href='{url}'>{title}</a></li>");

// Mutating and saving a loaded page
$page->of(false); // Disable output formatting before mutating field values
$page->title = 'New Title';
$page->save('title');
// Or atomic single-field update:
$page->setAndSave('title', 'New Title');

// Creating a new page
$newPage = $pages->new([
	'template' => 'product',
	'parent' => '/products/',
	'title' => 'New Product',
]);
```

## Orienting on an Existing Site

1. Inspect schema via `pw_schema_read` and `pw_module_list` (MCP) or `vendor/bin/wire template:list --json` and `vendor/bin/wire field:list --json`.
2. Check `site/config.php`, `site/init.php`, and `site/ready.php` for hooks, URL routes, and custom configuration.
3. Inspect `site/classes/` for Custom Page Classes and `site/templates/` for template controllers.
4. Inspect `site/modules/` for project-specific modules and check if any expose `AGENTS.md` or `API.md`.
5. If the optional `AgentTools` module is installed in `site/modules/AgentTools/`, read `site/modules/AgentTools/AGENTS.md`.
