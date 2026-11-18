---
name: pw-docs-architect
description: "Use when creating or updating ProcessWire module READMEs, API.md reference files, [Type]Field.php IDE companion classes, architecture guides, or CLI command documentation."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Docs Architect

Author accurate, developer-grade documentation (`README.md`, `API.md`, `[Type]Field.php` PHPDoc companions, and `AGENTS.md`) for ProcessWire modules, packages, and projects.

## When to Use
- Writing or updating a ProcessWire module's `README.md` or `API.md`.
- Documenting a `Fieldtype` module and its `[Type]Field.php` IDE settings class.
- Documenting `vendor/bin/wire` CLI commands, URL hook endpoints, or MCP tools.

## Phase 1: Source-of-Truth Discovery

Inspect the implementation before writing a single line of documentation:
1. Run `pw_schema_read` and `pw_module_list` (MCP) or inspect `getModuleInfo()` and `configure()` in the target source files.
2. Check existing `API.md` conventions via `wire/core/Tools/WireApiDocs/HOWTO-API.md` or `php index.php docs get-text <Class>`.
3. Verify every public/hookable PHP method signature, parameter type, and default value directly from source or `ReflectionClass` (or `php index.php docs method <Class> <method>`), and every CLI command option from `configure()`.

## Phase 2: Document Templates by Artifact Type

### 1. Module `API.md` Reference (Upstream ProcessWire Standard)
Place `API.md` in the module root directory alongside the `.module.php` file:

````markdown
# FieldtypeMyCustom

Stores normalized decimal prices with currency code metadata.

## Value type

`MyCustomValue` (object) when formatted or unformatted.

## Getting and setting values

```php
// Reading
$price = $page->price;
echo $price->amount; // 125.50
echo $price->currency; // "EUR"

// Writing
$page->of(false);
$page->price->amount = 199.00;
$page->price->currency = 'EUR';
$page->save('price');
```

## Selectors

```php
// Query by amount (default column) or currency subfield
$items = $pages->find('template=product, price>=100, price.currency=EUR, limit=25');
```

## Output / markup

```php
echo $page->price; // Outputs formatted string e.g. "€199.00" when $page->of(true)
```

## Notes

- Database schema uses `DECIMAL(12,2)` for `data` and `CHAR(3)` for `currency`.
- Empty values return a blank `MyCustomValue` with `amount = 0.0`.
````

### 2. Core PHPDoc `#pw-` Tags (`HOWTO-API.md`)
When documenting classes or hookable methods consumed by ProcessWire's doc parser (`php index.php docs`):
- `#pw-summary <one-line summary>`
- `#pw-body = <detailed markdown body>`
- `#pw-group-<group-name>`
- `#pw-hooker` (marks hookable methods)
- `#pw-internal` (hides internal methods from public docs)

### 3. Fieldtype IDE Companion Class (`[Type]Field.php`)
Every `Fieldtype` module should include a `[Type]Field.php` file in the same directory (e.g., `MyCustomField.php` for `FieldtypeMyCustom`) extending `Field` with `@property` annotations for all field and inputfield settings, and return its class name from `getFieldClass(array $a = []): string`:

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

/**
 * Companion Field class for FieldtypeMyCustom settings autocompletion.
 *
 * @property string $defaultCurrency Three-letter ISO currency code (default: "EUR")
 * @property int $precision Decimal precision (default: 2)
 */
class MyCustomField extends Field
{
}
```

### 4. Module `README.md` Structure
Organize module `README.md` files in this exact order:
1. **Title & Summary:** One-paragraph explanation of what problem the module solves.
2. **Requirements:** PHP version (`>= 8.3`), ProcessWire version (`>= 3.0.210`), required modules.
3. **Installation:** `composer require ...` and `php vendor/bin/wire module:install --name=ModuleName`.
4. **Quick Start:** Copy-paste runnable example using valid ProcessWire API (`$this->wire()->modules->get(...)`, tabs, `declare(strict_types=1);`, curly braces).
5. **Configuration:** Markdown table of configurable settings (`Setting | Type | Default | Description`).
6. **Hooks & Events:** Hookable `___method()` signatures and example `$wire->addHookAfter(...)` listeners.
7. **CLI / MCP Integration (if applicable):** Exact `vendor/bin/wire` commands, arguments, options (`--json`, `--dry-run`, `--force`), and example outputs.
8. **Security & Permissions:** Required permissions, `$sanitizer` usage, and CSRF rules.

### 5. Module Boost Self-Discovery Standard (`trk/processwire-boost`)
Third-party ProcessWire modules in `site/modules/<Module>/` expose AI context using a **Single-Responsibility Contract** (zero duplication across files) automatically discovered by `wire boost:install` and `wire boost:update`:

| Artifact | Level | Reader & Load Timing | Single Responsibility |
|---|---|---|---|
| `API.md` | **Level 1 (Baseline)** | `php index.php docs` & Agent (on signature lookup) | **Upstream ProcessWire Standard.** Public class/method signatures, selectors, and settings dictionary (`# ClassName` H1). Zero tutorial prose. |
| `.agents/skills/pw-<slug>/SKILL.md` | **Level 2 (Recommended)** | Host-project **consumer** agent (on-demand) | Playbook teaching how to **use** the module in templates, hooks, or blueprints (`name: pw-<slug>`, `description: "Use when ..."`, `<= 500` lines; links to `API.md` for full signature tables). |
| `AGENTS.md` | **Optional (Contributor)** | Agent **modifying the module's own code** | Module **internal architecture** (`src/` layers, internal SQL tables, build/test commands). Indexed as a pointer via YAML frontmatter `description:` without bloating host `AGENTS.md`. |
| `.agents/guidelines/*.md` | **Exceptional (Default: None)** | Host-project agent (**every prompt — high cost!**) | Only global project-wide invariants (`<= 30` non-empty lines). Never put usage tutorials here. |
| `.agents/schema/field-extenders.php` | **Conditional** | `pw_schema_read` & `.agents/map.json` | Returns `list<class-string<FieldSchemaExtender>\|FieldSchemaExtender>` for compound `Fieldtype` modules with nested subfields. |

```php
<?php

declare(strict_types=1);

return [
	\Vendor\MyModule\Boost\MyCompoundFieldSchemaExtender::class,
];
```

Verify module documentation and Boost compliance before finishing:
`php vendor/bin/wire boost:validate --module=<Module> --strict --json`

## Quality Checklist

- [ ] Every PHP example uses **tabs**, `declare(strict_types=1);`, curly braces on all control structures, and context-safe API access (`$this->wire()->apiVar` or `$event->wire()->apiVar`).
- [ ] Every CLI example uses `php vendor/bin/wire <command>` and matches the real `configure()` definition.
- [ ] Zero placeholders (`TODO`, `TBD`, `"see source code"`).
- [ ] Zero foreign framework terminology (no non-ProcessWire ORM, CLI runner, templating engine, or service container references).

## Anti-Patterns & Loophole Closing

- ❌ **Duplicating full tutorial prose across `API.md`, `SKILL.md`, and `AGENTS.md`:** Keep `API.md` strictly as a signature/settings reference, `SKILL.md` for consumer workflows, and `AGENTS.md` for internal module architecture.
- ❌ **Putting usage tutorials into `.agents/guidelines/*.md`:** Guidelines load on every turn (`<= 30` non-empty lines max); move workflows to `.agents/skills/pw-<slug>/SKILL.md`.
- ❌ **Extending `Field` with the `Fieldtype*` class name (`class FieldtypeMyCustom extends Field`):** Companion IDE classes drop the `Fieldtype` prefix and append `Field` (`class MyCustomField extends Field`).

## Related Skills
- `pw-module-development`
- `pw-writing-skills`
