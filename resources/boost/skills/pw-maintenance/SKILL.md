---
name: pw-maintenance
description: "Use when toggling the application's maintenance mode (down/up) in ProcessWire and managing downtime configuration."
risk: moderate
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Maintenance Mode

This implements a managed maintenance mode for ProcessWire driven by CLI commands, mimicking Laravel's `artisan down` behavior. 

## When to Use
- You are about to run a heavily disruptive migration or seeding.
- The website needs to display a "demo/under construction" or "maintenance" message gracefully while you allow designated administrators (with a secret parameter) to continue browsing.

## Core Content

### How It Works
The `wire down` command writes a `down.json` payload into ProcessWire's `$config->paths->assets` directory. The `wire up` command deletes this file.

Because `processwire-console` operates independently of HTTP requests, **it cannot stop requests directly**. To make it work, the `site/init.php` file MUST be configured to intercept traffic when `down.json` exists.

### Integration (One-Time Setup in `site/init.php`)
To ensure the website goes down for visitors during maintenance, verify or place this snippet inside `site/init.php`:

```php
// Intercept down.json if exists
$downFile = $config->paths->assets . 'down.json';
if (file_exists($downFile) && php_sapi_name() !== 'cli') {
    $down = json_decode(file_get_contents($downFile), true);
    // Support secret bypassing (e.g. ?secret=dev123)
    $providedSecret = wire('input')->get('secret') ?: wire('session')->get('maintenance_secret');
    
    if (empty($down['secret']) || $providedSecret !== $down['secret']) {
        if (!empty($down['redirect'])) wire('session')->redirect($down['redirect']);
        http_response_code($down['status'] ?? 503);
        die('Site is under maintenance. Please check back soon.');
    } else {
        // Save the secret in session so they don't have to keep it in the URL
        if ($providedSecret === $down['secret']) {
            wire('session')->set('maintenance_secret', $down['secret']);
        }
    }
}
```

### Supported CLI Commands
- `wire down`
  - Safely stops the app for the public.
  - Options:
    - `--redirect=/some-url` - Where to push visitors.
    - `--status=503` - Explicit HTTP code.
    - `--secret=developerToken123` - Very critical. If passed, the developer visits `example.com/?secret=developerToken123` and can still use the site normally to verify fixes.
- `wire up`
  - Restores the application to live status.

## Anti-Patterns
- ❌ **Editing index.php:** ProcessWire updates overwrite `index.php`. Do not place the check logic there. Place it in `site/init.php` (earliest site hook).
- ❌ **Missing CLI check:** Always ensure `php_sapi_name() !== 'cli'` inside `site/init.php` or you will lock yourself out of the `wire` CLI commands while down!

## Related Skills
- [pw-expert](./pw-expert)
- [pw-migrations](./pw-migrations)
