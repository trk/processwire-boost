# ProcessWire Module Development

ProcessWire modules extend the core functionality.

## Core Module Types

### Fieldtype

Handles data storage and retrieval in the database.

- `___getDatabaseSchema()`: Define SQL table.
- `___loadPageField()`: Format data for API.
- `___savePageField()`: Format data for DB.

### Inputfield

Handles the UI for fields in the admin.

- `___render()`: Output HTML.
- `___processInput()`: Handle POST data.

### Process

Creates admin pages and controllers.

- `___execute()`: Main action.
- `___executeEdit()`: Secondary action mapped to `/edit/`.

## Hooking System

Modules are the best place for hooks.

### Implementation

```php
public function init() {
    $this->addHookBefore('Pages::save', $this, 'myHook');
}

public function myHook(HookEvent $event) {
    $page = $event->arguments(0);
    // Custom logic before save
}
```

## Module Info

Must define `getModuleInfo()`. Use `singular => true` for services.
