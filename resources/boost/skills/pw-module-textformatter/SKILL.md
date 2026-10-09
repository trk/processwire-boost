---
name: pw-module-textformatter
description: "Use when building Textformatter modules to securely and dynamically format string-based fields immediately prior to output rendering in ProcessWire."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Textformatter Module Architecture

A `Textformatter` module intercepts raw string data pulled from a Field (such as `FieldtypeTextarea` or `FieldtypeText`) and formats it dynamically in memory immediately before output rendering (when `$page->of()` is true). It does not alter stored database records.

## Documentation Reference (Source of Truth)

Before writing or modifying any Textformatter module, inspect the core files:
- **Core CLI:** `php index.php docs get-text TextformatterMarkdownExtra`
- **Core Base Class:** `wire/core/Module/Textformatter/Textformatter.php`
- **Core Examples:**
  - `wire/modules/Textformatter/TextformatterEntities.module`
  - `wire/modules/Textformatter/TextformatterStripTags.module`
  - `wire/modules/Textformatter/TextformatterMarkdownExtra/TextformatterMarkdownExtra.module`

## Architecture Requirements

1. **Inheritance:** Class must extend `ProcessWire\Textformatter`.
2. **Passing By Reference:** Value arguments (`&$str`, `&$value`) are modified directly **by reference**. Do not return a string; the method signature returns `void`.
3. **Primary Methods:**
   - `format(&$str)`: Formats generic string without page/field context (e.g. when called via `$sanitizer->format()`).
   - `formatValue(Page $page, Field $field, &$value)`: Formats within a page field render cycle. By default, core calls `$this->format($value)`.
4. **Configurable (Optional):** Implement `ProcessWire\ConfigurableModule` if options should be editable from the admin interface.

## Implementation Pattern (PHP 8.4+)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class TextformatterHighlightTag extends Textformatter implements ConfigurableModule
{
	public string $cssClass = 'highlighted';

	public static function getModuleInfo(): array
	{
		return [
			'title' => 'Textformatter: Highlight Tag',
			'version' => 100,
			'summary' => 'Replaces [[keyword]] tags with HTML <mark> tags prior to rendering.',
			'requires' => [
				'ProcessWire>=3.0.0',
				'PHP>=8.4.0',
			],
		];
	}

	/**
	 * Format given text string by reference.
	 *
	 * @param string $str
	 */
	public function format(&$str): void
	{
		if (!is_string($str) || $str === '') {
			return;
		}

		$class = $this->wire()->sanitizer->entities($this->cssClass);
		$str = preg_replace('/\[\[(.*?)\]\]/s', "<mark class=\"{$class}\">\\1</mark>", $str);
	}

	/**
	 * Format value in context of Page and Field.
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param string|mixed $value
	 */
	public function formatValue(Page $page, Field $field, &$value): void
	{
		$this->format($value);
	}

	/**
	 * Module configuration inputs for admin interface.
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields): void
	{
		/** @var InputfieldText $f */
		$f = $this->wire()->modules->get('InputfieldText');
		$f->name = 'cssClass';
		$f->label = $this->_('Default CSS Highlight Class');
		$f->val($this->cssClass);
		$inputfields->add($f);
	}
}
```

## Programmatic Invocation

```php
// Formatting a page field directly:
echo $page->body; // Runs assigned textformatters automatically if $page->of(true)

// Running formatter programmatically on arbitrary string:
/** @var TextformatterHighlightTag $formatter */
$formatter = $wire->modules->get('TextformatterHighlightTag');
$text = "Hello [[world]]";
$formatter->format($text);
echo $text; // "Hello <mark class="highlighted">world</mark>"
```

## Anti-Patterns to Avoid

- ❌ **Do not** return a string from `format()` or `formatValue()`. Always modify the reference parameter directly (`&$value`).
- ❌ **Do not** execute heavy SQL queries or HTTP requests inside `formatValue()`. Textformatters run every time a page field is rendered; slow operations will destroy page load speed.
- ❌ **Do not** modify the database inside a Textformatter. If persistent transformation on save is required, use a Fieldtype's `sleepValue()` or a `Pages::saveReady` hook.
