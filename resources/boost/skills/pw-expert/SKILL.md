---
name: pw-expert
description: "Use when building Symfony Console / Laravel Prompts CLI commands for trk/processwire-console, using InteractWithProcessWire helpers, or resolving context-safe ProcessWire API variable access across classes, hooks, templates, and closures."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Expert: CLI Architecture & API Scoping

Reference for authoring `trk/processwire-console` commands (`Symfony Console` + `Laravel Prompts` + `InteractWithProcessWire`) and accessing ProcessWire API variables safely across every runtime scope.

## Part 1: `trk/processwire-console` Command Architecture

### Command Skeleton & `InteractWithProcessWire` Trait

Every console command is a `final` class extending `Symfony\Component\Console\Command\Command` and using `Totoglu\Console\Traits\InteractWithProcessWire`:

```php
<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

final class ExampleActionCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('domain:action')
			->setDescription('Perform a domain action on a ProcessWire item')
			->addArgument('name', InputArgument::OPTIONAL, 'Target item name')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without saving')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output structured JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$force = (bool) $input->getOption('force');
		$dryRun = (bool) $input->getOption('dry-run');

		if ($asJson && !$force && !$dryRun) {
			return $this->outputJsonError(
				$output,
				'CONFIRMATION_REQUIRED',
				'Use --force when running this destructive command with --json.'
			);
		}

		$name = (string) ($input->getArgument('name') ?? '');
		if ($name === '' || !$this->isValidIdentifier($name)) {
			return $this->outputJsonError($output, 'INVALID_NAME', 'A valid identifier is required.');
		}

		if (!$asJson && !$force && !confirm("Apply changes to {$name}?", default: false)) {
			return Command::SUCCESS;
		}

		if (!$dryRun) {
			// Perform ProcessWire operation via \ProcessWire\wire()
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'name' => $name,
				'dryRun' => $dryRun,
			]);
		}

		info("Processed {$name} successfully.");
		return Command::SUCCESS;
	}
}
```

### `InteractWithProcessWire` Helper Reference

| Method | Signature & Behavior |
|---|---|
| `outputJsonSuccess` | `$this->outputJsonSuccess(OutputInterface $output, array $data = []): int` — Emits `{"ok": true, "data": ...}` and returns `Command::SUCCESS`. |
| `outputJsonError` | `$this->outputJsonError(OutputInterface $output, string $code, string $message): int` — Emits `{"ok": false, "error": {"code": ..., "message": ...}}` and returns `Command::FAILURE`. |
| `searchPage` | `$this->searchPage(string $label = 'Search for a page', bool $includeTrash = false): string\|int` — Interactive Laravel Prompts `search()` helper returning a selected Page ID or path. |
| `searchField` / `searchTemplate` / `searchRole` / `searchPermission` / `searchUser` / `searchInstalledModule` / `searchInstallableModule` / `searchLog` | Interactive `search()` helpers returning the selected item name (`string`). |
| `multiselectLogs` | `$this->multiselectLogs(string $label = 'Select logs to clear'): array<int, string>` — Interactive multi-select over available ProcessWire log channels. |
| `isValidIdentifier` | `$this->isValidIdentifier(string $name): bool` — Validates PHP/ProcessWire class or module identifiers (`/^[A-Za-z][A-Za-z0-9_]*$/`). |
| `parseKeyValueSets` | `$this->parseKeyValueSets(array\|string\|null $raw): array<string, string>` — Parses repeatable `--set="key=value"` options. |
| `loadStub` | `$this->loadStub(string $stubFile, array $replacements = []): ?string` — Loads a stub from `resources/stubs/` and applies placeholder replacements. |
| `scaffoldFeatureClass` | `$this->scaffoldFeatureClass(InputInterface $input, OutputInterface $output, string $suffix, string $subDir, string $defaultSiteNamespace, string $stubFile, string $promptLabel, string $promptPlaceholder): int` — Shared generator pipeline for `make:queue`, `make:seeder`, and `make:task`. |

### Registering Commands from Composer Packages

Third-party Composer packages can expose commands automatically via `composer.json` (discovered by `FeatureDiscoverer::discoverComposerCommands()`):

```json
{
	"extra": {
		"processwire-console": {
			"commands": [
				"Vendor\\Package\\Commands\\CustomCommand"
			]
		}
	}
}
```

---

## Part 2: Context-Safe ProcessWire API Variable Access

ProcessWire supports multi-instance execution and distinct runtime scopes. Always use the canonical access pattern for the current scope:

| Execution Scope | Canonical Access Pattern | Reason |
|---|---|---|
| `Wire`-derived class (`Module`, `Process`, `Fieldtype`, `Inputfield`, custom `Page` class) | `$this->wire()->pages` | Bound to the exact `ProcessWire` instance; provides IDE type inference and avoids slow `__get()` magic lookups on `$this->pages`. |
| Method or loop inside a `Wire`-derived class | `$pages = $this->wire()->pages;` (cached at top of method) | Avoids repeated method calls on every loop iteration. |
| Hook callback (`HookEvent $event`) | `$event->wire()->pages` | Bound to the `ProcessWire` instance that fired the event. |
| Template file (`site/templates/*.php`) | `$page`, `$pages` (or `page()`, `pages()` if `$config->useFunctionsAPI === true`) | Directly injected into template scope; add `/** @var Page $page */` (or custom page class) at the top of the file. |
| Standalone function, CLI command, or anonymous migration class | `\ProcessWire\wire()->pages` or `wire('pages')` | Anonymous migration classes (`return new class { ... }`) do not extend `Wire`, so `$this->wire()` does not exist on them. |

### Critical Scoping Rules

1. **Never overwrite API variable names:** Never name local variables `$pages`, `$page`, `$user`, `$users`, `$input`, `$files`, `$config`, `$session`, `$sanitizer`, `$log`, `$cache`, `$database`, `$fields`, `$templates`, or `$modules` in template or global scope.
2. **Database property access:** Always access `$this->wire()->database` as a property, never `$this->wire()->database()` as a method call.
3. **Wired `NullPage` creation:** Always call `$this->wire()->pages->newNullPage()` rather than `new NullPage()`.
4. **Registering custom API variables:** Inject shared services onto the `ProcessWire` instance in `site/init.php` or a module's `init()` via `$this->wire()->wire('myService', $serviceInstance)`.

## Anti-Patterns & Loophole Closing

- ❌ **Using Laravel framework classes (`Illuminate\*`, Eloquent, facades):** `trk/processwire-console` uses only `laravel/prompts` and `symfony/console` on top of ProcessWire core.
- ❌ **Allowing destructive `--json` commands without `--force`:** When `--json` is passed to a destructive command (`delete`, `detach`, `uninstall`, `trash`, `purge`, `rollback`, `reset`, `refresh`, `fresh`, `restore`, `clear`, `seed`), require `--force` and return `$this->outputJsonError($output, 'CONFIRMATION_REQUIRED', ...)` if omitted.
- ❌ **Calling `$this->pages` inside modules or custom page classes:** Always use `$this->wire()->pages`.
