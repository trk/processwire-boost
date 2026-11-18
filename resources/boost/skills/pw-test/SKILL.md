---
name: pw-test
description: "Use when creating, scaffolding, or running Pest PHP unit/feature tests or Playwright browser E2E and WCAG accessibility specs for ProcessWire projects and modules via vendor/bin/wire make:test, vendor/bin/wire test, and vendor/bin/wire test:e2e."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Testing (`pw-test`)

Scaffold and execute **Pest PHP** unit/feature tests and optional **Playwright** browser E2E / WCAG 2.1 AA specs across a ProcessWire project and its installed modules.

## When to Use
- Writing unit or feature tests in PHP for ProcessWire templates, custom page classes, hooks, or modules (`wire test`).
- Writing browser E2E and accessibility (`@axe-core/playwright`) specs for ProcessWire Admin, custom `Process` workspaces, interactive client-rendered views, or frontend flows (`wire test:e2e`).

---

## Part 1: Pest PHP Testing (`wire test`)

### Discovery Architecture
`TestCommand` uses `FeatureDiscoverer::discoverPaths('tests')` to aggregate PHP test directories in a single run:
1. `/tests/` — Root project tests and `tests/Pest.php` bootstrap.
2. `/site/tests/Feature/` & `/site/tests/Unit/` — Site-level feature and unit tests.
3. `/site/modules/<ModuleName>/tests/Feature/` & `/site/modules/<ModuleName>/tests/Unit/` — Module-scoped tests for every **installed** module in `site/modules/` (resolving both direct folder matches and companion `*.module.php` directories such as `Blog` -> `ProcessBlog`).

When initialized via `vendor/bin/wire test`, `tests/Pest.php` boots ProcessWire (`index.php`), exposing `\ProcessWire\wire()`, `$pages`, `$sanitizer`, and all installed modules.

### Scaffolding & Running Pest Tests

```bash
# Site Feature test -> site/tests/Feature/AuthFlowTest.php
php vendor/bin/wire make:test AuthFlowTest

# Site Unit test -> site/tests/Unit/SlugParserTest.php
php vendor/bin/wire make:test SlugParser --unit

# Module Feature test -> site/modules/MyCustomApi/tests/Feature/ApiConnectorTest.php
php vendor/bin/wire make:test ApiConnector --module=MyCustomApi

# Run all discovered root, site, and installed module Pest tests
php vendor/bin/wire test

# Filter by test name or target path with structured JSON output
php vendor/bin/wire test --filter=AuthFlowTest --json
```

### Canonical Pest Test Example

Mark every PHP test file under `site/` with `<?php // __(file-not-translatable)` on line 1 so ProcessWire's `LanguagePorter` / `ProcessLanguageTranslator` scanner skips test files when searching for translatable strings:

```php
<?php // __(file-not-translatable)

declare(strict_types=1);

test('sanitizer strips HTML tags from single-line text', function (): void {
	$sanitizer = \ProcessWire\wire()->sanitizer;

	$clean = $sanitizer->text('<h1>Hello ProcessWire</h1>');

	expect($clean)->toBe('Hello ProcessWire');
});
```

---

## Part 2: Optional Playwright E2E Testing (`wire test:e2e`)

### Initialization & Discovery Architecture
`TestE2eCommand` (`vendor/bin/wire test:e2e`) provides an optional, UI-agnostic Playwright runner:
- **Zero-config initialization (`--init`):** Non-destructively scaffolds `playwright.config.ts` and `tests/e2e/{global-setup,global-teardown,helpers}.ts` (ephemeral `pw_e2e_<runId>` superuser login, `storageState`, `PW_BASE_URL` / `PW_ADMIN_URL` injection from ProcessWire `$config->httpHosts` and `$config->urls->admin`).
- **Installed-module discovery:** Discovers specs containing `*.spec.ts` across `/tests/e2e/`, `/site/tests/e2e/`, `/site/templates/tests/e2e/`, and **installed** `/site/modules/<Module>/tests/e2e/` directories.

### Scaffolding & Running E2E Specs

```bash
# Initialize Playwright config and tests/e2e/* helpers without overwriting existing files
php vendor/bin/wire test:e2e --init

# Site E2E spec -> site/tests/e2e/checkout-flow.spec.ts
php vendor/bin/wire make:test CheckoutFlow --e2e

# Module E2E spec -> site/modules/ProcessAnalytics/tests/e2e/analytics-dashboard.spec.ts
php vendor/bin/wire make:test AnalyticsDashboard --e2e --module=ProcessAnalytics

# Run E2E specs across site and all installed modules
php vendor/bin/wire test:e2e

# Run E2E specs only for one installed module (accepts folder name or module class name)
php vendor/bin/wire test:e2e --module=ProcessAnalytics
php vendor/bin/wire test:e2e --module=ProcessAnalytics --grep="Dashboard" --json
```

### Canonical Playwright Spec (`tests/e2e/helpers.ts`)

Generated E2E environments expose `getAdminUrl()`, `attachRuntimeErrorTracker()`, `runWirePhp<T>()`, `createIsolatedSuperuser()`, and `deleteIsolatedUser()` in `tests/e2e/helpers.ts`:

```ts
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { attachRuntimeErrorTracker, getAdminUrl } from '../../../../../tests/e2e/helpers';

test.describe('ProcessAnalytics: Dashboard', () => {
	test('renders dashboard without runtime or WCAG 2.1 AA violations', async ({ page }) => {
		const tracker = attachRuntimeErrorTracker(page);

		await page.goto(getAdminUrl('analytics/'));
		await expect(page.locator('body')).toBeVisible();

		const axe = await new AxeBuilder({ page })
			.withTags(['wcag2a', 'wcag2aa'])
			.analyze();
		expect(axe.violations).toEqual([]);

		tracker.assertNoErrors();
	});
});
```

---

## Anti-Patterns & Loophole Closing

- ❌ **Running `./vendor/bin/pest` or `npx playwright test` blindly when testing modules:** Direct runners bypass ProcessWire's `isInstalled()` module filtering and `$config` URL injection. Run `php vendor/bin/wire test` and `php vendor/bin/wire test:e2e`.
- ❌ **Hardcoding `/processwire/` or `/admin/` in Playwright specs:** ProcessWire admin URLs are customizable per site. Always resolve admin paths via `getAdminUrl('subpath/')` from `tests/e2e/helpers`.
- ❌ **Running `TRUNCATE TABLE session_login_throttle` in parallel E2E tests:** Delete only the specific ephemeral test username (`DELETE FROM session_login_throttle WHERE name = :u`) or use `createIsolatedSuperuser()` / `deleteIsolatedUser()`.
- ❌ **Using foreign framework test traits or HTTP helpers (`RefreshDatabase`, `$this->get()`, `actingAs()`):** Interact with `\ProcessWire\wire()->pages`, `\ProcessWire\wire()->users`, and module APIs directly, cleaning up created entities in `afterEach()` / `afterAll()`.

## Related Skills
- `pw-module-development`
- `pw-systematic-debugging`
