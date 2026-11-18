# Textformatter Module Reference

A `Textformatter` module transforms raw string field values (such as `FieldtypeText` or `FieldtypeTextarea`) dynamically in memory right before output rendering when `$page->of()` is `true`, without mutating the stored database value.

## Source-of-Truth Documentation
- **Core CLI:** `php index.php docs get-text TextformatterMarkdownExtra`
- **Core Base Class:** `wire/core/Module/Textformatter/Textformatter.php`
- **Core Examples:** `wire/modules/Textformatter/TextformatterEntities.module`, `wire/modules/Textformatter/TextformatterStripTags.module`

## Architecture Requirements
1. **Inheritance:** Class name must start with `Textformatter` and extend `ProcessWire\Textformatter` (optionally implementing `ConfigurableModule`).
2. **Pass-by-Reference Contract (`void` return):**
   - `public function format(&$str): void` — Mutates `$str` **in place by reference** without page/field context.
   - `public function formatValue(Page $page, Field $field, &$value): void` — Mutates `$value` **in place by reference** with `Page` and `Field` context (by default delegates to `$this->format($value)`).

## Implementation Pattern (PHP 8.3+)

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
				'ProcessWire>=3.0.210',
				'PHP>=8.3.0',
			],
		];
	}

	/**
	 * @param string $str
	 */
	public function format(&$str): void
	{
		if (!is_string($str) || $str === '') {
			return;
		}

		$sanitizer = $this->wire()->sanitizer;
		$class = $sanitizer->entities($this->cssClass);
		$str = preg_replace_callback(
			'/\[\[(.*?)\]\]/s',
			static fn(array $matches): string => "<mark class=\"{$class}\">" . $sanitizer->entities($matches[1]) . '</mark>',
			$str
		) ?? $str;
	}

	/**
	 * @param Page $page
	 * @param Field $field
	 * @param string|mixed $value
	 */
	public function formatValue(Page $page, Field $field, &$value): void
	{
		$this->format($value);
	}

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

## Anti-Patterns to Avoid
- ❌ **Do not** return a string from `format(&$str)` or `formatValue(...)` — always mutate the reference parameter directly (`void`).
- ❌ **Do not** run uncached SQL queries or HTTP calls inside `formatValue()` (executes on every field render), and never write back to the database inside a `Textformatter`.
