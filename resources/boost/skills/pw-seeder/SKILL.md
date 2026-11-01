---
name: pw-seeder
description: "Use when creating, orchestrating, or running database seeders and Faker data population in ProcessWire via processwire-console."
risk: moderate
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Database Seeding

Populate development, staging, or initial application data using `Totoglu\Console\Database\Seeder` classes discovered from `site/seeders/` and `site/modules/*/seeders/`.

## When to Use
- Populating initial pages, roles, users, or taxonomy terms after running migrations.
- Generating realistic fixture data with `FakerPHP` (`$this->faker`).
- Orchestrating multiple seeders in sequence via `$this->call()`.

## Directory & Naming Conventions

- **Locations:**
  - Global seeders: `site/seeders/*Seeder.php` (default namespace `Site\Seeders`)
  - Module seeders: `site/modules/<ModuleName>/seeders/*Seeder.php` (default namespace `ProcessWire`)
- **Naming:** Class and file names must end with `Seeder` (e.g., `ArticlesSeeder` in `ArticlesSeeder.php`).
- **Base Class:** Extend `Totoglu\Console\Database\Seeder` and implement `public function run(): void`. The booted `ProcessWire` instance is available at `$this->wire`, and `$this->faker` holds a `\Faker\Generator` instance when `fakerphp/faker` is installed.

## Scaffolding & Implementing Seeders

```bash
# Global seeder in site/seeders/ArticlesSeeder.php
php vendor/bin/wire make:seeder ArticlesSeeder

# Module seeder in site/modules/Blog/seeders/ArticlesSeeder.php
php vendor/bin/wire make:seeder ArticlesSeeder --module=Blog
```

Implementation and orchestration pattern:

```php
<?php

declare(strict_types=1);

namespace Site\Seeders;

use ProcessWire\Page;
use Totoglu\Console\Database\Seeder;

final class ArticlesSeeder extends Seeder
{
	public function run(): void
	{
		$pages = $this->wire->pages;
		$sanitizer = $this->wire->sanitizer;
		$parent = $pages->get('/articles/');

		if (!$parent->id) {
			return;
		}

		for ($i = 1; $i <= 5; $i++) {
			$title = $this->faker !== null
				? $this->faker->sentence(4)
				: "Sample Article {$i}";
			$name = $sanitizer->pageName($title);

			if ($pages->has("parent={$parent->id}, name={$name}, include=all")) {
				continue;
			}

			$article = $pages->new([
				'template' => 'article',
				'parent' => $parent,
				'name' => $name,
				'title' => $title,
			]);

			if ($article instanceof Page && $article->id) {
				$article->setAndSave('summary', 'Seeded summary text.');
			}
		}
	}
}
```

### Orchestrating Child Seeders (`$this->call()`)

Use `$this->call()` inside a parent seeder to execute dependent seeders in deterministic order (`resolveSeederClass()` resolves FQCNs, `Site\Seeders\*`, `ProcessWire\*`, and discovered short class names, throwing `\RuntimeException` if a target seeder does not exist):

```php
<?php

declare(strict_types=1);

namespace Site\Seeders;

use Totoglu\Console\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
	public function run(): void
	{
		$this->call('CategoriesSeeder');
		$this->call('ArticlesSeeder');
	}
}
```

## Running Seeders (CLI)

```bash
# Interactive selection or run all discovered seeders
php vendor/bin/wire db:seed

# Run a specific seeder class without interactive prompt
php vendor/bin/wire db:seed --class=ArticlesSeeder --force

# Machine-readable JSON output (requires --force)
php vendor/bin/wire db:seed --class=ArticlesSeeder --force --json
```

## Anti-Patterns & Loophole Closing

- ❌ **Raw SQL inserts for Page data:** Always use `$this->wire->pages->new()` or `Page::save()` so ProcessWire populates `pages`, `pages_parents`, and field tables consistently and triggers hooks.
- ❌ **Non-idempotent seeders:** Always check `$pages->has(...)` before creating records so re-running `wire db:seed` does not create duplicate pages or throw duplicate-name exceptions.
- ❌ **Omitting `--force` with `--json`:** `db:seed` is a state-mutating command; when invoked with `--json`, always pass `--force`.

## Related Skills
- `pw-migrations`
- `pw-pages`
