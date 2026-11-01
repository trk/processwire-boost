---
name: pw-module-fieldtype-inputfield
description: "Use when building custom database fieldtypes and their corresponding interface inputfields in ProcessWire."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Fieldtype and Inputfield Architecture

In ProcessWire, custom data types are decoupled into two distinct modules (scaffold via `php vendor/bin/wire make:module <BaseName> --type=fieldtype`):
1. **Fieldtype (Database & Persistence Layer):** Extends `Fieldtype`. Manages MariaDB/MySQL table schema (`getDatabaseSchema`), transforms raw DB rows into runtime values (`wakeupValue`), prepares values for database persistence (`sleepValue`), sanitizes values on assignment (`sanitizeValue`), and provides the blank value (`getBlankValue`).
2. **Inputfield (User Interface & Input Handling Layer):** Extends `Inputfield`. Renders the HTML form controls for the backend admin and frontend (`render`), and intercepts HTTP POST/GET inputs (`processInput`).

## Documentation Reference (Source of Truth)

Before creating or editing Fieldtypes or Inputfields, inspect the official core documentation:
- **Core CLI:** `php index.php docs get-text Fieldtype` and `php index.php docs get-text Inputfield`
- **Core Markdown:**
  - Fieldtype API: `wire/core/Fieldtype/API.md`
  - Inputfield API: `wire/core/Inputfield/API.md`
  - Field Class: `wire/core/Field/API.md`
- **First-Party Core Implementations:**
  - `wire/modules/Fieldtype/FieldtypeText/`
  - `wire/modules/Fieldtype/FieldtypeInteger/`
  - `wire/modules/Fieldtype/FieldtypeRepeater/`
  - `wire/modules/Inputfield/InputfieldText/`
  - `wire/modules/Inputfield/InputfieldForm/`

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
				'ProcessWire>=3.0.0',
				'PHP>=8.4.0',
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
		$schema['data'] = 'varchar(512) NOT NULL DEFAULT ""';
		$schema['keys']['data'] = 'KEY data (data)';
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

	public function wakeupValue(Page $page, Field $field, $value): string
	{
		if (empty($value)) {
			return $this->getBlankValue($page, $field);
		}
		return (string) $value;
	}

	public function sleepValue(Page $page, Field $field, $value): string
	{
		return $this->sanitizeValue($page, $field, $value);
	}

	/**
	 * Returns the companion Field class name for IDE settings autocompletion.
	 */
	public function getFieldClass(): string
	{
		return 'CustomField';
	}
}

// Load IDE companion class
require_once __DIR__ . '/CustomField.php';
```

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

	public function render(): string
	{
		$id = $this->attr('id');
		$name = $this->attr('name');
		$value = htmlspecialchars((string) $this->attr('value'), ENT_QUOTES, 'UTF-8');

		$out = "<div class='uk-inline uk-width-1-1'>";
		$out .= "<span class='uk-form-icon' uk-icon='icon: tag'></span>";
		$out .= "<input type='text' id='{$id}' name='{$name}' value='{$value}' class='uk-input' />";
		$out .= "</div>";

		return $out;
	}

	public function processInput(WireInputData $input): self
	{
		$name = $this->attr('name');
		if (!isset($input->$name)) {
			return $this;
		}

		$value = $this->wire()->sanitizer->text($input->$name);

		if ($value !== $this->attr('value')) {
			$this->attr('value', $value);
			$this->trackChange('value');
		}

		return $this;
	}
}
```

## Phase 3: IDE Companion Class (`CustomField.php`)

Every Fieldtype module directory must contain a companion `[Type]Field.php` class extending `Field`. This enables PHPStan/Intelephense autocompletion for configurable settings without magic property errors:

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

## Phase 4: Module Developer Documentation (`API.md`)

Provide an `API.md` in the module directory documenting developer usage:

```markdown
# FieldtypeCustom

Encapsulates custom string persistence and formatting.

## Value type
`string`

## Getting and setting values
\`\`\`php
$val = $page->get('my_custom_field');
$page->setAndSave('my_custom_field', 'Normalized string');
\`\`\`

## Selectors
\`\`\`php
$pages->find("my_custom_field*=keyword, limit=10");
\`\`\`

## Output / markup
\`\`\`php
echo $page->my_custom_field;
\`\`\`

## Notes
Stores data as `varchar(512)` in MariaDB/MySQL. Default blank value is empty string `""`.
```

## Anti-Patterns to Avoid

- ❌ **Do not** omit `sanitizeValue()`. It is called automatically on value assignment (`$page->my_field = 'val'`) and is the primary defense against corrupted data.
- ❌ **Do not** write raw unescaped values in `render()`. Always pass values through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- ❌ **Do not** hardcode database tables. Let ProcessWire create and manage field tables via `getDatabaseSchema()`.
