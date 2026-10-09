# ProcessWire Selectors (Queries)

ProcessWire uses a string-based query language called Selectors. Selectors are comma-separated `field=value` expressions (commas act as logical `AND`). For complex traversal, activate the `pw-pages` skill.

## 1. Operator Reference

### Exact & Numeric Comparisons
- `=` : Equal to (`id=123`, `template=product`, `price=99.90`)
- `!=` : Not equal to (`status!=hidden`, `template!=admin`)
- `<`, `<=`, `>`, `>=` : Numeric / Date comparisons (`price<=100`, `created>=2026-01-01`)

### Text Matching
- `*=` : Contains exact phrase / sequential words (`body*=organic coffee`)
- `~=` : Contains all words, order-independent (`title~=red shoes`)
- `%=` : SQL `LIKE` operator — matches substrings without MySQL fulltext word-length limits (`title%=foo`)
- `^=` : Starts with text (`name^=pro-`)
- `$=` : Ends with text (`name$=-draft`)
- `~|=`: Contains any of the words (`title~|=red blue green`)
- `**=`: Partial word matching (useful for live autocompletes)

## 2. Logical OR Expressions

- **OR Values:** Pipe `|` separates matching values: `template=article|news`
- **OR Fields:** Pipe `|` queries across multiple fields: `title|summary*=yacht`
- **OR Groups (Parentheses):** Group alternate conditions: `template=boat, (length>=50), (featured=1)`

## 3. Subfields, Count & Row-Matching

- **Subfield Access:** Query properties of Page references or Repeaters: `category.name=luxury`
- **Count Selectors:** Match quantity of multi-value fields: `images.count>=3`
- **Same-Row Matching (`@`):** Enforce matching within the **same exact row** in repeaters: `@items.sku=ABC, @items.qty>0`
- **Sub-Selectors (`[...]`):** Dynamic nested query: `company=[city=Bodrum, employees>10]`

## 4. Exclusion Modes & Access Control

By default, `$pages->find()` and `$pages->findOne()` exclude hidden, unpublished, and restricted pages.
- `include=hidden`: Allows hidden pages.
- `include=unpublished`: Allows hidden and unpublished pages.
- `include=trash`: Includes pages in the trash.
- `include=all`: Overrides all restrictions (hidden, unpublished, access control).
- `check_access=0`: Bypasses role-based permissions but still excludes unpublished pages.

## 5. Performance & Safety Rules

> [!WARNING]
> Never run an unbounded query on a large dataset. Always use `limit=`.

- **Always sanitize user input:**
  ```php
  $q = $sanitizer->selectorValue($input->get->q);
  $results = $pages->find("title|summary%=$q, limit=50");
  ```
- **Use `$pages->count()`** instead of `count($pages->find())` — avoids loading Page objects into memory.
- **Use `$pages->has()`** for lightweight existence checks — returns first matching page ID or 0.
- **Use `$pages->findMany()`** for iterating large datasets with automatic memory management.
- **Prefer indexed fields first:** `id`, `name`, `template`, `parent_id` execute fastest.
