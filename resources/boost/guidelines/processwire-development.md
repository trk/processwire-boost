# ProcessWire Advanced Development

ProcessWire provides a hooking system, custom module development, and schema management API. For full patterns and examples, activate the `pw-module-development` skill.

## Hooks

Place hooks in `site/ready.php` or Module `init()`/`ready()` methods:
- **`addHookBefore`**: Modify arguments or prevent original execution.
- **`addHookAfter`**: Read return value, log, or execute follow-up tasks.
- **Hookable Methods**: Any class method intended to be hookable must use the `___methodName()` triple-underscore prefix convention.

## Module Documentation

- **API.md**: Each module directory should have an `API.md` using an H1 header with the class name (no "API" suffix) and detailing value types, getting/setting, selectors, and markup.
- **[Type]Field.php**: Fieldtype modules should have a companion `[Type]Field.php` class with PHPDoc annotations covering settings, loaded via `getFieldClass()`.

## Module Types

- **Process Modules**: Admin page URLs and GUI interfaces.
- **Fieldtype Modules**: Database schema — how data is stored/retrieved.
- **Inputfield Modules**: HTML form elements — how users input data.

**Namespaces**: Always declare `namespace ProcessWire;` or fully qualify (e.g., `\ProcessWire\wire('pages')`).
