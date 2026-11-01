---
name: pw-pages
description: "Use when constructing ProcessWire selectors or manipulating Page objects — finding, filtering, counting, creating, editing, trashing, or deleting pages."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Pages & Selectors Playbook

Consult the local documentation ladder before using unfamiliar `Pages` or `Page` methods:
1. Core CLI: `php index.php docs get-text Pages`, `php index.php docs get-text Page`, `php index.php docs methods-text Pages`
2. Core Markdown: `wire/core/Pages/API.md`, `wire/core/Page/API.md`, `wire/core/PageArray/API.md`, `wire/core/Selectors/API.md`
3. MCP Server: `pw_schema_read` and `pw_query`

## 1. Page Retrieval Methods Matrix

| Method | Return Type | Access & Status Filtering | Primary Use Case |
|---|---|---|---|
| `$pages->find($selector)` | `PageArray` | Excludes `hidden`, `unpublished`, `trash`, and restricted pages | Standard multi-page query (always include `limit=` and `sort=`) |
| `$pages->findOne($selector)` | `Page\|NullPage` | Same exclusions as `find()` | Fetch single visible page matching selector |
| `$pages->get($selector)` | `Page\|NullPage` | **No exclusions** (includes `hidden`, `unpublished`, `trash`, restricted) | Fetch known page by ID, path, or internal system lookup |
| `$pages->findMany($selector)` | `PageArray` iterator | Same exclusions as `find()` | Memory-safe chunked iteration over thousands of pages |
| `$pages->count($selector)` | `int` | Same exclusions as `find()` | Count matching pages without hydrating `Page` objects |
| `$pages->has($selector)` | `int` (`page_id` or `0`) | No exclusions | Fast existence check without loading the `Page` |
| `$pages->newNullPage()` | `NullPage` | N/A | Instantiate a wired `NullPage` (never call `new NullPage()` directly) |

> [!IMPORTANT]
> `$pages->get()` and `$pages->findOne()` return a `NullPage` (`$p->id === 0`) when no page matches, never `null`. Always check `if ($p->id)` before reading or mutating properties.

## 2. Selector Operators & Advanced Expressions

### Comparison & Text Matching Operators
- **Exact / Numeric:** `=`, `!=`, `<`, `<=`, `>`, `>=` (e.g., `template=product, price<=100, created>=2026-01-01`)
- **Fulltext Phrase / Words (`*=`, `~=`):** Rely on MySQL `FULLTEXT` indexes. `*=` matches sequential phrase; `~=` matches all words in any order.
- **Substring LIKE (`%=`):** Uses SQL `LIKE '%val%'` without minimum word-length or stopword limits.
- **Prefix / Suffix (`^=`, `$=`):** `name^=charter-`, `name$=-vip`
- **Any Word / Partial Word (`~|=`, `**=`):** `title~|=yacht catamaran`, `title**=lux`

### Logical OR & Grouping
```php
// OR values on a single field
$items = $pages->find('template=article|news, sort=-created, limit=20');

// OR across multiple fields
$q = $sanitizer->selectorValue((string) $input->get('q'));
$items = $pages->find("template=product, title|summary|body%={$q}, limit=20");

// OR groups: at least one parenthesized group must match
$items = $pages->find('template=product, stock>0, (featured=1), (discount_percent>=20)');
```

### Subfields, Counts, Same-Row (`@`) & Sub-Selectors (`[...]`)
```php
// Count of multi-value fields
$withGallery = $pages->find('template=yacht, images.count>=5, limit=20');

// Same-row matching (@) inside Repeaters or multi-value Page fields
$matched = $pages->find('template=yacht, @seasons.month=july, @seasons.available=1, limit=20');

// Sub-selector ([...]) evaluated dynamically against a relation
$matched = $pages->find('template=product, manufacturer=[country=Italy, active=1], limit=20');
```

### Inclusion Overrides
Override default `find()` / `findOne()` / `count()` exclusions when required:
- `include=hidden` — Include hidden pages.
- `include=unpublished` — Include hidden and unpublished pages.
- `include=trash` — Include trashed pages.
- `include=all` — Bypass all status and access restrictions.
- `check_access=0` — Bypass role access checks while still excluding unpublished and trashed pages.

## 3. Creating Pages

```php
// Option A: Array syntax via $pages->new() (creates and saves immediately)
$article = $pages->new([
	'template' => 'article',
	'parent' => '/articles/',
	'name' => $sanitizer->pageName('My New Article'),
	'title' => 'My New Article',
]);

// Option B: $pages->add(template, parent, name, values)
$article = $pages->add('article', '/articles/', 'my-new-article', [
	'title' => 'My New Article',
]);

// Option C: Explicit Page instance when setting multiple fields or statuses before first save
$article = new Page();
$article->template = $templates->get('article');
$article->parent = $pages->get('/articles/');
$article->name = $sanitizer->pageName('My New Article');
$article->title = 'My New Article';
$article->addStatus(Page::statusHidden);
$article->save();
```

## 4. Updating Pages Safely (`$page->of(false)`)

Always disable output formatting (`$page->of(false)`) before modifying a loaded page so `Textformatter` output is not written back to the database:

```php
$article = $pages->get(1234);
if ($article->id) {
	$article->of(false);
	$article->title = 'Updated Title';
	$article->summary = 'Updated summary';
	$article->save();

	// Or atomic single-field update (automatically handles output formatting state):
	$article->setAndSave('title', 'Updated Title');
}
```

## 5. Trashing & Deleting Pages

Prefer `$pages->trash($page)` over `$pages->delete($page)` unless permanent deletion is explicitly required:

```php
$article = $pages->get(1234);
if ($article->id && $article->trashable()) {
	$pages->trash($article);
}
```

## 6. CLI Page Administration (`vendor/bin/wire page:*`)

For administrative tasks from the terminal, use `vendor/bin/wire`:
- `php vendor/bin/wire page:list --template=article --limit=10 --json`
- `php vendor/bin/wire page:find "template=article, limit=10" --json`
- `php vendor/bin/wire page:create --template=article --parent=/articles/ --title="Hello" --json`
- `php vendor/bin/wire page:update --id=1234 --set="title=Updated" --json`
- `php vendor/bin/wire page:move --id=1234 --parent=/archive/ --json`
- `php vendor/bin/wire page:publish|unpublish|restore --id=1234 --json`
- `php vendor/bin/wire page:trash --id=1234 --force --json`

## Anti-Patterns & Loophole Closing

- ❌ **Checking `if ($page)` or `if ($page !== null)` on `$pages->get()`:** `NullPage` is a truthy object. Always check `if ($page->id)`.
- ❌ **Interpolating raw `$input` into selectors:** Always wrap user input in `$sanitizer->selectorValue()`.
- ❌ **Calling `count($pages->find($selector))`:** Hydrates every matching page into memory. Always use `$pages->count($selector)`.
- ❌ **Mutating and saving a loaded page without `$page->of(false)`:** Corrupts formatted fields; call `$page->of(false)` or `$page->setAndSave()`.
