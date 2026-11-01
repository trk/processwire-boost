---
name: pw-url-routing
description: "Use when registering ProcessWire URL/Path hooks ($wire->addHook('/path/...')) to build virtual routes, JSON API endpoints, or dynamic redirects without physical pages."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire URL/Path Hooks (`pw-url-routing`)

Register URL/Path hooks in `site/init.php` (recommended, runs before page rendering) or `site/ready.php`, or inside a module's `init()` method to handle virtual paths and JSON endpoints without creating templates or pages in the admin tree.

## Documentation Reference (Source of Truth)

- Core CLI: `php index.php docs get-text WireHooks`
- Core Markdown: `wire/core/WireHooks/API.md`

## 1. Route Patterns & Argument Extraction

### Exact Routes & JSON Responses
Returning an `array` from a URL hook automatically sets `Content-Type: application/json` and encodes the payload as JSON:

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

$wire->addHook('/api/health', function(HookEvent $event): array {
	return [
		'ok' => true,
		'timestamp' => time(),
	];
});
```

### Named Parameters (`{param}`) & Inline Regex Constraints (`(name:pattern)`)

```php
// Named parameter: /api/products/{sku}
$wire->addHook('/api/products/{sku}', function(HookEvent $event): array|bool {
	$sanitizer = $event->wire()->sanitizer;
	$pages = $event->wire()->pages;

	$sku = $sanitizer->selectorValue((string) $event->arguments('sku'));
	if ($sku === '') {
		return false; // Triggers ProcessWire 404 response
	}

	$product = $pages->findOne("template=product, sku={$sku}");
	if (!$product->id || !$product->viewable()) {
		return false;
	}

	return [
		'id' => $product->id,
		'title' => (string) $product->title,
		'sku' => (string) $product->get('sku'),
		'url' => $product->url,
	];
});

// Constrained parameter: only matches numeric IDs
$wire->addHook('/orders/(id:[0-9]+)', function(HookEvent $event): Page|bool {
	$orderId = (int) $event->arguments('id');
	$order = $event->wire()->pages->findOne("template=order, id={$orderId}");

	return $order->id ? $order : false;
});
```

## 2. Return Value Semantics

| Return Value | ProcessWire Behavior |
|---|---|
| `string` | Outputs the string directly as the response body. |
| `array` | Sets `Content-Type: application/json` header and outputs `json_encode($return)`. |
| `Page` | Sets the returned `Page` as the active `$page` and renders its template normally. |
| `true` | Indicates the callback already sent headers/output manually (e.g., via `echo`). |
| `false` or `null` | Aborts the route and delegates to ProcessWire's standard 404 handler. |

## 3. Trailing Slashes, Pagination & HTTP Method Guards

- **Trailing Slashes:** ProcessWire enforces trailing slash conventions on URL hooks:
  - Defining `/api/items/` will `301` redirect `/api/items` to `/api/items/`.
  - Append `/?` (`/api/items/?`) to accept both with and without a trailing slash without redirecting (essential for POST/webhook endpoints where a `301` redirect drops the POST body).
- **Pagination (`{pageNum}`):** Append `{pageNum}` as the final segment without a trailing slash (`/articles/feed/{pageNum}`) and read `(int) $event->pageNum`.
- **HTTP Method Filtering:** Guard registration with `$input->is('POST')` so the hook is only registered on matching request methods:

```php
if ($input->is('POST')) {
	$wire->addHook('/api/contact/?', function(HookEvent $event): array {
		$session = $event->wire()->session;
		$sanitizer = $event->wire()->sanitizer;
		$input = $event->wire()->input;

		$session->CSRF->validate();
		$email = $sanitizer->email((string) $input->post('email'));

		return ['ok' => $email !== ''];
	});
}
```

## Anti-Patterns & Loophole Closing

- ❌ **Trailing-slash redirects on POST/Webhook URLs:** Defining `/webhooks/stripe/` causes `POST /webhooks/stripe` to `301` redirect to `GET /webhooks/stripe/`. Always use `/webhooks/stripe/?` for POST/PUT/DELETE endpoints.
- ❌ **Returning `$pages->get($id)` without checking `$page->viewable()`:** `$pages->get()` ignores access control and unpublished status. Use `$pages->findOne()` or check `$page->viewable()` before returning data.
- ❌ **Using `$this->wire()` inside a closure in `site/init.php`:** Inside a route closure, access the API via `$event->wire()->pages`, `$event->wire()->sanitizer`, etc.

## Related Skills
- `pw-security-audit`
- `pw-pages`
