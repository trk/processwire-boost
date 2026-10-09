---
name: pw-pages
description: "Use when constructing ProcessWire Selectors or manipulating Page objects — finding, filtering, creating, editing, trashing, and deleting pages."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Pages & Selectors Playbook

**CRITICAL RULE:** Before using selectors or page manipulation methods in API calls, always verify API methods using the documentation ladder:
1. Core CLI: `php index.php docs get-text Pages` or `php index.php docs methods-text Pages`
2. Core Markdown: `wire/core/Pages/API.md`, `wire/core/Page/API.md`, `wire/core/PageArray/API.md`, `wire/core/Selectors/API.md`
3. ProcessWire MCP Server: `pw_query` and `pw_schema_read`
Never hallucinate API methods.

Selectors are the foundational querying language in ProcessWire. They are simple strings of text used in `$pages->find()`, `$pages->get()`, `$page->children()`, `$page->siblings()`, and even for filtering arrays and fields.

## Anatomy of a Selector

A standard selector consists of a **field**, an **operator**, and a **value**.

```php
$pages->find("template=product");
```

Fields can be combined natively in one string. Commas `,` act as `AND`.

```php
$pages->find("template=product, parent=/shop/, get_price>100");
```

## 1. Operators Reference

ProcessWire offers a highly flexible set of operators.

### Basic & Numeric

- `=` : Equal to
- `!=` : Not equal to (or use negation like `!body*=word`)
- `<` / `<=` : Less than (or equal to)
- `>` / `>=` : Greater than (or equal to)

### Text Matching

_Note: Using `_=`, `~=`heavily relies on MySQL FULLTEXT indexes (typically 4+ chars required, ignores stop-words). Use`%=` if you need to match short strings or stop-words.\*

- `*=` : Contains exact phrase / text (words must be sequential).
- `~=` : Contains all words (order independent).
- `%=` : Contains phrase/text LIKE (SQL LIKE equivalent, no length restrictions).
- `^=` : Starts with text.
- `$=` : Ends with text.
- `~|=`: Contains any words.
- `**=`: Partial word matching (useful for live searches).

## 2. OR Logic

You can specify `OR` logic on fields, values, or entire groups.

**OR Values:** Pipe `|` separates possible values.

```php
$pages->find("template=article|news");
```

**OR Fields:** Pipe `|` separates possible fields.

```php
$pages->find("title|summary*=bitcoin");
```

**OR Groups (Parentheses):** Specify multiple full conditions where only one group needs to match.

```php
$pages->find("template=product, stock>0, (featured_from<=today, featured_to>=today), (highlighted=1)");
```

## 3. Sorting & Limits

**Sorting:** Use `sort=field`. Precede with a minus `-` for descending order.

```php
$pages->find("template=news, sort=-published_date, sort=title");
```

_(Note: `$pages->find()` defaults to MySQL text relevance if no sort is provided. Always provide a sort when order matters!)_

**Limit/Pagination:**

```php
$pages->find("template=skyscraper, limit=50"); // Used natively with pagination modules
```

## 4. Traversing Data (Sub-selectors & Count)

You can dive deep into complex fields directly from the selector.

**Count Selectors:** Target pages based on the quantity inside a multi-value field.

```php
$pages->find("images.count>=3");
```

**Subfield Selectors:** Query inner properties of complex fields (Page Reference, Repeater, Image, etc).

```php
$pages->find("buildings.feet_high>1000, buildings.year_built<1980");
```

**Matching the exact same row (`@`):**
If the field is a multi-value field (e.g. Repeaters), the standard subfield selector might match `feet_high` from row A, and `year_built` from row B. To force the selector to match BOTH properties within the **same exact row**, use `@`:

```php
$pages->find("template=house, @categories.name=modern, @categories.featured=1");
```

**Sub-selectors `[...]` :**
A sub-selector runs a query on the related field dynamically.

```php
// Find products whose company has >5 locations, and one location is in Finland
$pages->find("template=product, company=[locations>5, locations.title%=Finland]");
```

## 5. Page Retrieval Methods Matrix

| Method | Returns | Access/Status Filtering | Use Case |
|---|---|---|---|
| `$pages->find("...")` | `PageArray` | Excludes hidden, unpublished, and restricted | Standard multi-page query |
| `$pages->findOne("...")` | `Page` or `NullPage` | Same as `find()` (excludes hidden, unpublished) | Locate first matching visible page |
| `$pages->get("...")` | `Page` or `NullPage` | **No exclusions** (includes hidden, unpublished) | Locate specific page by ID, path, or selector |
| `$pages->findMany("...")` | `PageArray` iterator | Excludes hidden, unpublished, and restricted | Memory-safe chunked iteration for large sets |
| `$pages->has("...")` | `int` (page ID) or `0` | No exclusions | Fast existence check without loading object |
| `$pages->count("...")` | `int` | Excludes hidden, unpublished, and restricted | Count matching rows without loading objects |

```php
// Memory-safe chunked iteration:
foreach ($pages->findMany("template=product") as $product) {
    // Automatically loaded and unmanaged in memory chunks
}

// Check existence:
if ($pages->has("template=user, email=info@example.com")) {
    // Page exists
}

// PageArray::each formatting:
echo $pages->find("template=news, sort=-created, limit=5")
    ->each("<li><a href='{url}'>{title}</a></li>");
```

## 6. Security & Access Control

By default, database-querying selectors (`find()`, `findOne()`, `children()`) **exclude** hidden, unpublished, or access-restricted pages. `$pages->get()` is the exception (includes hidden/unpublished/restricted pages).

You can override these exclusions:

- `include=hidden` : Allows `hidden` pages.
- `include=unpublished` : Allows both `hidden` and `unpublished`.
- `include=trash` : Includes pages located in the trash.
- `include=all` : Overrides all restrictions (hidden, unpublished, access control).
- `check_access=0` : Disables role-based access checks for the query, but still excludes unpublished pages.

## 7. Sanitizing User Input

Always sanitize user input before passing it into a selector.

```php
// Integers:
$year = (int) $input->get->year;

// Arbitrary string values:
$k = $sanitizer->selectorValue($input->get->keyword);
$results = $pages->find("title|body~=$k, limit=50");
```

---

## 8. Creating Pages

Always use `Page` instances to insert new content.

```php
// Option A: Standard Page instance
$p = new \ProcessWire\Page();
$p->template = "article";
$p->parent = $pages->get("/articles/"); // MUST have a parent
$p->name = $sanitizer->pageName("My New Article!"); // URL slug
$p->title = "My New Article!";
$p->save();

// Option B: Array syntax ($pages->new)
$p = $pages->new([
    'template' => 'article',
    'parent' => '/articles/',
    'title' => 'My New Article!',
]);

// Option C: Shortcut ($pages->add)
$p = $pages->add("article", "/articles/", "my-new-article", [
    'title' => 'My New Article!'
]);
```

## 9. Editing & Updating Pages

You must call `->of(false)` to turn off output formatting before saving a page that has already been loaded from the database, otherwise textformatters might corrupt data.

```php
$p = $pages->get(1234);
$p->of(false); // TURN OFF OUTPUT FORMATTING
$p->title = "Updated Title";
$p->my_custom_field = "New Value";
$p->save();

// Combined atomic set and save:
$p->setAndSave('title', 'Updated Title');
```

## 10. Deleting & Trashing Pages

Use `$pages->trash()` to move a page to the trash securely. Only use `$pages->delete()` when absolutely necessary as it skips the trash.

```php
$p = $pages->get(1234);
if ($p->id && $p->trashable()) {
    $pages->trash($p);
}
```

## 11. Performance Tips

- **Always use `limit=`** on `$pages->find()` for large datasets
- Use `$pages->count($selector)` instead of `$pages->find($selector)->count()` — avoids loading all pages into memory
- Use `$pages->findMany($selector)` when iterating across hundreds or thousands of pages
- Prefer indexed fields first (`id`, `name`, `template`, `parent_id`) in selectors
- Call `$pages->uncacheAll()` after manual batch processing
- Use `$page->of(false)` before modifying output-formatted pages
