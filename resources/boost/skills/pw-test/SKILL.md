---
name: pw-test
description: "Use when creating, scaffolding, or running Pest PHP tests for ProcessWire projects and modules via vendor/bin/wire make:test and vendor/bin/wire test."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Testing (`pw-test`)

Scaffold and execute Pest PHP tests across the ProcessWire project and its modules using `vendor/bin/wire make:test` and `vendor/bin/wire test`.

## When to Use
- Writing unit or feature tests for ProcessWire templates, custom page classes, hooks, or modules.
- Running the test suite (`vendor/bin/wire test`) during Test-Driven Development (TDD) or verification.

## Test Discovery Architecture

`TestCommand` uses `FeatureDiscoverer::discoverPaths('tests')` to aggregate test directories from three locations and pass them to Pest in a single run:
1. `/tests/` — Root project tests and `tests/Pest.php` bootstrap.
2. `/site/tests/Feature/` & `/site/tests/Unit/` — Site-level feature and unit tests.
3. `/site/modules/<ModuleName>/tests/Feature/` & `/site/modules/<ModuleName>/tests/Unit/` — Module-scoped tests for every installed module in `site/modules/`.

When initialized via `vendor/bin/wire test`, `tests/Pest.php` automatically boots ProcessWire (`index.php`), exposing `\ProcessWire\wire()`, `$pages`, `$sanitizer`, and all installed modules to the test runner.

## Scaffolding Tests (`make:test`)

Always scaffold test files using `vendor/bin/wire make:test`:

```bash
# Site Feature test -> site/tests/Feature/AuthFlowTest.php
php vendor/bin/wire make:test AuthFlowTest

# Site Unit test -> site/tests/Unit/SlugParserTest.php
php vendor/bin/wire make:test SlugParser --unit

# Module Feature test -> site/modules/MyCustomApi/tests/Feature/ApiConnectorTest.php
php vendor/bin/wire make:test ApiConnector --module=MyCustomApi

# Module Unit test with JSON output -> site/modules/MyCustomApi/tests/Unit/TokenSignerTest.php
php vendor/bin/wire make:test TokenSigner --module=MyCustomApi --unit --json
```

## Running Tests (`wire test`)

`vendor/bin/wire test` forwards any additional arguments directly to `vendor/bin/pest`:

```bash
# Run all discovered root, site, and module tests
php vendor/bin/wire test

# Filter by test name or file
php vendor/bin/wire test --filter=AuthFlowTest

# Run a specific file or directory
php vendor/bin/wire test site/modules/MyCustomApi/tests/Feature/ApiConnectorTest.php

# Structured JSON result ({"ok": true, "data": ...} on pass; {"ok": false, "error": {"code": "TESTS_FAILED", ...}} on failure)
php vendor/bin/wire test --filter=AuthFlowTest --json
```

## Writing ProcessWire Pest Tests

```php
<?php

declare(strict_types=1);

test('sanitizer strips HTML tags from single-line text', function (): void {
	$sanitizer = \ProcessWire\wire()->sanitizer;

	$clean = $sanitizer->text('<h1>Hello ProcessWire</h1>');

	expect($clean)->toBe('Hello ProcessWire');
});
```

## Anti-Patterns & Loophole Closing

- ❌ **Running `./vendor/bin/pest` directly when testing modules:** Running `vendor/bin/pest` without arguments only executes `/tests` and ignores `/site/tests` and `/site/modules/*/tests`. Always run `php vendor/bin/wire test`.
- ❌ **Using Laravel test helpers (`RefreshDatabase`, `$this->get()`, `actingAs()`):** This is ProcessWire, not Laravel. Interact with `\ProcessWire\wire()->pages`, `\ProcessWire\wire()->users`, and module methods directly, and clean up any created test pages in `afterEach()`.
- ❌ **Manually editing root `composer.json` to autoload module test folders:** `wire test` discovers and executes module `tests/` directories automatically without polluting root PSR-4 autoload maps.

## Related Skills
- `pw-module-development`
- `pw-systematic-debugging`
