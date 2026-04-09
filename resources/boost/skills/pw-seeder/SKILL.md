---
name: pw-seeder
description: "Use when creating, dispatching, or interacting with Database Seeding in ProcessWire (processwire-console file-based seeders)."
risk: moderate
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Database Seeding

This project implements a Laravel-style database seeding system for ProcessWire via the `processwire-console` package. It includes built-in auto-discovery of seeder classes and integration with `FakerPHP`.

## When to Use
- You need to populate the database with dummy or initial data during development or after migrations.
- You are asked to create a "Seeder" for a specific module or template structure.

## Core Content

### Seeder Directory & Naming Guidelines
- **Locations**: Seeders MUST be placed inside `site/seeders/*Seeder.php` (global) or `site/modules/[ModuleName]/seeders/*Seeder.php` (module-scoped).
- **Naming**: Class names MUST end with `Seeder` (e.g., `UsersSeeder`) and match their filenames (`UsersSeeder.php`).
- **Inheritance**: The class MUST extend `Totoglu\Console\Database\Seeder`.

### Creating a Seeder Endpoint
A basic seeder class overrides the `run()` method.

```php
<?php
declare(strict_types=1);

namespace Site\Seeders;

use Totoglu\Console\Database\Seeder;
use ProcessWire\Page;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Example: Seeding from JSON
        // $json = json_decode(file_get_contents(__DIR__ . '/users.json'), true);
        
        for ($i = 0; $i < 10; $i++) {
            $p = new Page();
            $p->template = 'user';
            $p->parent = $this->wire->users->getPage();
            
            // Note: $this->faker is null unless fakerphp/faker is installed via composer.
            $p->name = "user-{$i}";
            $p->addStatus(Page::statusActive);
            $p->save();
        }
    }
}
```

### Managing Seeders (CLI)
- `wire make:seeder {Name}` - Create a new seeder class in `site/seeders/`.
- `wire make:seeder {Name} --module=MyModule` - Create a seeder specifically inside a module.
- `wire db:seed` - Run all discovered seeders or a specific one with `--class=UsersSeeder`.

## Anti-Patterns
- ❌ **Direct SQL Inserts:** Avoid raw `$database->exec()` logic when `ProcessWire\Page` objects cover the requirements. Native API honors hooks and formatting.
- ❌ **Naming mismatches:** Don't name files `FakeUsers.php`. It MUST end with `Seeder.php` or it won't be auto-discovered.

## Related Skills
- [pw-expert](./pw-expert)
- [pw-migrations](./pw-migrations)
