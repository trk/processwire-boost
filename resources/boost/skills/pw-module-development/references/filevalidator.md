# FileValidator Module Reference

ProcessWire automatically discovers modules whose class name starts with `FileValidator` and extends `ProcessWire\FileValidatorModule`, invoking them whenever files are uploaded to `InputfieldFile` / `InputfieldImage` or checked via `$sanitizer->validateFile($filename)`.

## Source-of-Truth Documentation
- **Core CLI:** `php index.php docs get-text FileValidatorModule`
- **Core Markdown & Base:** `wire/core/Module/FileValidatorModule/API.md`, `wire/core/Module/FileValidatorModule/FileValidatorModule.php`
- **Core Example:** `wire/modules/FileValidatorZip.module`

## Architecture Requirements
1. **Naming & Inheritance:** Class name must begin with `FileValidator` (e.g., `FileValidatorCSV`) and extend `ProcessWire\FileValidatorModule`.
2. **Extension Declaration:** Declare `'validates' => ['csv']` (lowercase file extensions) in `getModuleInfo()`.
3. **Signature Preservation:** Implement `protected function isValidFile($filename): bool` — keep `$filename` **untyped** in the PHP parameter list so it stays compatible with upstream `FileValidatorModule::isValidFile($filename)`. Return `true` when valid, or record a translated reason via `$this->error($this->_('...'))` and return `false`.
4. **Optional Field Context:** Inspect `$this->getPage()`, `$this->getField()`, and `$this->getPagefile()` when rules depend on the target field or template.

## Implementation Pattern (PHP 8.3+)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class FileValidatorCSV extends FileValidatorModule
{
	public static function getModuleInfo(): array
	{
		return [
			'title' => 'FileValidator: CSV Security Scanner',
			'version' => 100,
			'summary' => 'Scans uploaded CSV files for spreadsheet formula injection vectors.',
			'validates' => ['csv'],
			'singular' => false,
			'autoload' => false,
			'requires' => [
				'ProcessWire>=3.0.210',
				'PHP>=8.3.0',
			],
		];
	}

	/**
	 * @param string $filename Full path to the file on disk
	 */
	protected function isValidFile($filename): bool
	{
		if (!is_file($filename) || !is_readable($filename)) {
			$this->error($this->_('File not found or not readable.'));
			return false;
		}

		$handle = fopen($filename, 'rb');
		if ($handle === false) {
			$this->error($this->_('Unable to open file for validation.'));
			return false;
		}

		$lineCount = 0;
		while (($line = fgets($handle, 4096)) !== false && $lineCount < 50) {
			$trimmed = ltrim($line);
			if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
				fclose($handle);
				$this->error($this->_('CSV contains disallowed formula prefix characters.'));
				return false;
			}
			$lineCount++;
		}
		fclose($handle);

		return true;
	}
}
```

## Anti-Patterns to Avoid
- ❌ **Do not** add a `string` parameter type hint to `isValidFile($filename)` (violates upstream parent signature).
- ❌ **Do not** manually `unlink($filename)` inside `isValidFile()` — ProcessWire's upload handler deletes rejected files automatically when `isValidFile()` returns `false`.
- ❌ **Do not** hook `InputfieldFile::fileAdded` for file format validation when `FileValidatorModule` handles both form uploads and `$sanitizer->validateFile()` natively.
