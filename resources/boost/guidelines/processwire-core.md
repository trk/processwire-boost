# ProcessWire Core API & Scoping

Use ProcessWire API variables (`$pages`, `$page`, `$fields`, `$templates`, `$modules`, `$user`, `$users`, `$roles`, `$permissions`, `$input`, `$sanitizer`, `$session`, `$cache`, `$database`, `$files`, `$datetime`, `$log`, `$languages`, `$config`) rather than raw SQL for content and schema operations. Activate `pw-expert` or `pw-pages` for deep patterns.

## Context-Safe API Access (Multi-Instance Safe)

- **Inside `Wire`-derived classes (Modules, Custom Page classes):** Always use `$this->wire()->apiVar` (e.g., `$this->wire()->pages`) and cache into a local variable when accessed repeatedly in a method or loop. Never use `$this->pages` magic getters.
- **Inside `HookEvent` callbacks:** Use `$event->wire()->apiVar`.
- **Inside Template files (`site/templates/*.php`):** Use injected variables (`$page`, `$pages`) or Functions API (`page()`, `pages()` when `$config->useFunctionsAPI === true`).
- **Inside standalone closures, CLI commands, or anonymous migration classes:** Use `wire()->apiVar` or `wire('apiVar')` (anonymous migration classes do not extend `Wire`, so `$this->wire()` does not exist).

## Critical Core Invariants

- **Never overwrite API variable names** (`$pages`, `$page`, `$user`, `$users`, `$input`, `$files`, `$config`, `$session`, `$sanitizer`, `$log`, `$cache`, `$database`, `$fields`, `$templates`, `$modules`) in template or global scope, and never hardcode `/processwire/` or `/admin/` (always read `$this->wire()->config->urls->admin`).
- **Database property & Wired `NullPage`:** Access `$this->wire()->database` as a property (never `$this->wire()->database()`), and instantiate empty pages via `$this->wire()->pages->newNullPage()` (never `new NullPage()`).
- **Mutating & `Output Formatting ($page->of())` Trap:**
  - Always call `$page->of(false)` before mutating field values followed by `$page->save('field')`, or use atomic `$page->setAndSave('field', $value)` / `$pages->new(['template' => ..., 'parent' => ..., 'title' => ...])`.
  - Templates run with `$page->of() === true`, whereas CLI scripts, hooks, and background jobs often run with `of() === false`. A `maxFiles=1` image/file field returns `Pageimage|null` when `of(true)` **but returns a `Pageimages` collection when `of(false)`** — in CLI/hook/module code, call `$page->getUnformatted('image')->first()` or `$page->getFormatted('image')` rather than assuming `$page->image` is a single `Pageimage`.
- **Multi-Language Content (`LanguageSupport`):** When `$this->wire()->languages` is active, read/write translated field values via `$page->getLanguageValue($lang, 'title')` and `$page->setLanguageValue($lang, 'title', $value)`, resolve localized URLs via `$page->localUrl($lang)` (`LanguageSupportPageNames`), and enable a secondary language on a page via `$page->set("status{$lang->id}", 1)`.
