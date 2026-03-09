# PHP Rules for ProcessWire

- Always use curly braces for control structures, even for single lines.
- Prefer strict typing: `declare(strict_types=1);` in all new files.
- Favor explicit return types for all functions and methods.
- Use explicit type hints for parameters in all method calls.

## Constructors

- Use PHP 8+ constructor property promotion in `__construct()`.
- Avoid empty `__construct()` methods without parameters unless private.

## Comments & Documentation

- Prefer PHPDoc blocks over inline comments.
- Do not use comments within code unless reflecting complex business logic.
- Use array shape type definitions (e.g., `array{id: int, name: string}`) when appropriate.

## Enums

- Use Enums for constant sets of data. Keys should be TitleCase.
