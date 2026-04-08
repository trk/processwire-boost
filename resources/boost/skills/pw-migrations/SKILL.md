# ProcessWire Schema Migrations

Use when creating, running, rolling back, or managing ProcessWire schema migrations via the CLI. Activate this skill whenever you need to add fields, templates, pages, roles, or modules through versioned migration files.

## Overview

ProcessWire Boost provides a migration system for managing schema changes declaratively. Migrations are timestamped PHP files stored in `site/migrations/` that return anonymous classes with `up()` and `down()` methods. State is tracked in the `wire_migrations` database table with batch numbering for grouped rollback.

## Migration Commands

| Command | Description |
|---------|-------------|
| `make:migration <name>` | Create a new migration file from a stub template |
| `migrate` | Run all pending migrations |
| `migrate:status` | Show the status of all migrations (applied/pending) |
| `migrate:rollback` | Rollback the last batch of migrations |
| `migrate:reset` | Rollback all applied migrations |
| `migrate:refresh` | Reset and re-run all migrations |
| `migrate:fresh` | Drop migration table and re-run all migrations |
| `migrate:install` | Create the `wire_migrations` tracking table |

## Creating Migrations

Use `make:migration` with the `--type` flag to scaffold from built-in stubs:

```bash
# Blank migration (default)
php vendor/bin/wire make:migration my_custom_change

# Create a new field
php vendor/bin/wire make:migration create_summary_field --type=create-field --field=summary --fieldtype=FieldtypeTextarea --label="Summary"

# Create a new template
php vendor/bin/wire make:migration create_article_template --type=create-template --template=article --label="Article"

# Attach a field to a template
php vendor/bin/wire make:migration attach_summary_to_article --type=attach-field --template=article --field=summary

# Create a page
php vendor/bin/wire make:migration create_blog_parent --type=create-page --template=basic-page --parent=/ --label="Blog"

# Create a role with permissions
php vendor/bin/wire make:migration create_editor_role --type=create-role --label="Editor"

# Install a module
php vendor/bin/wire make:migration install_seo_module --type=install-module --module=SeoMaestro
```

### Available Stub Types

| Type | Flag | Purpose |
|------|------|---------|
| `blank` | `--type=blank` | Empty migration scaffold |
| `create-field` | `--type=create-field` | Create a new ProcessWire field |
| `create-template` | `--type=create-template` | Create a template with fieldgroup and template file |
| `attach-field` | `--type=attach-field` | Attach an existing field to a template |
| `create-page` | `--type=create-page` | Create a page under a parent |
| `create-role` | `--type=create-role` | Create a role with permissions |
| `install-module` | `--type=install-module` | Install a ProcessWire module |

## Migration File Structure

Migrations are anonymous classes with `up()` and `down()` methods. The file is stored at `site/migrations/{timestamp}_{name}.php`:

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

return new class {

    public function up(): void
    {
        $fields = wire('fields');

        $field = new Field();
        $field->type = wire('modules')->get('FieldtypeText');
        $field->name = 'subtitle';
        $field->label = 'Subtitle';
        $fields->save($field);
    }

    public function down(): void
    {
        $field = wire('fields')->get('subtitle');
        if (!$field || !$field->id) {
            return;
        }

        $fieldgroups = $field->getFieldgroups();
        if ($fieldgroups->count() > 0) {
            $names = $fieldgroups->implode(', ', 'name');
            throw new WireException(
                "Cannot delete field 'subtitle' — attached to: {$names}. "
                . "Create a separate migration to detach it first."
            );
        }

        wire('fields')->delete($field);
    }
};
```

## Methods

### Running Migrations

```bash
# Run all pending migrations
php vendor/bin/wire migrate

# Run with step limit
php vendor/bin/wire migrate --step=3

# Dry run (preview without applying)
php vendor/bin/wire migrate --dry-run

# Force (skip confirmation)
php vendor/bin/wire migrate --force

# JSON output (for scripting)
php vendor/bin/wire migrate --json
```

### Rolling Back

```bash
# Rollback last batch
php vendor/bin/wire migrate:rollback

# Rollback last N migrations
php vendor/bin/wire migrate:rollback --step=2

# Rollback all applied migrations
php vendor/bin/wire migrate:reset

# Reset and re-run everything
php vendor/bin/wire migrate:refresh

# Drop table and re-run everything (destructive)
php vendor/bin/wire migrate:fresh
```

### Checking Status

```bash
# Show status of all migrations
php vendor/bin/wire migrate:status

# JSON output
php vendor/bin/wire migrate:status --json
```

## Best Practices

1. **One concern per migration.** Do not create a field and attach it to a template in the same migration. Use separate migrations so rollback is atomic.
2. **Always implement `down()`.** Every `up()` should have a corresponding reversible `down()` to enable clean rollback.
3. **Guard against data loss in `down()`.** Check for dependencies (pages using a template, fields attached to templates, users with a role) before deleting in `down()`.
4. **Use descriptive names.** Name migrations after what they do: `create_blog_template`, `attach_body_to_article`, `install_seo_module`.
5. **Run `pw_schema_read` first.** Before creating fields or templates, verify they do not already exist to prevent duplicate creation errors.
6. **Test with `--dry-run`.** Always preview pending migrations before applying in production.
7. **Migration order matters.** Create fields before attaching them to templates. Create templates before creating pages that use them.

## Database Schema

The migration system uses a `wire_migrations` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `migration` | VARCHAR(255) UNIQUE | Migration filename |
| `batch` | INT UNSIGNED | Batch number for grouped rollback |
| `applied_at` | TIMESTAMP | When the migration was applied |
