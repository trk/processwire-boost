# PHP Rules for ProcessWire

- **Strict typing:** Declare `declare(strict_types=1);` at the top of every new PHP file.
- **Indentation:** Use **tabs, not spaces** across all PHP files to match ProcessWire core conventions.
- **Control structures:** Always use curly braces `{ ... }` for control structures (`if`, `elseif`, `else`, `foreach`, `for`, `while`), even for single-line bodies.
- **Explicit types:** Provide explicit parameter type hints, return types, and property types on all functions, methods, and classes (except where an upstream ProcessWire parent signature forbids widening/narrowing).
- **Strict comparisons:** Use `===` / `!==` (`=== null` over `!$var`, `in_array(..., true)`, `array_key_exists()` over `isset()` when `null` is a valid value).
- **Naming:** Use descriptive `camelCase` variable and method names (e.g., `$isPagePublished`, not `$p_pub`).
- **Copyright headers:** Keep copyright years current when modifying existing file headers.

## Constructors & Classes

- Use PHP 8+ constructor property promotion in `__construct()`.
- Omit empty `__construct()` methods that take no parameters and perform no initialization.

## Comments & PHPDoc

- Prefer PHPDoc blocks over inline comments; add inline comments only to explain non-obvious invariants or business constraints.
- Use PHPDoc array shapes (e.g., `@return array{id: int, name: string}`) and generics (`@var PageArray|ProductPage[] $items`) where native PHP types cannot express the structure.

## Enums

- Use backed or unit `enum` types for fixed sets of domain constants, with `TitleCase` case names.
