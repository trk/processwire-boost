---
name: pw-expert
description: "Use when working with Laravel Prompts, Symfony Console, or composer package patterns within ProcessWire Console. NOT for Laravel framework development."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Expert

This skill covers the engineering patterns used within `trk/processwire-console` — which is built on Symfony Console and Laravel Prompts, NOT the Laravel framework itself.

## When to Use

- Building new CLI commands for ProcessWire Console
- Working with Symfony Console `InputInterface` / `OutputInterface`
- Using Laravel Prompts (`text()`, `select()`, `confirm()`, `spin()`)
- Creating composer packages that integrate with ProcessWire Console
- Structuring command classes with proper architecture

## Do NOT Use When

- Building Laravel framework features (this is NOT a Laravel project)
- Working on ProcessWire templates or modules (use `pw-module-development`)
- Creating migrations (use `pw-migrations`)

## Architecture Principles

### Command Structure

Every command follows this pattern:

```php
<?php

declare(strict_types=1);

namespace Totoglu\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MyCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('domain:action')
            ->setDescription('Brief description of what this command does')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without changes')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $json = (bool) $input->getOption('json');
        $dryRun = (bool) $input->getOption('dry-run');

        // Always access ProcessWire API via wire()
        $pages = \ProcessWire\wire('pages');

        // ... command logic ...

        return Command::SUCCESS;
    }
}
```

### Key Conventions

- `final class` — no inheritance allowed
- `declare(strict_types=1)` — always
- `--json` flag for machine-readable output
- `--dry-run` flag for safe preview
- `--force` flag to skip confirmation
- All inputs via `InputInterface` — never `$_GET`/`$_POST`
- All outputs via `SymfonyStyle` — never raw `echo`

### Laravel Prompts Usage

Used for interactive input, NOT for Laravel framework features:

```php
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

// Interactive text input
$name = text(
    label: 'Field name',
    placeholder: 'my_field',
    required: true,
    validate: fn(string $value) => match (true) {
        strlen($value) < 2 => 'Name must be at least 2 characters',
        !preg_match('/^[a-z][a-z0-9_]*$/', $value) => 'Invalid field name format',
        default => null,
    }
);

// Selection from options
$type = select(
    label: 'Field type',
    options: ['FieldtypeText', 'FieldtypeTextarea', 'FieldtypePage'],
);

// Confirmation for destructive operations
if (!$force && !confirm('Delete this field?', default: false)) {
    return Command::SUCCESS;
}

// Spinner for long operations
$result = spin(fn() => $pages->find($selector), 'Searching pages...');
```

### Composer Package Discovery

External packages can register commands via `composer.json`:

```json
{
    "extra": {
        "processwire-console": {
            "commands": [
                "Vendor\\Package\\Commands\\MyCommand"
            ]
        }
    }
}
```

## Anti-Patterns

- ❌ Using Laravel framework features (Eloquent, Blade, etc.)
- ❌ Importing Laravel service providers or facades
- ❌ Using `request()`, `response()`, or Laravel helpers
- ❌ Raw `echo` instead of `$io->writeln()`
- ❌ Direct `$_POST`/`$_GET` access
- ❌ Missing `--json` and `--dry-run` support
