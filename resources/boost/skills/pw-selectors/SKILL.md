---
name: pw-selectors
description: Use when you need to construct ProcessWire Selectors to query, filter, sort, and find pages or data structures efficiently.
metadata:
  triggers:
    - processwire
    - selectors
    - query builder
    - find pages
---

# ProcessWire Selectors Skill Playbook

**CRITICAL RULE:** Before using selectors in API calls, always verify the available API methods by consulting `.llms/docs/index.md`. Never hallucinate API methods.

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
*Note: Using `*=`, `~=` heavily relies on MySQL FULLTEXT indexes (typically 4+ chars required, ignores stop-words). Use `%=` if you need to match short strings or stop-words.*
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
*(Note: `$pages->find()` defaults to MySQL text relevance if no sort is provided. Always provide a sort when order matters!)*

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

## 5. Security & Access Control
By default, database-querying selectors (`find()`, `children()`) **exclude** hidden, unpublished, or access-restricted pages. `$pages->get()` is the exception (assumes `include=all`).

You can override these exclusions:
- `include=hidden` : Allows `hidden` pages.
- `include=unpublished` : Allows both `hidden` and `unpublished`.
- `include=all` : Overrides all restrictions (hidden, unpublished, access control).
- `check_access=0` : Disables role-based access checks for the query, but still excludes unpublished pages.

## 6. Sanitizing User Input
Always sanitize user input before passing it into a selector.
```php
// Integers:
$year = (int) $input->get->year;

// Arbitrary string values:
$k = $sanitizer->selectorValue($input->get->keyword);
$results = $pages->find("title|body~=$k");
```
