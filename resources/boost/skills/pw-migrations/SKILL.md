---
name: pw-migrations
description: "Use when creating, running, rolling back, or managing ProcessWire schema migrations via vendor/bin/wire make:migration and vendor/bin/wire migrate."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Schema Migrations

Manage ProcessWire schema changes (fields, templates, fieldgroups, pages, roles, permissions, and module installations) through timestamped PHP migration files in `site/migrations/` tracked in the `wire_migrations` database table.

## When to Use
- Creating, updating, or attaching fields and templates.
- Creating system pages, roles, permissions, or installing modules reproducibly across environments.
- Rolling back or inspecting applied schema changes via `vendor/bin/wire migrate:*`.

## CLI Migration Commands

All migration commands support `--json` for structured output (`migrate:rollback`, `migrate:reset`, `migrate:refresh`, and `migrate:fresh` require `--force` when used with `--json`).

| Command | Purpose |
|---|---|
| `php vendor/bin/wire make:migration <name> [--type=<stub>]` | Create a timestamped migration in `site/migrations/` |
| `php vendor/bin/wire migrate [--step=N] [--dry-run] [--json]` | Run all pending migrations |
| `php vendor/bin/wire migrate:status [--json]` | List applied and pending migrations with batch numbers |
| `php vendor/bin/wire migrate:rollback [--step=N] [--dry-run] [--force] [--json]` | Roll back the last batch (or last `N` migrations) |
| `php vendor/bin/wire migrate:reset [--force] [--json]` | Roll back all applied migrations |
| `php vendor/bin/wire migrate:refresh [--force] [--json]` | Roll back all migrations and re-run them |
| `php vendor/bin/wire migrate:fresh [--force] [--json]` | Drop `wire_migrations` tracking table and re-run all migrations |
| `php vendor/bin/wire migrate:install [--json]` | Explicitly ensure the `wire_migrations` table exists |

## Scaffolding Migrations (`make:migration`)

```bash
# Blank migration
php vendor/bin/wire make:migration add_custom_index

# Create a field
php vendor/bin/wire make:migration create_summary_field --type=create-field --field=summary --fieldtype=FieldtypeTextarea --label="Summary"

# Create a template (with matching fieldgroup and optional template file)
php vendor/bin/wire make:migration create_article_template --type=create-template --template=article --label="Article"

# Attach an existing field to a template
php vendor/bin/wire make:migration attach_summary_to_article --type=attach-field --template=article --field=summary

# Create a page
php vendor/bin/wire make:migration create_blog_root --type=create-page --template=basic-page --parent=/ --label="Blog"

# Create a role
php vendor/bin/wire make:migration create_editor_role --type=create-role --label="Editor"

# Install a module
php vendor/bin/wire make:migration install_seo_module --type=install-module --module=SeoMaestro
```

## Migration File Anatomy (`site/migrations/{timestamp}_{name}.php`)

Each migration file returns an anonymous class implementing `up(): void` and `down(): void`.

> [!IMPORTANT]
> Because migration files return an **anonymous class** (`return new class { ... };`) that does **not** extend `ProcessWire\Wire`, `$this->wire()` is **not** available inside migrations. Always use `wire()->fields`, `wire()->templates`, `wire()->pages`, `wire()->modules`, or `wire('fields')`.

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

return new class {
	public function up(): void
	{
		$fields = wire()->fields;
		$modules = wire()->modules;

		$field = $fields->get('subtitle');
		if (!$field) {
			$field = new Field();
			$field->type = $modules->get('FieldtypeText');
			$field->name = 'subtitle';
		}

		$field->label = 'Subtitle';
		$fields->save($field);
	}

	public function down(): void
	{
		$fields = wire()->fields;
		$field = $fields->get('subtitle');
		if (!$field || !$field->id) {
			return;
		}

		$fieldgroups = $field->getFieldgroups();
		if ($fieldgroups->count() > 0) {
			$names = $fieldgroups->implode(', ', 'name');
			throw new WireException(
				"Cannot delete field 'subtitle' while attached to: {$names}. Detach it first."
			);
		}

		$fields->delete($field);
	}
};
```

## Best Practices & Anti-Patterns

- **Run `pw_schema_read` first:** Check existing fields and templates before creating migrations to avoid collisions.
- **Make `up()` idempotent:** Check `$fields->get('name')` or `$templates->get('name')` before instantiating `new Field()` / `new Template()`.
- **Guard `down()` against data loss:** Check `$field->getFieldgroups()->count()` before deleting a field, and `$pages->count("template={$tpl->name}, include=all")` before deleting a template.
- ❌ **Calling `$this->wire()` inside a migration anonymous class:** Throws `Call to undefined method class@anonymous::wire()`. Always use `wire()->apiVar`.
- ❌ **Skipping `--force` when running `migrate:reset` or `migrate:fresh` with `--json`:** Always pass `--force` with `--json` on destructive commands.

## Related Skills
- `pw-writing-plans`
- `pw-seeder`
