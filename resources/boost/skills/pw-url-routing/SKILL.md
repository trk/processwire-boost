---
name: pw-url-routing
description: "Use when utilizing ProcessWire URL/Path hooks to create custom routing, virtual pages, or API endpoints without physical backend templates."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire URL/Path Hooks Skill

## When to Use This Skill

Use this skill when you need to:

- Create custom API endpoints (e.g., returning JSON data).
- Handle specific URLs without creating physical pages, templates, or fields in the admin.
- Create virtual routes, short URLs, or SEO-friendly URL segments dynamically.
- Intercept existing paths before ProcessWire issues a 404 response.

---

> [!IMPORTANT]
> **CRITICAL DOCUMENTATION RULE**: Before writing any ProcessWire code or using its API, you MUST consult the local API documentation located at `.agents/docs/index.md`. You MUST NOT hallucinate API methods.
> ALL code, documentation, and file contents MUST strictly be written in English. Ensure inner code strings are always in English and wrapped in ProcessWire translation functions (`$this->_('English String')` or `__('English String', __FILE__)`) so they can localized if needed.

## Fundamentals

URL hooks are defined during the ProcessWire boot process, typically in `site/init.php` (recommended) or `site/ready.php`, or inside a module's `init()` method.

### Basic Routing

To output a string directly:

```php
$wire->addHook('/api/hello', function($event) {
    return 'Hello World';
});
```

To output HTML and prevent further ProcessWire output, return `true`:

```php
$wire->addHook('/api/hello', function($event) {
    echo '<h1>Hello World</h1>';
    return true;
});
```

### Rendering an Existing Page

You can return a `Page` object to map a custom URL directly to an existing page. ProcessWire will make this the active `$page` and render it normally:

```php
$wire->addHook('/special-offer', function($event) {
    return $event->pages->get('/about/contact/');
});
```

## Advanced Routing Patterns

### Named Arguments (Variables)

You can extract values from the URL into arguments.

- **Simple Named Arguments**: `{argument_name}`

```php
$wire->addHook('/hello/{planet}', function($event) {
    // You can access it via arguments or directly
    $planet = $event->arguments('planet');
    // or $event->planet;

    // Validate or return false for a 404 response
    if (!in_array($planet, ['earth', 'mars'])) {
        return false;
    }

    return "Hello " . $planet;
});
```

- **Pattern Matching Named Arguments**: `(name:pattern)`

```php
$wire->addHook('/hello/(planet:earth|mars|jupiter)', function($event) {
    return "Hello " . $event->planet;
});
```

### Wildcards & Regular Expressions

If the path structure is complex, you can use full PCRE regex patterns.
Remembering portions of the matched URL without named arguments uses `$event->arguments(1)`.

```php
$wire->addHook('(/.*)/json', function($event) {
    $path = $event->arguments(1);
    $page = $event->pages->findOne($path);

    if (!$page->id || !$page->viewable()) {
        return false;
    }

    // Returning an array automatically outputs as JSON with proper Content-Type
    return [
        'id' => $page->id,
        'title' => $page->title
    ];
});
```

## Important Behaviors & Constraints

> [!WARNING]
> **Trailing Slashes**: ProcessWire strictly enforces trailing slashes. `/foo/bar/` definition will 301 redirect `/foo/bar` to `/foo/bar/`.
> To accept both without redirecting: `$wire->addHook('/foo/bar/?', ...)`

> [!NOTE]
> **Pagination**: URL hooks ignore numbered pagination by default (`/foo/bar/page2`).
> To support pagination, append `{pageNum}` strictly as the last segment without a trailing slash:
> `$wire->addHook('/foo/bar/{pageNum}', ...)`
> The page number can be accessed via `$event->pageNum`.

## Return Values Reference

1. **String**: Output string directly.
2. **Array**: Converted automatically to JSON with `application/json` header.
3. **Page Object**: ProcessWire renders the page and sets it as the active `$page`.
4. **`true`**: Tells ProcessWire you output the data manually (via `echo`).
5. **`false` or `null`**: Instructs ProcessWire to return a standard 404 response.

## Conditional Registration

Instead of checking request types inside the hook callback, conditionally wrap the hook registration to save memory and process cycles:

```php
if ($input->is('POST')) {
    $wire->addHook('/api/submit', function($event) { /* Handle POST */ });
}
```
