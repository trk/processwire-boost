---
name: pw-test
description: "Use when creating, executing, or managing Pest tests within ProcessWire or ProcessWire modules, including Test-Driven Development (TDD) tasks."
risk: safe
source: processwire-boost
---

# ProcessWire Testing (pw-test)

## When to Use
- You are asked to write a test for a ProcessWire feature.
- You need to debug tests using Pest PHP.
- You are scaffolding new Modules and need to setup TDD capabilities.
- The user requests a "test run" via `wire test`.

## Core Concepts & Architecture

ProcessWire Console uses **Pest PHP** coupled with a powerful `FeatureDiscoverer`. This means tests are decentralized across the CMS but executed globally.

### Test Discovery Locations
Tests automatically execute if placed in one of three locations:
1. `/tests/`: Root-level generic tests.
2. `/site/tests/`: Project-specific or integration tests.
When you run `php vendor/bin/wire test`, the console discovers all active test folders and runs Pest across them simultaneously.

### Pest Initialization & Bootstrapping
When Pest is first initialized via `wire test`, the test engine automatically appends logic to `/tests/Pest.php` to boot ProcessWire:

```php
if (!class_exists('\\ProcessWire\\ProcessWire')) {
    require_once dirname(__DIR__) . '/index.php';
}
```
This is critical: **It exposes the full `wire()` API, `$pages`, `$sanitizer`, and global state to the isolated Pest test process.** You do not need to manually boot ProcessWire in your test files.


### Scaffolding Tests
Never create test files manually. Always use the scaffolding commands to ensure naming and path conventions are correct:

```bash
# Create a standard Feature test in the root site/tests/Feature/
php vendor/bin/wire make:test AuthFlowTest

# Create a Unit test in site/tests/Unit/
php vendor/bin/wire make:test UserParser --unit

# Scope the test specifically to a module
php vendor/bin/wire make:test ApiConnector --module=MyCustomApi
```

### Running Tests
Execute the tests directly using the console wrapper. Any native Pest arguments can be forwarded.

```bash
# Run all tests
php vendor/bin/wire test

# Run and filter by class or description
php vendor/bin/wire test --filter=AuthFlowTest

# Parallel execution
php vendor/bin/wire test --parallel
```

## Writing Tests

Always use Pest's native functional API.

```php
<?php
// site/modules/MyModule/tests/Feature/ExampleTest.php
declare(strict_types=1);

test('module handles sanitization correctly', function () {
    $sanitizer = \ProcessWire\wire('sanitizer');
    
    $clean = $sanitizer->text("<h1>Hello</h1>");
    
    expect($clean)->toBe('Hello');
});
```

## Anti-Patterns & Loophole Closing

- ❌ NEVER generate tests via manual file creation. *Rationalization:* "It's faster to just write the file." *Counter:* Doing so bypasses the `pest.stub` standardization and directory routing built into `make:test`.
- ❌ NEVER run `./vendor/bin/pest` directly if testing multi-module structures. *Rationalization:* "It's the standard Pest way." *Counter:* ProcessWire separates test files via `FeatureDiscoverer`. Using `pest` directly will only test the root `/tests` folder and will ignore active ProcessWire modules! Always use `php vendor/bin/wire test`.
- ❌ NEVER attempt to manually edit `composer.json` to load module tests via PSR-4. *Rationalization:* "PHPUnit needs autoloading for test classes." *Counter:* Pest does not require structural PSR-4 routing for simple closures, and the `TestCommand` explicitly passes module directories at execution time.
- ❌ NEVER write Laravel or Symfony-centric test logic (e.g., `RefreshDatabase` trait, HTTP assertions like `$this->get()`). *Rationalization:* "Pest is usually used with Laravel." *Counter:* This is ProcessWire. Use native `$pages->find()`, `$wire->input`, etc.

## Related Skills
- `pw-module-development`: Guidelines for building the actual modules you are testing.
- `pw-systematic-debugging`: Approaches for isolating code errors when tests fail.
