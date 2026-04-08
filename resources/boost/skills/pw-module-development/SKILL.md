---
name: pw-module-development
description: Use when building, structuring, or refactoring native backend modules for ProcessWire using PHP 8.4 and strict typing.
metadata:
  triggers:
    - processwire module
    - hook architecture
    - php 8.4
    - backend development
    - wire module creation
---

# ProcessWire Core Module Development (wire-module-development)

This skill dictates the principles of building **Modules** (not simple plugins) for the ProcessWire CMS ecosystem. It requires "deep thinking", "zero tolerance for structural flaws", adherence to "strict typing", and total integration with ProcessWire's core philosophy. It is tailored specifically for ProcessWire 3.x architectures and PHP 8.4 standards.

## Pre-Computation / Anti-Rationalization Check

Before writing any module code, run through this architectural checklist:

- **Is the Module Type Correct?** Should it be `autoload` (runs on every request), `singular` (single instance), or only initialized in the admin interface? Do not arbitrarily make modules `autoload` if they do not require global event hooks.
- **Hook vs Override:** ProcessWire's strength lies in its Hook system. Never modify core files. Have you precisely identified which method you are hooking into (e.g., `Pages::saveReady`)?
- **Global `wire()` vs Dependency Injection:** Within the module's scope, use `$this->pages` or `$this->wire('pages')`. Avoid utilizing the global `wire()` function inside module classes.
- **PHP 8.4 Syntax:** Have you implemented Constructor Property Promotion, `readonly` classes, and property type declarations? Is `declare(strict_types=1);` at the top of the file?
- **Autonomous Namespacing:** Will the module utilize its own `composer.json`? Has a dedicated `src/` folder been established for PSR-4 autoloading?

## Execution Phases

### Phase 1: Architectural Planning & Scaffolding

1. Create the module's root directory (e.g., `site/modules/MyCustomModule/`) and the primary file `MyCustomModule.module.php`.
2. The module class must implement the `ProcessWire\Module` interface (or extend `ProcessWire\WireData`). Depending on the use case, implement `Module` (standard) or `ConfigurableModule` (for graphical settings).
3. Draft the mandatory `public static function getModuleInfo()` method using modern array notation (including `version => 100`, title, summary, and constraints like `requires => ['ProcessWire>=3.0.210', 'PHP>=8.4.0']`).
4. If utilizing Composer within the module, require the `vendor/autoload.php` inside the module's `init()` method.

### Phase 2: PHP 8.4 & Strict Types Integration

*   Files must begin with `declare(strict_types=1);`.
*   Enforce comprehensive type hinting for all properties, arguments, and return types.
*   To create new hookable methods within the module, prefix the method name with three underscores (`___`): `public function ___myCustomMethod()`.

```php
<?php 

declare(strict_types=1);

namespace MyCustomModule;

use ProcessWire\Module;
use ProcessWire\WireData;
use ProcessWire\HookEvent;

class MyCustomModule extends WireData implements Module 
{
    // PHP 8.4 Property Promotion & Strict Typing
    public function __construct(
        protected readonly string $logName = 'my-custom-module',
        public int $defaultLimit = 10
    ) {}

    public static function getModuleInfo(): array 
    {
        return [
            'title' => 'My Custom Module',
            'version' => '1.0.0',
            'summary' => 'Executes custom business logic effectively.',
            'autoload' => true,
            'singular' => true,
            'requires' => [
                'ProcessWire>=3.0.210',
                'PHP>=8.4.0',
            ],
            'icon' => 'cogs'
        ];
    }
    
    public function init(): void 
    {
        // Module-scoped composer autoloading 
        $autoloader = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        // Attach system Hooks
        $this->addHookAfter('Pages::saveReady', $this, 'hookSaveReady');
    }

    protected function hookSaveReady(HookEvent $event): void 
    {
        $page = $event->arguments(0);
        
        if ($page->template->name !== 'my_target_template') {
            return;
        }
        
        // Execute business logic...
        $this->wire()->log->save($this->logName, "Page {$page->id} triggered.");
    }
}
```

### Phase 3: Database & Security (PDO & Sanitizer)

*   **Never trust user input.** Whether it originates from a URL or a POST payload, sanitize it thoroughly before utilization: `$this->wire()->input->post('email', 'email')` or `$this->wire()->sanitizer->text($string)`.
*   When executing raw queries (e.g., custom module tables), utilize the `$this->wire()->database` object, which is a native PDO instance. **Always use Prepared Statements.** Do not use string interpolation for SQL queries.
*   During module uninstallation (`___uninstall()`), ensure complete cleanup of custom database tables, cache files, and residual data.

```php
// Database Best Practice:
$database = $this->wire()->database;
$query = $database->prepare("SELECT id, data FROM custom_table WHERE status = :status");
$query->bindValue(':status', 1, \PDO::PARAM_INT);
$query->execute();
$results = $query->fetchAll(\PDO::FETCH_ASSOC);
```

### Phase 4: Inter-Module Communication & API Variables

*   Inside a class extending `WireData` or `Wire`, do not use `wire()` to fetch other objects. Use direct property access like `$this->pages`, `$this->modules`, `$this->sanitizer`, `$this->input`.
*   When attempting to access other modules, utilize `$this->modules->get('ModuleName')` and verify the instance against null, ensuring configuration and state correctly boot up prior to interaction.

## Essential Tools & Ecosystem

- `composer` (Only permitted when module-specific, isolated third-party logic is actually necessary).
- `wire tinker` (Command-line REPL for instantly observing ProcessWire code and directly interacting with API objects).

## Copy-Paste Prompts

(Pass these direct prompts to the agent to initiate workflows instantly)

**[Scaffolding - Core Module Skeleton]**
> "Generate a new ProcessWire module named `CustomLogger`. It should be configured as `autoload` and `singular`. Define a `Pages::saveReady` hook inside `init()`. The hook needs to log an entry (under `custom-logger` in ProcessWire system logs) exclusively for pages matching the `article` template. Strictly follow the wire-module-development skill protocols: use PHP 8.4 features, valid strict types, and robust class structures."

**[Database Integration - Automated Installer]**
> "Implement `___install()` and `___uninstall()` methodology for the module. Upon installation, structurally generate an InnoDB database table named `custom_log_table` featuring 3 columns: id, page_id, and message (utilize utf8mb4 charset). During removal, appropriately drop this table. Write highly secure PDO execution code without any interpolation."

**[Security Protocol - Input Sanitization]**
> "Develop a `processInput` method leveraging the ProcessWire Input API. Methodically pull 3 fields (`title`, `email`, `description`) received via POST. Filter and secure them utilizing the designated sanitizer components (`$sanitizer->email()`, `$sanitizer->text()`, etc.) and store the clean results in an output array."

## Core Anti-Patterns to Avoid

- `$pages->find("name={$_GET['name']}")` -> Severe Security Vuln! Selectors must not accept unrestrained inputs without applying Sanitizer.
- `\ProcessWire\wire('pages')` -> Calling the global function from within a module is a poor and computationally unnecessary pattern. Instead: `$this->wire()->pages` or `$this->pages`.
- Empty Try-Catch -> `try { ... } catch(\Exception $e) {}` -> Swallowing exceptions obscures debugging. Continually implement error logging: `$this->wire()->log->error($e->getMessage())`.
- Hard-coded strings in native language -> All user-facing strings must remain localized out of the box using `$this->_('English String')`. Translations are handled via the ProcessWire translation UI.

## Context Awareness (ProcessWire API Docs)

**CRITICAL RULE FOR ALL AI AGENTS:**
When you need to understand, use, or hook into a ProcessWire core class or module, you **MUST NEVER** guess or hallucinate the API methods.
- You **MUST** consult the local AI-optimized Markdown API documentation starting at `.llms/docs/index.md`.
- Navigate through the index, find the relevant class document (e.g. `.llms/docs/core/Page.md`), and use your file reading tools to read its methods, parameters, and hookable (🪝) events before writing any code.
