# Fieldtype & Inputfield Module Reference

In ProcessWire, custom database field types are decoupled into two distinct modules (scaffold via `php vendor/bin/wire make:module Fieldtype<BaseName> --type=fieldtype` and `php vendor/bin/wire make:module Inputfield<BaseName> --type=inputfield`):
1. **Fieldtype (Database & Persistence Layer):** Extends `Fieldtype` (or `FieldtypeMulti` for multi-row `WireArray` values). Manages MariaDB/MySQL table schema (`getDatabaseSchema`), transforms raw DB rows into runtime values (`___wakeupValue`), prepares values for database persistence (`___sleepValue`), sanitizes values on assignment (`sanitizeValue`), and returns the blank value (`getBlankValue`).
2. **Inputfield (User Interface & Input Handling Layer):** Extends `Inputfield`. Renders HTML form controls (`___render`) and processes HTTP POST/GET inputs (`___processInput`).

## Source-of-Truth Documentation
- **Core CLI:** `php index.php docs get-text Fieldtype` and `php index.php docs get-text Inputfield`
- **Core Markdown:** `wire/core/Fieldtype/API.md`, `wire/core/Inputfield/API.md`, `wire/core/Field/API.md`
- **Core Examples:** `wire/modules/Fieldtype/FieldtypeText/`, `wire/modules/Fieldtype/FieldtypeInteger/`, `wire/modules/Fieldtype/FieldtypeRepeater/`, `wire/modules/Inputfield/InputfieldText/`

## Phase 1: The Fieldtype Module (`FieldtypeCustom.module.php`)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class FieldtypeCustom extends Fieldtype
{
	public static function getModuleInfo(): array
	{
		return [
			'title' => 'Custom Fieldtype',
			'version' => 100,
			'summary' => 'Encapsulates custom data types with database persistence.',
			'installs' => 'InputfieldCustom',
			'requires' => [
				'ProcessWire>=3.0.210',
				'PHP>=8.3.0',
			],
		];
	}

	public function getInputfield(Page $page, Field $field): Inputfield
	{
		/** @var InputfieldCustom $inputfield */
		$inputfield = $this->wire()->modules->get('InputfieldCustom');
		return $inputfield;
	}

	public function getDatabaseSchema(Field $field): array
	{
		$schema = parent::getDatabaseSchema($field);
		$len = $this->wire()->database->getMaxIndexLength();
		$schema['data'] = 'varchar(512) NOT NULL DEFAULT ""';
		$schema['keys']['data'] = "KEY `data` (`data`({$len}))";
		return $schema;
	}

	public function sanitizeValue(Page $page, Field $field, $value): string
	{
		return $this->wire()->sanitizer->text((string) $value);
	}

	public function getBlankValue(Page $page, Field $field): string
	{
		return '';
	}

	public function ___wakeupValue(Page $page, Field $field, $value): string
	{
		if ($value === null || $value === '') {
			return $this->getBlankValue($page, $field);
		}
		return (string) $value;
	}

	public function ___sleepValue(Page $page, Field $field, $value): string
	{
		return $this->sanitizeValue($page, $field, $value);
	}

	/**
	 * Returns the companion Field class name for IDE settings autocompletion.
	 * Keep the optional `array $a = []` parameter intact to match Fieldtype::getFieldClass().
	 *
	 * @param array<string, mixed> $a
	 */
	public function getFieldClass(array $a = []): string
	{
		return 'CustomField';
	}
}
```

> **Multi-column schema note:** When `getDatabaseSchema()` defines extra columns alongside `data` (e.g., `$schema['currency'] = 'CHAR(3) NOT NULL DEFAULT ""'`), `___sleepValue()` returns an associative `array<string, scalar|null>` keyed by column name (`['data' => ..., 'currency' => ...]`) and `___wakeupValue()` receives that raw `array` — widen both return/parameter types accordingly instead of narrowing to `: string`.

> **Autoloading note (`WireClassLoader`):** ProcessWire automatically locates `<BaseName>Field.php` in the same directory when its `<BaseName>` prefix matches `Fieldtype<BaseName>` (e.g., `FieldtypeCustom` -> `CustomField.php`) — no `require_once` is needed. If your companion class name does **not** match `<BaseName>Field`, register it in `__construct()` via `$this->wire()->classLoader->addClassMap(['MySpecialField' => 'MySpecialField.php'], __DIR__ . '/');`; otherwise `Fields::makeItem()` silently falls back to `Field`.

## Phase 2: The Inputfield Module (`InputfieldCustom.module.php`)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class InputfieldCustom extends Inputfield
{
	public static function getModuleInfo(): array
	{
		return [
			'title' => 'Custom Inputfield',
			'version' => 100,
			'summary' => 'Admin UI input control for FieldtypeCustom.',
			'requires' => 'FieldtypeCustom',
		];
	}

	public function ___render(): string
	{
		$id = htmlspecialchars((string) $this->attr('id'), ENT_QUOTES, 'UTF-8');
		$name = htmlspecialchars((string) $this->attr('name'), ENT_QUOTES, 'UTF-8');
		$value = htmlspecialchars((string) $this->attr('value'), ENT_QUOTES, 'UTF-8');

		$out = "<div class='uk-inline uk-width-1-1'>";
		$out .= "<span class='uk-form-icon' uk-icon='icon: tag'></span>";
		$out .= "<input type='text' id='{$id}' name='{$name}' value='{$value}' class='uk-input' />";
		$out .= "</div>";

		return $out;
	}

	public function ___processInput(WireInputData $input): self
	{
		$name = (string) $this->attr('name');
		if (!isset($input[$name])) {
			return $this;
		}

		$value = $this->wire()->sanitizer->text((string) $input[$name]);

		if ($value !== $this->attr('value')) {
			$this->attr('value', $value);
			$this->trackChange('value');
		}

		return $this;
	}
}
```

## Phase 3: IDE Companion Class (`CustomField.php`)

Every Fieldtype module directory should contain a companion `<BaseName>Field.php` class extending `Field` for PHPStan/IDE autocompletion on field settings:

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

/**
 * Companion class for FieldtypeCustom settings autocompletion.
 *
 * @property int $maxLength Maximum allowed characters
 * @property string $placeholder Display placeholder in input
 */
class CustomField extends Field
{
}
```

## Phase 4: Developer Documentation (`API.md`) & Compound Schema Extender

- Place an `API.md` file in the module directory (`# FieldtypeCustom`, `## Value type`, `## Getting and setting values`, `## Selectors`, `## Output / markup`, `## Notes`).
- If the Fieldtype stores nested subfields (similar to Repeaters or compound JSON schemas), expose a `Totoglu\Console\Boost\Schema\FieldSchemaExtender` in `.agents/schema/field-extenders.php` (see `pw-docs-architect`).

## Anti-Patterns to Avoid
- ❌ **Do not** omit `sanitizeValue()` — it runs automatically on `$page->my_field = $val` and protects runtime state before `save()`.
- ❌ **Do not** prefix `getDatabaseSchema()` with `___` — `Fieldtype::getDatabaseSchema(Field $field)` is not hookable in core.
- ❌ **Do not** write unescaped `$id`, `$name`, or `$value` attributes in `___render()` — always pass strings through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
