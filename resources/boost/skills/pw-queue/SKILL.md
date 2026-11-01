---
name: pw-queue
description: "Use when creating, dispatching, processing, retrying, or debugging database-backed background queue jobs in ProcessWire via processwire-console."
risk: safe
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Queue Management

`trk/processwire-console` provides a database-backed job queue using the `queue_jobs` and `failed_jobs` tables (`QueueManager::TABLE_JOBS` and `QueueManager::TABLE_FAILED`), with automatic class discovery across `site/queue/` and `site/modules/*/queue/`.

## When to Use
- Offloading slow operations (sending emails, webhook calls, image/PDF generation, external API syncs) out of the HTTP request cycle.
- Creating `*Queue.php` job classes or dispatching jobs via `Totoglu\Console\Queue\Queue::push()`.
- Running workers (`wire queue:work`) or inspecting/retrying failed jobs (`wire queue:failed`, `wire queue:retry`).

## Directory & Naming Conventions

- **Locations:**
  - Global jobs: `site/queue/*Queue.php` (default namespace `Site\Queue`)
  - Module jobs: `site/modules/<ModuleName>/queue/*Queue.php` (default namespace `ProcessWire`)
- **Naming:** Class and file names must end with `Queue` (e.g., `SendEmailQueue` in `SendEmailQueue.php`).
- **Base Class:** Every job class must extend `Totoglu\Console\Queue\Queue` and implement `public function handle(array $payload): void`.

## Scaffolding & Implementing a Queue Job

Scaffold a new queue job via the CLI:

```bash
# Global queue job in site/queue/SendEmailQueue.php
php vendor/bin/wire make:queue SendEmailQueue

# Module-scoped queue job in site/modules/Newsletter/queue/SendEmailQueue.php
php vendor/bin/wire make:queue SendEmailQueue --module=Newsletter
```

Implementation pattern:

```php
<?php

declare(strict_types=1);

namespace Site\Queue;

use Totoglu\Console\Queue\Queue;

final class SendEmailQueue extends Queue
{
	/**
	 * @param array<string, mixed> $payload
	 */
	public function handle(array $payload): void
	{
		$to = (string) ($payload['to'] ?? '');
		$subject = (string) ($payload['subject'] ?? '');
		$body = (string) ($payload['body'] ?? '');

		if ($to === '' || $body === '') {
			throw new \InvalidArgumentException('Missing required email payload fields.');
		}

		$mail = \ProcessWire\wireMail();
		$mail->to($to)->subject($subject)->body($body)->send();
	}
}
```

## Dispatching Jobs

Ensure the queue tables exist first via `php vendor/bin/wire queue:table` (or `(new QueueManager())->ensureTables()`), then dispatch jobs with `Queue::push()` from templates, hooks, modules, or scheduled tasks:

```php
use Totoglu\Console\Queue\Queue;

// Immediate dispatch to 'default' queue channel
$jobId = Queue::push('SendEmailQueue', [
	'to' => 'user@example.com',
	'subject' => 'Welcome',
	'body' => 'Welcome to the platform!',
]);

// Delayed dispatch (e.g. 60 seconds delay on 'emails' channel)
$delayedJobId = Queue::push(
	queueClass: \Site\Queue\SendEmailQueue::class,
	payload: ['to' => 'user@example.com', 'subject' => 'Follow-up', 'body' => 'Checking in!'],
	queueName: 'emails',
	delay: 60
);
```

## CLI Queue Operations

| Command | Purpose |
|---|---|
| `php vendor/bin/wire make:queue <Name> [--module=<Mod>]` | Scaffold a `*Queue.php` class |
| `php vendor/bin/wire queue:table` | Create the `queue_jobs` and `failed_jobs` tables |
| `php vendor/bin/wire queue:work [--queue=default] [--once] [--sleep=3] [--tries=3] [--memory=128]` | Process pending jobs (runs continuously by default, or processes a single job and exits with `--once`) |
| `php vendor/bin/wire queue:failed [--json]` | List permanently failed jobs in `failed_jobs` |
| `php vendor/bin/wire queue:retry <id\|all> [--json]` | Re-queue a specific failed job ID or `all` failed jobs |
| `php vendor/bin/wire queue:clear [--force] [--json]` | Purge all records from `failed_jobs` |

## Anti-Patterns & Loophole Closing

- ❌ **Inserting into `queue_jobs` with raw SQL:** Always dispatch through `Queue::push()` so payloads and timestamps are serialized consistently.
- ❌ **Omitting the `Queue` suffix:** A file named `SendEmail.php` will not be discovered by `FeatureDiscoverer`. Always name the class and file `*Queue` (`SendEmailQueue.php`).
- ❌ **Passing unserializable objects in `$payload`:** Pass scalar IDs (such as `['page_id' => $page->id]`) and re-fetch the `Page` inside `handle()` via `\ProcessWire\wire()->pages->get((int) $payload['page_id'])`.

## Related Skills
- `pw-scheduling`
- `pw-expert`
