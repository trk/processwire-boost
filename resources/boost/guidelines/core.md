# ProcessWire API & Development Guidelines

## Core Principles

- **Everything is a Page**: Every content item in ProcessWire is a `Page` object.
- **API First**: Use the `$wire` API (or `$pages`, `$templates`, `$fields` variables) for all operations.
- **Selectors**: Always use efficient selectors for finding pages.

## API Examples (PHP 8.3+)

### Finding Pages

```php
$results = $pages->find("template=project, status=published, limit=10");
```

### Creating a Page

```php
$p = new Page();
$p->template = "basic-page";
$p->parent = "/about/";
$p->name = "new-page";
$p->title = "New Page Title";
$p->save();
```

### Hooks

```php
$wire->addHookAfter("Pages::saved", function(HookEvent $event) {
    $page = $event->arguments(0);
    // Custom logic after page save
});
```

## AI Instructions

- Use `wire_query` tool to search for existing pages before creating new ones.
- Always check the `map.json` for template and field availability.
- Prefer `Strict Typing` in all generated PHP code.
