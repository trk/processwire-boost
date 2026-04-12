---
name: pw-expert
description: "Use when working with Laravel Prompts/Symfony Console command patterns, or when determining the correct, context-safe way to access ProcessWire API variables like $page, $pages, or $user."
risk: safe
source: processwire-boost
---

# ProcessWire Expert

This skill covers the engineering patterns used within `trk/processwire-console` (built on Symfony Console and Laravel Prompts) AND the definitive contextual rules for accessing ProcessWire API variables across different scopes.

## Part 1: Console & CLI Architecture

### When to Use
- Building new CLI commands for ProcessWire Console
- Working with Symfony Console `InputInterface` / `OutputInterface`
- Using Laravel Prompts (`text()`, `select()`, `confirm()`, `spin()`)
- Creating composer packages that integrate with ProcessWire Console
- Structuring command classes with proper architecture

### Do NOT Use When
- Building Laravel framework features (this is NOT a Laravel project)
- Creating migrations (use `pw-migrations`)

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
use function Laravel\Prompts	ext;
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

### Anti-Patterns

- ❌ Using Laravel framework features (Eloquent, Blade, etc.)
- ❌ Importing Laravel service providers or facades
- ❌ Using `request()`, `response()`, or Laravel helpers
- ❌ Raw `echo` instead of `$io->writeln()`
- ❌ Direct `$_POST`/`$_GET` access
- ❌ Missing `--json` and `--dry-run` support

---

## Part 2: ProcessWire API Variables Scope Management

ProcessWire exposes its core via "API variables" (e.g., `$page`, `$pages`, `$sanitizer`, `$user`, `$config`). Because ProcessWire can run in multi-instance modes and within varying PHP scopes, *how* you access these variables matters greatly for IDE autocompletion, performance, and context safety.

### 1. Contextual Best Practices

#### In Classes (Modules, Custom Page Classes)
**DO:** Use `$this->wire()->apiVar` (e.g., `$this->wire()->pages`).
**WHY:** Ensures you are referencing the exact ProcessWire instance the class was loaded into (safe for multi-instance). It also provides IDE type-hinting natively.
**AVOID:** 
- `$this->apiVar` (e.g. `$this->pages`) - relies on slow `__get()` magic methods and can conflict with class properties.
- `wire()->apiVar` - ambiguous in multi-instance environments when called from inside a class.

#### In Hooks (Ready.php, Modules)
**DO:** Use `$event->wire()->apiVar` (e.g., `$event->wire()->user`).
**WHY:** A hook is passed a `HookEvent $event` object. Pulling the API from the event guarantees you operate on the instance that triggered the hook.

#### In Procedural Functions
**DO:** Use `wire()->apiVar` (e.g., `wire()->sanitizer`).
**WHY:** Global scope. Using the `->` property access instead of the string function (e.g. `wire('sanitizer')`) tells the IDE exactly what object type is being returned.

#### In Template Files (`/site/templates/*.php`)
**DO:** Use native variables like `$page`, `$pages` or the Functions API like `page()`, `pages()`.
**WHY:** ProcessWire injects API variables directly into the template scope. 
- `$page->title` is convenient inside double quotes: `echo "<h1>$page->title</h1>";`.
- Provide IDE hints at the top of templates: `/** @var Page $page */` or `/** @var BlogPostPage $page */`.
- **Functions API (`pages()`)** is great because it is immune to scope loss (works inside `include` or local functions without globalizing) and is auto-type-hinted by the IDE. (Requires `$config->useFunctionsAPI = true;`).
**AVOID:** 
- `$this->pages` in templates. In a template file, `$this` is the `TemplateFile` renderer instance, not a generic scope.

### 2. Performance and Optimization

#### Localize in Loops
If you are repeatedly accessing an API variable in a loop (especially from within a class where you use `$this->wire()->...`), assign it to a local variable first to skip hundreds of redundant function calls.

```php
// Inefficient inside a class loop:
foreach($items as $item) {
    $this->wire()->sanitizer->text($item);
}

// Efficient:
$sanitizer = $this->wire()->sanitizer;
foreach($items as $item) {
    $sanitizer->text($item);
}
```

### 3. General Rules

#### Never overwrite API variables
ProcessWire has over 20 API variables (`$pages`, `$page`, `$user`, `$users`, `$config`, `$session`, `$sanitizer`, `$files`, `$mail`, etc). **NEVER** use these exact names for your own temporary variables (e.g. do not name your array of files `$files`), as it will destructively overwrite the API in the current scope.

#### Namespaces
Always ensure your PHP files start with:
```php
<?php 

declare(strict_types=1);

namespace ProcessWire;
```
This ensures standard classes (like `PageArray`, `WireData`) and functions (like `wire()`) resolve correctly without needing a leading backslash.

#### Injecting Custom API Variables
You can make a module, object, or string globally accessible by injecting it into the `wire` instance:
```php
// In init.php or a Module's init()
$wire->wire('myCustomService', new MyCustomService()); 

// Accessible everywhere:
$myCustomService = wire()->myCustomService;
```
