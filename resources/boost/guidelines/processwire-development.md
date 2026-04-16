# ProcessWire Advanced Development

ProcessWire provides a hooking system, custom module development, and schema management API. For full patterns and examples, activate the `pw-module-development` skill.

## Hooks

Place hooks in `site/ready.php` or Module `init()`/`ready()` methods:
- **`addHookBefore`**: Modify arguments or prevent original execution.
- **`addHookAfter`**: Read return value, log, or execute follow-up tasks.
- **Hookable Methods**: Any class method intended to be hookable must use the `___methodName()` triple-underscore prefix convention.

## Module Documentation

### API.md files
Each Fieldtype module directory should have an `API.md` covering usage of that module's Fieldtype (and related classes) from a developer's perspective.
Recommended structure (one entry per Fieldtype):
- `# FieldtypeClassName` — H1 is the class name, no "API" suffix
- One-line description
- `## Value type` — the PHP type returned
- `## Getting and setting values` — code examples
- `## Selectors` — selector usage with notes on non-obvious behavior
- `## Output / markup` — rendering examples
- `## Notes` — defaults, sanitization, DB column, compatible types

### [Type]Field.php classes (Fieldtype settings typing)
Each Fieldtype module should have a corresponding `[Type]Field.php` file (e.g. `TextField.php`, `IntegerField.php`) in the same directory:
- Extends `Field`
- Contains PHPDoc `@property` annotations for all configurable settings from both the Fieldtype and its Inputfield
- Fieldtype implements `getFieldClass()` returning the class name
- The Fieldtype module loads it (commonly via `require_once` at the bottom of the module file)

### Database access
- Always access as `$this->wire()->database` (property), not `$this->wire()->database()` (method call).

## Module Types

- **Process Modules**: Admin page URLs and GUI interfaces.
- **Fieldtype Modules**: Database schema — how data is stored/retrieved.
- **Inputfield Modules**: HTML form elements — how users input data.

**Namespaces**: Always declare `namespace ProcessWire;` or fully qualify (e.g., `\ProcessWire\wire('pages')`).
