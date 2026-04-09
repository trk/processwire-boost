---
name: pw-scheduling
description: "Use when creating, scheduling, or managing time-based tasks in ProcessWire via the CronExpression wrapper."
risk: safe
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Task Scheduling

This system implements a Laravel-inspired Task Scheduler for ProcessWire via the `processwire-console` package using `dragonmantank/cron-expression`.

## When to Use
- You need to perform periodic cleanup, syncing, or queue dispatching without adding messy raw entries to the server's crontab.
- Instead of adding multiple cronjobs, you just add `wire schedule:run` to cron once and control specific schedules within PHP classes.

## Core Content

### Schedule Directory & Naming Guidelines
- **Locations**: Scheduled tasks MUST be placed inside `site/schedule/*Task.php` (global) or `site/modules/[ModuleName]/schedule/*Task.php` (module-scoped).
- **Naming**: Class names MUST end with `Task` (e.g., `CleanupLogsTask`) and match their filenames.
- **Inheritance**: The class MUST extend `Totoglu\Console\Scheduling\Task`.

### Creating a Task Endpoint
A task specifies what happens (`handle()`) and when it happens (`schedule()`).

```php
<?php
declare(strict_types=1);

namespace Site\Schedule;

use Totoglu\Console\Scheduling\Task;
use Totoglu\Console\Scheduling\Event;

class ClearCachesTask extends Task
{
    public function schedule(Event $schedule): void
    {
        // Define frequency
        $schedule->dailyAt('04:00'); 
    }

    public function handle(): void
    {
        $this->wire->cache->deleteAll();
        \Laravel\Prompts\info("Caches cleared by scheduler.");
    }
}
```

### Supported Frequencies via `Event` Wrapper
- `->everyMinute()`
- `->everyFiveMinutes()`
- `->hourly()`
- `->daily()`
- `->dailyAt('13:00')`
- `->weekly()`
- `->monthly()`
- `->cron('* * * * *')` (custom raw expressions)

### Managing Tasks (CLI)
- `wire make:task {Name}` - Autogenerates a class within `site/schedule/`.
- `wire schedule:run` - Runs all tasks that are currently due. (This is what you put in the server's `crontab` as `* * * * * cd /path-to-project && php vendor/bin/wire schedule:run`).

## Anti-Patterns
- ❌ **Heavy Logic in Scheduler:** Do not put 1000-row loops inside `handle()`. Instead, `handle()` should dispatch a Queue job (`Queue::push('SyncUsersQueue', [])`).
- ❌ **Missing the word Task:** `UpdateProfiles.php` is invalid. It must be `UpdateProfilesTask.php`.

## Related Skills
- [pw-expert](./pw-expert)
- [pw-queue](./pw-queue)
