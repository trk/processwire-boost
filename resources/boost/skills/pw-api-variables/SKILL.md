---
name: pw-api-variables
description: Use when determining the correct, context-safe way to access ProcessWire API variables like $page, $pages, or $user.
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire API Variables Skill Playbook

ProcessWire exposes its core via "API variables" (e.g., `$page`, `$pages`, `$sanitizer`, `$user`, `$config`). Because ProcessWire can run in multi-instance modes and within varying PHP scopes, *how* you access these variables matters greatly for IDE autocompletion, performance, and context safety.

## 1. Contextual Best Practices

### In Classes (Modules, Custom Page Classes)
**DO:** Use `$this->wire()->apiVar` (e.g., `$this->wire()->pages`).
**WHY:** Ensures you are referencing the exact ProcessWire instance the class was loaded into (safe for multi-instance). It also provides IDE type-hinting natively.
**AVOID:** 
- `$this->apiVar` (e.g. `$this->pages`) - relies on slow `__get()` magic methods and can conflict with class properties.
- `wire()->apiVar` - ambiguous in multi-instance environments when called from inside a class.

### In Hooks (Ready.php, Modules)
**DO:** Use `$event->wire()->apiVar` (e.g., `$event->wire()->user`).
**WHY:** A hook is passed a `HookEvent $event` object. Pulling the API from the event guarantees you operate on the instance that triggered the hook.

### In Procedural Functions
**DO:** Use `wire()->apiVar` (e.g., `wire()->sanitizer`).
**WHY:** Global scope. Using the `->` property access instead of the string function (e.g. `wire('sanitizer')`) tells the IDE exactly what object type is being returned.

### In Template Files (`/site/templates/*.php`)
**DO:** Use native variables like `$page`, `$pages` or the Functions API like `page()`, `pages()`.
**WHY:** ProcessWire injects API variables directly into the template scope. 
- `$page->title` is convenient inside double quotes: `echo "<h1>$page->title</h1>";`.
- Provide IDE hints at the top of templates: `/** @var Page $page */` or `/** @var BlogPostPage $page */`.
- **Functions API (`pages()`)** is great because it is immune to scope loss (works inside `include` or local functions without globalizing) and is auto-type-hinted by the IDE. (Requires `$config->useFunctionsAPI = true;`).
**AVOID:** 
- `$this->pages` in templates. In a template file, `$this` is the `TemplateFile` renderer instance, not a generic scope.

## 2. Performance and Optimization

### Localize in Loops
If you are repeatedly accessing an API variable in a loop (especially from within a class where you use `$this->wire()->...`), assign it to a local variable first to skip hundreds of redundant function calls.

```php
// Inefficient inside a class loop:
foreach($items as $item) {
    $this->wire()->sanitizer->text($item);
}

// Efficient:
$sanitizer = $this->wire()->sanitizer;
foreach($items as $item) {
    $sanitizer->text($item);
}
```

## 3. General Rules

### Never overwrite API variables
ProcessWire has over 20 API variables (`$pages`, `$page`, `$user`, `$users`, `$config`, `$session`, `$sanitizer`, `$files`, `$mail`, etc). **NEVER** use these exact names for your own temporary variables (e.g. do not name your array of files `$files`), as it will destructively overwrite the API in the current scope.

### Namespaces
Always ensure your PHP files start with:
```php
<?php 

declare(strict_types=1);

namespace ProcessWire;
```
This ensures standard classes (like `PageArray`, `WireData`) and functions (like `wire()`) resolve correctly without needing a leading backslash.

### Injecting Custom API Variables
You can make a module, object, or string globally accessible by injecting it into the `wire` instance:
```php
// In init.php or a Module's init()
$wire->wire('myCustomService', new MyCustomService()); 

// Accessible everywhere:
$myCustomService = wire()->myCustomService;
```
