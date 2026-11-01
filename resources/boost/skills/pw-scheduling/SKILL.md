---
name: pw-scheduling
description: "Use when creating, configuring, or running scheduled cron tasks in ProcessWire via Totoglu\\Console\\Scheduling\\Task and Event."
risk: safe
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Task Scheduling

Define recurring cron tasks in PHP classes discovered from `site/schedule/` and `site/modules/*/schedule/`, and execute all due tasks from a single server crontab entry (`php vendor/bin/wire schedule:run`).

## When to Use
- Scheduling periodic maintenance, cache pruning, feed imports, or dispatching background queue jobs on a cron schedule.
- Replacing multiple raw server crontab entries with version-controlled PHP task classes.

## Directory & Naming Conventions

- **Locations:**
  - Global tasks: `site/schedule/*Task.php` (default namespace `Site\Schedule`)
  - Module tasks: `site/modules/<ModuleName>/schedule/*Task.php` (default namespace `ProcessWire`)
- **Naming:** Class and file names must end with `Task` (e.g., `ClearCachesTask` in `ClearCachesTask.php`).
- **Base Class:** Extend `Totoglu\Console\Scheduling\Task` and implement both `schedule(Event $schedule): void` and `handle(): void`. The `ProcessWire` instance is injected at `$this->wire`.

## Scaffolding & Implementing a Task

```bash
# Global task in site/schedule/ClearCachesTask.php
php vendor/bin/wire make:task ClearCachesTask

# Module-scoped task in site/modules/Analytics/schedule/SyncMetricsTask.php
php vendor/bin/wire make:task SyncMetricsTask --module=Analytics
```

Implementation pattern:

```php
<?php

declare(strict_types=1);

namespace Site\Schedule;

use Totoglu\Console\Queue\Queue;
use Totoglu\Console\Scheduling\Event;
use Totoglu\Console\Scheduling\Task;

final class ClearCachesTask extends Task
{
	public function schedule(Event $schedule): void
	{
		$schedule->dailyAt('04:00');
	}

	public function handle(): void
	{
		$this->wire->cache->maintenance();
		Queue::push('PruneExpiredSessionsQueue');
	}
}
```

## Available Schedule Frequencies (`Totoglu\Console\Scheduling\Event`)

| Method | Cron Expression | Meaning |
|---|---|---|
| `$schedule->everyMinute()` | `* * * * *` | Every minute |
| `$schedule->everyFiveMinutes()` | `*/5 * * * *` | Every 5 minutes |
| `$schedule->hourly()` | `0 * * * *` | Top of every hour |
| `$schedule->daily()` | `0 0 * * *` | Daily at `00:00` |
| `$schedule->dailyAt('13:30')` | `30 13 * * *` | Daily at specified `HH:MM` |
| `$schedule->weekly()` | `0 0 * * 0` | Sundays at `00:00` |
| `$schedule->monthly()` | `0 0 1 * *` | 1st day of each month at `00:00` |
| `$schedule->cron('15 */6 * * *')` | Custom 5-field cron | Arbitrary cron expression |

## Running the Scheduler

Configure a single system crontab entry to invoke `schedule:run` every minute:

```cron
* * * * * cd /path/to/project && php vendor/bin/wire schedule:run >> /dev/null 2>&1
```

Manual / JSON verification:

```bash
php vendor/bin/wire schedule:run --json
```

## Anti-Patterns & Loophole Closing

- ❌ **Heavy synchronous workloads inside `handle()`:** Because `schedule:run` executes due tasks sequentially in a single process, long-running loops in `handle()` can delay subsequent tasks. Dispatch heavy work to a queue job via `Queue::push()` inside `handle()`.
- ❌ **Omitting the `Task` suffix:** Files not ending in `Task.php` are ignored by task discovery.

## Related Skills
- `pw-queue`
- `pw-expert`
