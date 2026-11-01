---
name: pw-maintenance
description: "Use when toggling ProcessWire application maintenance mode (wire down / wire up) or configuring site/init.php to intercept HTTP requests during downtime."
risk: moderate
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire Maintenance Mode

Manage application downtime via `vendor/bin/wire down` and `vendor/bin/wire up`, paired with early HTTP interception in `site/init.php`.

## When to Use
- Before running disruptive schema migrations, bulk imports, or database restores.
- When putting the site into maintenance or demo mode while allowing developers to bypass downtime using a secret token.

## How It Works

`vendor/bin/wire down` writes a JSON payload to `$config->paths->site . 'down.json'` (`site/down.json`, mode `0600`, outside web-accessible `site/assets/` so the secret token cannot be fetched over HTTP). `vendor/bin/wire up` removes `site/down.json`.

Because CLI commands run in a separate process from web requests, `site/init.php` must check for `site/down.json` on non-CLI requests.

## HTTP Interception Setup (`site/init.php`)

Place this check in `site/init.php` (never in `index.php`, which is overwritten during ProcessWire core upgrades):

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

$downFile = $config->paths->site . 'down.json';
if (is_file($downFile) && PHP_SAPI !== 'cli') {
	/** @var array{redirect?: ?string, retry?: ?string, secret?: ?string, status?: int, time?: int}|null $down */
	$down = json_decode((string) file_get_contents($downFile), true);
	if (is_array($down)) {
		$secret = (string) ($down['secret'] ?? '');
		$providedSecret = (string) ($input->get('secret') ?: $session->get('maintenance_secret'));

		if ($secret !== '' && hash_equals($secret, $providedSecret)) {
			$session->set('maintenance_secret', $secret);
		} else {
			if (!empty($down['redirect'])) {
				$session->redirect((string) $down['redirect']);
			}
			if (!empty($down['retry'])) {
				header('Retry-After: ' . (int) $down['retry']);
			}
			http_response_code((int) ($down['status'] ?? 503));
			exit('Site is under maintenance. Please check back soon.');
		}
	}
}
```

## CLI Commands Reference

```bash
# Enter maintenance mode (503 Service Unavailable)
php vendor/bin/wire down

# Enter maintenance mode with secret bypass (?secret=devToken123), retry header, and custom status
php vendor/bin/wire down --secret=devToken123 --retry=60 --status=503

# Enter maintenance mode with redirect
php vendor/bin/wire down --redirect=/maintenance.html

# Machine-readable JSON output
php vendor/bin/wire down --secret=devToken123 --json

# Exit maintenance mode and restore live traffic
php vendor/bin/wire up --json
```

## Anti-Patterns & Loophole Closing

- ❌ **Reading `down.json` from web-accessible `site/assets/`:** `wire down` writes strictly to `$config->paths->site . 'down.json'` (`site/down.json`) so the secret token is never publicly downloadable. Always read `$config->paths->site . 'down.json'`.
- ❌ **Editing `index.php`:** ProcessWire core upgrades replace `index.php`. Always place request interception in `site/init.php`.
- ❌ **Omitting `PHP_SAPI !== 'cli'`:** Without the CLI guard in `site/init.php`, running `vendor/bin/wire up` or `vendor/bin/wire migrate` will terminate with the maintenance message and lock out CLI administration.

## Related Skills
- `pw-expert`
- `pw-migrations`
