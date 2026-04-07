# ProcessWire Advanced Development

ProcessWire provides a powerful, transparent hooking system, custom module development environment, and schema management API.

## The Hooking System

You can hook into almost any core method to modify its behavior, manipulate variables before execution, or react after execution. The best places to place hooks are in `site/ready.php` or within a custom Module's `init()`/`ready()` methods.

- **`addHookBefore`**: Modify arguments or prevent original execution.
- **`addHookAfter`**: Read the return value, log metrics, or execute follow-up tasks.

```php
// Modifying a value before it's saved to the DB
$wire->addHookBefore('Pages::save', function(HookEvent $event) {
    /** @var \ProcessWire\Page $page */
    $page = $event->arguments(0);
    // If saving an article, automatically generate a summary if empty
    if ($page->template == 'article' && empty($page->summary)) {
        $page->summary = substr($page->body, 0, 150) . '...';
    }
});

// Reacting to a completed action (e.g., sending an email after a user is created)
$wire->addHookAfter('Pages::added', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template == 'user') {
        // user was just added!
    }
});
```

## Module Development

Modules extend the core. Modules are classes that implement the `Module` interface and require a `getModuleInfo()` array array returning their metadata (`title`, `version`, `summary`, etc.).

- **Process Modules**: Create URLs and GUI interfaces in the ProcessWire admin area.
- **Fieldtype Modules**: Dictate how data goes in and out of the database (SQL schema).
- **Inputfield Modules**: Dictate how users input data (HTML form elements).

**Namespaces**: Always declare `namespace ProcessWire;` or fully qualify your API usage (e.g., `\ProcessWire\wire('pages')`).

## Schema Management via API

You can programmatically scaffold templates and fields. This is incredibly useful for writing install scripts or managing schema via code.

```php
// 1. Create Field
$bodyField = new \ProcessWire\Field();
$bodyField->type = wire('fieldtypes')->get('FieldtypeTextarea');
$bodyField->name = 'custom_body';
$bodyField->label = 'Custom Body Text';
$bodyField->save();

// 2. Create Fieldgroup and attach field
$fg = new \ProcessWire\Fieldgroup();
$fg->name = 'article_template';
$fg->add('title'); // Always add title to pages!
$fg->add('custom_body');
$fg->save();

// 3. Create Template and attach Fieldgroup
$t = new \ProcessWire\Template();
$t->name = 'article_template';
$t->fieldgroup = $fg;
$t->save();
```
