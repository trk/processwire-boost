---
name: pw-module-development
description: "Use when building, structuring, or refactoring native backend modules for ProcessWire using PHP 8.4 and strict typing."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Core Module Development

This skill governs building native backend modules in ProcessWire. Modules extend `ProcessWire\WireData` and implement `ProcessWire\Module`.

## Documentation Reference (Source of Truth)

Before writing or modifying any ProcessWire module, inspect the core documentation:
- **Core CLI:** `php index.php docs get-text Module` and `php index.php docs methods-text WireHooks`
- **Core Markdown:**
  - Module Interface: `wire/core/Module/API.md`
  - Hook System: `wire/core/WireHooks/API.md`
  - Database Wrapper: `wire/core/WireDatabase/API.md`
  - Config & Paths: `wire/core/Config/API.md`

## Architecture Requirements

1. **Strict Typing:** Every file must begin with `declare(strict_types=1);` and `namespace ProcessWire;`.
2. **Inheritance & Interface:** Classes typically extend `WireData` and implement `Module` (or `ConfigurableModule` for settings).
3. **Scoped API Access:**
   - Inside classes, always access API variables via `$this->wire()->apiVar` (e.g. `$this->wire()->pages`, `$this->wire()->sanitizer`).
   - For multiple calls inside a loop/method, cache locally: `$pages = $this->wire()->pages;`.
   - Never use `$this->pages` directly (relies on slow magic getters and risks property collisions).
4. **Hook Conventions:**
   - Any method designed to be hookable must prefix its name with three underscores: `public function ___myHookableMethod()`.
   - Register hooks in `init()` (before page is rendered) or `ready()` (after API is fully initialized and current `$page` is set).
   - In hooks, access API variables via `$event->wire()->apiVar`.
5. **Autoload & Singular Flags:**
   - `autoload => true`: Loaded on every request. **Only** use if the module attaches global hooks needed across all requests.
   - `autoload => 'template=admin'`: Loaded only within the admin interface.
   - `singular => true`: Only one instance of the class exists in memory.

## Implementation Pattern (PHP 8.4+)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class CustomLogger extends WireData implements Module
{
	public function __construct(
		protected readonly string $logName = 'custom-logger',
		public int $defaultLimit = 10
	) {
		parent::__construct();
	}

	public static function getModuleInfo(): array
	{
		return [
			'title' => 'Custom Logger',
			'version' => 100,
			'summary' => 'Logs page state transitions for target templates.',
			'autoload' => 'template=admin',
			'singular' => true,
			'requires' => [
				'ProcessWire>=3.0.210',
				'PHP>=8.4.0',
			],
			'icon' => 'history',
		];
	}

	public function init(): void
	{
		$this->addHookAfter('Pages::saved', $this, 'hookPageSaved');
	}

	protected function hookPageSaved(HookEvent $event): void
	{
		/** @var Page $page */
		$page = $event->arguments(0);

		if ($page->template->name !== 'article') {
			return;
		}

		$log = $this->wire()->log;
		$user = $this->wire()->user;

		$log->save($this->logName, sprintf(
			'Page %d (%s) saved by user %s',
			$page->id,
			$page->title,
			$user->name
		));
	}

	/**
	 * Hookable custom method example
	 */
	public function ___processItem(string $item): bool
	{
		return true;
	}
}
```

## Database Access

ProcessWire's `$this->wire()->database` is a `WireDatabasePDO` wrapper (PDO-compatible). Always use prepared statements with parameter binding:

```php
$database = $this->wire()->database;
$query = $database->prepare('SELECT id, data FROM custom_table WHERE status = :status');
$query->bindValue(':status', 1, \PDO::PARAM_INT);
$query->execute();
$results = $query->fetchAll(\PDO::FETCH_ASSOC);
```

## Memory Management in Long-Running Operations

When processing large datasets in modules or CLI commands:
- Use `$pages->findMany("template=item")` for memory-safe chunked iteration.
- Call `$pages->uncacheAll()` periodically if using manual loops.
- Use `$pages->count($selector)` instead of `count($pages->find($selector))` to check totals without hydrating Page objects.

## Anti-Patterns to Avoid

- ❌ **Do not** use `$this->pages` or `wire('pages')` inside module classes. Use `$this->wire()->pages`.
- ❌ **Do not** make modules `autoload => true` unnecessarily. If a module only hooks the admin, use `autoload => 'template=admin'`. If it provides on-demand utility, leave `autoload => false`.
- ❌ **Do not** concatenate variables into SQL strings. Always use prepared statements via `$this->wire()->database->prepare()`.
- ❌ **Do not** swallow exceptions with empty catch blocks. Always log errors via `$this->wire()->log->error($e->getMessage())`.
