# PHP Rules for ProcessWire

- Always use curly braces for control structures, even for single lines.
- Prefer strict typing: `declare(strict_types=1);` in all new files.
- Prefer strict comparisons: `=== null` over `!$var`, `array_key_exists()` over `isset()` where null is valid.
- Favor explicit return types for all functions and methods.
- Use explicit type hints for parameters in all method calls.
- Indentation: **tabs, not spaces** (match ProcessWire core conventions).
- Keep copyright years in file headers current (when modifying existing headers).

## Constructors

- Use PHP 8+ constructor property promotion in `__construct()`.
- Avoid empty `__construct()` methods without parameters unless private.

## Comments & Documentation

- Prefer PHPDoc blocks over inline comments.
- Do not use comments within code unless reflecting complex business logic.
- Use array shape type definitions (e.g., `array{id: int, name: string}`) when appropriate.

## Enums

- Use Enums for constant sets of data. Keys should be TitleCase.
