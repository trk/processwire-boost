---
name: pw-module-development
description: "Use when building, scaffolding, structuring, or refactoring any ProcessWire module — backend Module or ConfigurableModule services, Process admin controllers, Fieldtype and Inputfield pairs, Textformatter filters, FileValidatorModule upload scanners, or Markup renderers."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Module Development

Authoritative playbook for building, refactoring, and packaging ProcessWire 3 modules using PHP 8.3+ and strict typing. Scaffold modules via `php vendor/bin/wire make:module <ModuleName> --type=module|process|fieldtype|inputfield`.

## Mandatory Step 1: Specialized Module Type Routing

Every module obeys the **Universal Module Architecture** below. If your module belongs to one of ProcessWire's specialized module families, you **MUST** also read the matching sibling reference file in this skill's `references/` directory before writing code:

| Module Family | Base Class & Naming | Mandatory Reference File |
|---|---|---|
| **Fieldtype & Inputfield** | `Fieldtype<Base>` + `Inputfield<Base>` + `<Base>Field.php` | Read `references/fieldtype-inputfield.md` |
| **Process (Admin Controller)** | `Process<Name> extends Process` (`___execute*`, RBAC, admin page) | Read `references/process.md` (and `pw-admin-ui` for UI) |
| **Textformatter** | `Textformatter<Name> extends Textformatter` (`format(&$str): void`) | Read `references/textformatter.md` |
| **FileValidator** | `FileValidator<Name> extends FileValidatorModule` (`isValidFile($filename)`) | Read `references/filevalidator.md` |
| **Backend / Service / Markup** | `WireData implements Module` (and optional `ConfigurableModule`) | Covered directly in this file below |

## Source-of-Truth Core Documentation

Consult local ProcessWire documentation before assuming signatures:
- **Core CLI:** `php index.php docs get-text Module` and `php index.php docs methods-text WireHooks`
- **Core Markdown:** `wire/core/Module/API.md`, `wire/core/WireHooks/API.md`, `wire/core/WireDatabase/API.md`, `wire/core/Config/API.md`

## Universal Module Architecture

1. **Strict Typing & Formatting:** Start every PHP file with `declare(strict_types=1);` and `namespace ProcessWire;` (or `Vendor\<Module>` under `src/`), using **tabs** and curly braces `{ ... }` on all control structures.
2. **Context-Safe API Access:**
   - Inside module classes (`Wire`-derived), always access API variables via `$this->wire()->apiVar` (caching in a local variable when used repeatedly: `$pages = $this->wire()->pages;`).
   - Inside hook callbacks, use `$event->wire()->apiVar`. Never use `$this->pages` magic getters.
3. **Single-Point Namespace Autoloading (`WireClassLoader`):**
   - In multi-class modules, place internal classes in `src/` under `Vendor\<Module>` and register the namespace **once** inside the primary module's `__construct()` after `parent::__construct()`:
     `$this->wire()->classLoader->addNamespace('Vendor\\CustomLogger', __DIR__ . '/src/');`
   - Never scatter `require_once`, `class_exists`, or `class_alias` shims across module files.
4. **Autoload, Lazy Boot & Singular Flags:**
   - Default to `'autoload' => false` for on-demand/service/Markup modules, or `'autoload' => 'template=admin'` for admin-only hooks.
   - Only use `'autoload' => true` when hooks must run on frontend and backend requests, and keep `init()` / `ready()` lightweight by deferring heavy service, schema, or filesystem initialization lazily until a hook fires.
5. **Parent Signatures, `___` Hooks & `#pw-internal` Boundaries:**
   - Prefix custom hookable methods with `___` (`public function ___processItem(...)`) and document `@method` on the class PHPDoc.
   - Never narrow parent parameter types or drop optional parameter defaults when overriding core `Wire` / `Module` methods, and never depend on core methods tagged `#pw-internal`.

## Canonical Backend / Configurable Module Pattern (PHP 8.3+)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class CustomLogger extends WireData implements Module, ConfigurableModule
{
	public string $logChannel = 'custom-logger';

	public function __construct()
	{
		parent::__construct();
		$this->wire()->classLoader->addNamespace('Vendor\\CustomLogger', __DIR__ . '/src/');
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
				'PHP>=8.3.0',
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

		$log = $event->wire()->log;
		$user = $event->wire()->user;
		$log->save($this->logChannel, sprintf('Page %d (%s) saved by %s', $page->id, $page->title, $user->name));
	}

	public function getModuleConfigInputfields(InputfieldWrapper $inputfields): void
	{
		/** @var InputfieldText $f */
		$f = $this->wire()->modules->get('InputfieldText');
		$f->name = 'logChannel';
		$f->label = $this->_('Log Channel Name');
		$f->val($this->logChannel);
		$inputfields->add($f);
	}
}
```

## Markup Renderer Module Pattern (`Markup<Name>`)

`Markup*` modules (`WireData implements Module`, `'autoload' => false`) encapsulate reusable HTML rendering away from template files:
- Expose a typed `render(...): string` method escaping every dynamic value via `$this->wire()->sanitizer->entities()` or `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Cache expensive output via `$this->wire()->cache->get($cacheKey, $ttlSeconds, fn(): string => $this->render($items))`.
- Keep POST mutations and database writes out of `Markup` modules.

## Database, Memory, Dynamic Discovery & Lifecycle Hygiene

- **Prepared Statements & Transactions:** Always use `$this->wire()->database->prepare()` + `bindValue()`, `$database->escapeTable()` / `escapeCol()` for dynamic identifiers, and `beginTransaction()` / `commit()` / `rollBack()` around multi-step raw SQL writes.
- **Large Datasets:** Use `$pages->findMany($selector)` for chunked iteration and `$pages->count($selector)` instead of hydrating full `PageArray` results.
- **Safe Dynamic Plugin Loading:** Validate class names with regex, reject symlinks (`!is_link($file)`), verify `realpath($file)` stays inside `rtrim($realBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR`, and verify `ReflectionClass::isInstantiable()` + `isSubclassOf(ExpectedContract::class)` **before** instantiating.
- **Zero-Orphan `___install()` / `___uninstall()`:** Remove all module-created SQL tables (`DROP TABLE IF EXISTS`), permissions, `WireCache` namespaces, `pages_meta` keys, and `site/assets/` directories in `___uninstall()`.
- **Packaging Hygiene:** Keep `composer.json` constraints synchronized with `getModuleInfo()`. Ignore `.phpstan.cache`, `.phpunit.cache`, and `vendor/` in `.gitignore`, and mark `tests/`, `.github/`, `phpstan.neon`, and `phpunit.xml*` as `export-ignore` in `.gitattributes`.

## Anti-Patterns to Avoid

- ❌ **Do not** use `$this->pages` or `wire('pages')` inside module classes; always use `$this->wire()->pages`.
- ❌ **Do not** scatter `require_once` or duplicate `addNamespace()` calls across files; register `src/` once in `__construct()`.
- ❌ **Do not** set `'autoload' => true` unnecessarily or execute expensive queries inside `init()` on frontend requests.
- ❌ **Do not** leave orphaned tables, permissions, or `WireCache` keys behind in `___uninstall()`, or swallow exceptions silently.
