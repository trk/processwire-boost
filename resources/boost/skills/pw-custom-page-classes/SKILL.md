---
name: pw-custom-page-classes
description: "Use when extending ProcessWire's Page, User, Role, or RepeaterPage classes in site/classes/ to build strongly-typed domain models for templates."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Custom Page Classes Playbook

Custom Page Classes map ProcessWire templates to strongly-typed PHP classes in `site/classes/` under `namespace ProcessWire;` when `$config->usePageClasses = true` is enabled in `site/config.php`.

## Documentation Reference (Source of Truth)

- Core CLI: `php index.php docs get-text Page` and `php index.php docs methods-text Page`
- Core Markdown: `wire/core/Page/API.md` and `wire/core/Pages/API.md`

## 1. Naming & Directory Mapping (`site/classes/`)

Convert the template name to `PascalCase` and append `Page`:
- Template `home` -> `site/classes/HomePage.php` (`class HomePage extends Page`)
- Template `blog-post` -> `site/classes/BlogPostPage.php` (`class BlogPostPage extends Page`)
- Universal fallback -> `site/classes/DefaultPage.php` (`class DefaultPage extends Page`)
- Core specialized types -> `UserPage extends User`, `RolePage extends Role`, `LanguagePage extends Language`, `QuotesRepeaterPage extends RepeaterPage`, `SeoFieldsetPage extends FieldsetPage`

## 2. Canonical Implementation Pattern

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

/**
 * Custom Page Class for the `blog-post` template.
 *
 * @property string $title
 * @property string $summary
 * @property string $body
 * @property Pageimages $images
 * @property PageArray|CategoryPage[] $categories
 * @property-read string $authorName
 * @method string renderSummary(int $maxLength = 160)
 */
class BlogPostPage extends Page
{
	public function getAuthorName(): string
	{
		$author = $this->createdUser;
		if (!$author || !$author->id) {
			return $this->_('Unknown Author');
		}

		$fullName = trim("{$author->get('first_name')} {$author->get('last_name')}");

		return $fullName !== '' ? $fullName : (string) $author->name;
	}

	/**
	 * Hookable method (prefix with ___ and document with @method in PHPDoc).
	 */
	public function ___renderSummary(int $maxLength = 160): string
	{
		$sanitizer = $this->wire()->sanitizer;
		$raw = (string) ($this->get('summary') ?: $this->get('body'));

		return $sanitizer->entities($sanitizer->truncate($raw, $maxLength));
	}

	public function get($key): mixed
	{
		if ($key === 'authorName') {
			return $this->getAuthorName();
		}

		return parent::get($key);
	}
}
```

## 3. Advanced Patterns

### Class-Targeted Hooks
With custom page classes enabled, attach hooks directly to the custom class instead of `Page::saveReady` + manual template checks:

```php
// In site/ready.php or a module's init()
$wire->addHookBefore('BlogPostPage::saveReady', function(HookEvent $event): void {
	/** @var BlogPostPage $post */
	$post = $event->object;
	if ($post->get('summary') === '') {
		$post->set('summary', $event->wire()->sanitizer->truncate((string) $post->get('body'), 160));
	}
});
```

### Custom Admin Page List Labels (`getPageListLabel()`)
Override `getPageListLabel()` to customize how pages render in the Admin Page Tree (always entity-encode dynamic text; basic inline HTML tags like `<b>` or `<span>` are allowed):

```php
public function getPageListLabel(): string
{
	$sanitizer = $this->wire()->sanitizer;
	$title = $sanitizer->entities((string) $this->getUnformatted('title'));
	$stock = (int) $this->get('stock');

	return "{$title} <span>({$stock} in stock)</span>";
}
```

### Shared Static Helper for Expensive Dependencies
Because hundreds of `Page` instances may be hydrated during a single `$pages->find()` query, never instantiate heavy API clients or query builders in a `Page` property per instance. Share a static helper instance instead:

```php
protected static ?ProductInventoryService $inventory = null;

protected function inventory(): ProductInventoryService
{
	if (self::$inventory === null) {
		self::$inventory = $this->wire(new ProductInventoryService());
	}

	return self::$inventory;
}
```

### Reuse Strategy Selection

| Requirement | Mechanism |
|---|---|
| Shared behavior + shared `instanceof` identity | Abstract base class (`abstract class BaseArticlePage extends Page`) |
| Polymorphic contract across unrelated templates | Interface (`interface BookableInterface`) |
| Shared helper methods without inheritance hierarchy | Trait (cannot be used with `instanceof`) |
| External cross-cutting side effects | Hooks in `site/ready.php` or a Module |

## 4. Anti-Patterns & Loophole Closing

- ❌ **Registering hooks inside a Custom Page class (`$this->addHook(...)` in `__construct` or methods):** A `Page` class is instantiated once per loaded row; registering hooks inside it attaches duplicate hooks for every loaded page. Always register hooks in `site/ready.php` or a singular `Module`.
- ❌ **Accessing `$this->pages` or `$this->sanitizer` directly:** Always use `$this->wire()->pages` and `$this->wire()->sanitizer`.
- ❌ **Accessing `$this->template` directly inside `Page` methods:** Inside `Page` subclasses, direct property access can hit protected internal state before lazy loading; use `$this->get('template')` if needed inside internal overrides.
- ❌ **Assuming Output Formatting (`$this->of()`) is always `true`:** Custom page methods may be called in CLI scripts, hooks, or admin screens where `$this->of()` is `false`. Escape output explicitly with `$this->wire()->sanitizer->entities()` in methods that return HTML.

## Related Skills
- `pw-pages`
- `pw-module-development`
