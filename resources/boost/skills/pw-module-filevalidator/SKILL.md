---
name: pw-module-filevalidator
description: "Use when creating, structuring, or debugging FileValidator modules in ProcessWire for upload and asset validation."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire FileValidator Module Architecture

ProcessWire natively discovers modules whose class name begins with `FileValidator` that extend `ProcessWire\FileValidatorModule`. The core automatically invokes them when files are uploaded to `InputfieldFile` / `InputfieldImage` or validated programmatically via `$sanitizer->validateFile($filename)`.

## Documentation Reference (Source of Truth)

Before writing or modifying any FileValidator module, inspect the core API:
- **Core CLI:** `php index.php docs get-text FileValidatorModule`
- **Core Markdown:** `wire/core/Module/FileValidatorModule/API.md`
- **Core Base Class:** `wire/core/FileValidatorModule.php`
- **Core Examples:** `wire/modules/FileValidator/FileValidatorImage.php`, `wire/modules/FileValidator/FileValidatorZip.php`

## Architecture Requirements

1. **Naming:** Class name must begin with `FileValidator` (e.g. `FileValidatorCSV`, `FileValidatorPDF`).
2. **Inheritance:** Class must extend `ProcessWire\FileValidatorModule`.
3. **Module Info:** Must define the `validates` array containing lowercase file extensions (e.g. `['csv']`, `['pdf']`, or `'/.*/'` for all extensions).
4. **Primary Method:** Must implement `isValidFile(string $filename): bool`.
   - Return `true` if the file passes validation.
   - Return `false` if the file fails validation.
   - Record user-facing failure reasons with `$this->error($this->_('Your message'))`.

## Implementation Pattern (PHP 8.4+)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

/**
 * FileValidatorCSV validates uploaded CSV files for formula injection risks.
 */
class FileValidatorCSV extends FileValidatorModule
{
	public static function getModuleInfo(): array
	{
		return [
			'title' => 'FileValidator: CSV Security Scanner',
			'version' => 100,
			'summary' => 'Scans uploaded CSV files for formula injection characters.',
			'validates' => ['csv'],
			'singular' => false,
			'autoload' => false,
			'requires' => [
				'ProcessWire>=3.0.0',
				'PHP>=8.4.0',
			],
		];
	}

	/**
	 * Validate a file on disk.
	 *
	 * @param string $filename Full path to the file on disk
	 * @return bool True if valid, false if invalid
	 */
	protected function isValidFile($filename): bool
	{
		if (!is_file($filename) || !is_readable($filename)) {
			$this->error($this->_('File not found or not readable.'));
			return false;
		}

		$handle = fopen($filename, 'rb');
		if (!$handle) {
			$this->error($this->_('Unable to open file for validation.'));
			return false;
		}

		// Check first lines for spreadsheet formula injection vectors
		$lineCount = 0;
		while (($line = fgets($handle, 4096)) !== false && $lineCount < 50) {
			$trimmed = ltrim($line);
			if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
				fclose($handle);
				$this->error($this->_('CSV contains disallowed formula prefix characters at beginning of row.'));
				return false;
			}
			$lineCount++;
		}
		fclose($handle);

		return true;
	}
}
```

## Context Awareness (Page, Field, Pagefile)

When ProcessWire validates a file belonging to a page field context, it automatically sets context properties accessible through public getters:
- `$page = $this->getPage();` (Page or NullPage)
- `$field = $this->getField();` (Field or null)
- `$pagefile = $this->getPagefile();` (Pagefile, Pageimage, or null)

Use these getters when validation rules depend on the page template or field settings:

```php
protected function isValidFile($filename): bool
{
	$field = $this->getField();
	if ($field && $field->name === 'secure_reports') {
		// Field-specific validation logic
	}
	return true;
}
```

## Programmatic Invocation

```php
// Validate via Sanitizer (invokes all matching FileValidators automatically)
$isValid = $sanitizer->validateFile('/path/to/upload.csv');
if (!$isValid) {
	foreach ($sanitizer->errors() as $error) {
		$wire->log->error("Upload error: {$error}");
	}
}

// Or run validator directly
/** @var FileValidatorCSV $validator */
$validator = $wire->modules->get('FileValidatorCSV');
if (!$validator->isValid('/path/to/upload.csv')) {
	$message = $validator->errors('last string');
}
```

## Anti-Patterns to Avoid

- ❌ **Do not** manually hook `InputfieldFile::fileAdded` to validate file types. Extend `FileValidatorModule` instead so ProcessWire core handles execution, error collection, and transaction aborts natively.
- ❌ **Do not** guess file extensions in code if `validates` array can filter them declaratively in `getModuleInfo()`.
- ❌ **Do not** unlink the file manually inside `isValidFile()`. ProcessWire's upload handler handles cleanup when validation returns `false`.
- ❌ **Do not** echo errors directly. Always call `$this->error($this->_('Translated error message'))`.
