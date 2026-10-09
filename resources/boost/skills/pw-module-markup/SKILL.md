---
name: pw-module-markup
description: "Use when creating independent frontend rendering systems explicitly extending the ProcessWire Markup module ecosystem."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Markup Module Architecture

Markup modules in ProcessWire act as standalone HTML rendering engines and UI component generators (e.g. `MarkupAdminDataTable`, `MarkupPagerNav`). They encapsulate complex HTML generation away from template files, keeping templates clean and declarative (`$modules->get('MarkupPricingTable')->render($packages)`).

## Documentation Reference (Source of Truth)

Before creating or editing Markup modules, consult the core documentation:
- **Core CLI:** `php index.php docs get-text Module`
- **Core Markdown:** `wire/core/Module/API.md`
- **First-Party Implementations:**
  - `wire/modules/Markup/MarkupAdminDataTable/API.md`
  - `wire/modules/Markup/MarkupPagerNav/API.md`
- **Caching Reference:** `wire/core/WireCache/API.md`

## Architecture Requirements

1. **Inheritance:** Extends `ProcessWire\WireData` and implements `ProcessWire\Module`.
2. **Primary Render Method:** Must provide an explicit, strongly-typed public `render(...)` method returning string HTML.
3. **Escaping & Security:** All dynamic values must be escaped using `$this->wire()->sanitizer->entities()` or `htmlspecialchars()` to prevent XSS vulnerabilities.
4. **Caching Support (Optional):** Integrate with ProcessWire's `$this->wire()->cache` (`WireCache`) for expensive DOM generation.

## Implementation Pattern (PHP 8.4+)

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class MarkupPricingTable extends WireData implements Module
{
	public static function getModuleInfo(): array
	{
		return [
			'title' => 'Markup: Pricing Table Generator',
			'version' => 100,
			'summary' => 'Renders responsive pricing table components from PageArray datasets.',
			'requires' => [
				'ProcessWire>=3.0.0',
				'PHP>=8.4.0',
			],
		];
	}

	/**
	 * Render the pricing table from a PageArray of packages.
	 */
	public function render(PageArray $packages): string
	{
		if (!$packages->count()) {
			return '';
		}

		$sanitizer = $this->wire()->sanitizer;
		$out = "<div class='uk-grid uk-child-width-1-3@m uk-grid-match' uk-grid>";

		foreach ($packages as $pkg) {
			$title = $sanitizer->entities($pkg->title);
			$price = $sanitizer->float($pkg->get('price'));
			$summary = $sanitizer->entities($pkg->get('summary'));
			$url = $sanitizer->entities($pkg->url);

			$out .= "
			<div>
				<div class='uk-card uk-card-default uk-card-body uk-text-center'>
					<h3 class='uk-card-title'>{$title}</h3>
					<div class='uk-text-large uk-text-primary'>₺{$price}</div>
					<p class='uk-text-meta'>{$summary}</p>
					<a href='{$url}' class='uk-button uk-button-secondary'>" . $this->_('Select Plan') . "</a>
				</div>
			</div>";
		}

		$out .= "</div>";

		return $out;
	}

	/**
	 * Cached render with WireCache integration.
	 */
	public function renderCached(PageArray $packages, int $seconds = 3600): string
	{
		$cache = $this->wire()->cache;
		$cacheKey = "markup_pricing_" . md5($packages->implode(',', 'id'));

		return $cache->get($cacheKey, $seconds, fn() => $this->render($packages));
	}
}
```

## Template Invocation

```php
// In a template file (e.g. site/templates/pricing.php)
$packages = $pages->find("template=pricing_package, sort=sort");

/** @var MarkupPricingTable $pricingModule */
$pricingModule = $modules->get('MarkupPricingTable');
echo $pricingModule->renderCached($packages, 3600);
```

## Anti-Patterns to Avoid

- ❌ **Do not** put form POST submission handling or database mutations into a Markup module. Form handling belongs in a Process module, template controller, or dedicated service class.
- ❌ **Do not** render unescaped page fields directly into the HTML string. Always use `$sanitizer->entities()` or `htmlspecialchars()`.
- ❌ **Do not** hardcode external stylesheet links inside `render()`. Use `$config->styles->add(...)` during `init()` or document CSS requirements clearly.
