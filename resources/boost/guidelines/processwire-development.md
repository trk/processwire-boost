# ProcessWire Advanced Development

ProcessWire provides a hooking system, custom module development, and schema management API. For full patterns and examples, activate the `pw-module-development` skill.

## Hooks

Place hooks in `site/ready.php` or Module `init()`/`ready()` methods:
- **`addHookBefore`**: Modify arguments or prevent original execution.
- **`addHookAfter`**: Read return value, log, or execute follow-up tasks.

## Module Types

- **Process Modules**: Admin page URLs and GUI interfaces.
- **Fieldtype Modules**: Database schema — how data is stored/retrieved.
- **Inputfield Modules**: HTML form elements — how users input data.

**Namespaces**: Always declare `namespace ProcessWire;` or fully qualify (e.g., `\ProcessWire\wire('pages')`).
