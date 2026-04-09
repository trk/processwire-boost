---
name: pw-queue
description: "Use when creating, dispatching, or interacting with Queue jobs in ProcessWire (processwire-console file-based queues)."
risk: safe
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Queue Management 

This specific project implements a background job queue system out-of-the-box via the `processwire-console` package using a lightweight database implementation (`queue_jobs` and `failed_jobs`).

## When to Use
- You need to run a time-consuming PHP task (like sending emails, talking to APIs, or processing images) in the background.
- You are asked to implement a Queue strategy in ProcessWire.
- You need to debug, list, retry, or clear failed background jobs using CLI.

## Core Content

### Queue Directory & Naming Guidelines
- **Locations**: All queue endpoints MUST be placed inside `site/queue/*Queue.php` (for global tasks) or `site/modules/[ModuleName]/queue/*Queue.php` (for module-scoped tasks).
- **Naming**: Class names MUST end with `Queue` (e.g., `SendEmailQueue`) and match their filenames (`SendEmailQueue.php`).
- **Inheritance**: The class MUST extend `Totoglu\Console\Queue\Queue`.

### Creating a Queue Endpoint
A basic queue class relies on a `handle()` method.

```php
<?php
declare(strict_types=1);

namespace Site\Queue;

use Totoglu\Console\Queue\Queue;

class SendEmailQueue extends Queue
{
    public function handle(array $payload): void
    {
        // 1. Unpack payload cautiously
        $to = $payload['to'] ?? null;
        $body = $payload['body'] ?? null;

        if (!$to || !$body) {
            throw new \Exception("Invalid payload");
        }

        // 2. Perform process safely
        $mail = \ProcessWire\wireMail();
        $mail->to($to)->subject('Background Mail')->body($body)->send();
    }
}
```

### Dispatching Jobs
To push jobs into the database from anywhere in ProcessWire:

```php
use Totoglu\Console\Queue\Queue;

// The Queue dispatcher automatically discovers classes by their basename.
Queue::push('SendEmailQueue', [
    'to' => 'user@example.com',
    'body' => 'Welcome to the system!'
]);
```

### Managing the Queue (CLI)
Always manage the active/failed queues using `wire` commands:

- `wire queue:table` - Use this FIRST to install/scaffold the database tables if they do not exist.
- `wire queue:work` - Starts the background daemon processor. If asked to "start the queue", run this.
- `wire queue:failed` - Lists jobs that failed completely after exhausting attempts.
- `wire queue:retry {id}` - Pushes a failed job back onto the main queue for processing.
- `wire queue:clear` - Purge all failed jobs.

## Anti-Patterns
- ❌ **Querying native tables explicitly:** Do not run manual `$database->exec('INSERT INTO queue_jobs...')`. Always use `Queue::push()`.
- ❌ **Naming mismatches:** Don't name your queue file `ProcessEmails.php`. It MUST be `ProcessEmailsQueue.php`.
- ❌ **Leaving out namespaces:** While auto-discovery attempts to gracefully find basenames, always try to assign a sensible namespace (e.g. `Site\Queue` or `ProcessWire`).

## Related Skills
- [pw-expert](./pw-expert)
- [pw-module-development](./pw-module-development)
