# ProcessWire Selectors (Queries)

ProcessWire queries use comma-separated `field<operator>value` selector strings (commas act as logical `AND`). For comprehensive page lifecycle and selector patterns, activate the `pw-pages` skill.

## 1. Operators Reference

### Exact & Numeric Comparisons
- `=` : Equal to (`id=123`, `template=product`, `price=99.90`)
- `!=` : Not equal to (`status!=hidden`, `template!=admin`, or prefix `!field*=word`)
- `<`, `<=`, `>`, `>=` : Numeric or date comparisons (`price<=100`, `created>=2026-01-01`)

### Text Matching
- `*=` : Contains exact phrase / sequential words (uses MySQL `FULLTEXT` index)
- `~=` : Contains all words in any order (uses MySQL `FULLTEXT` index)
- `%=` : SQL `LIKE` substring match (no minimum word length or stopword restrictions)
- `^=` / `$=` : Starts with / ends with string (`name^=pro-`, `name$=-draft`)
- `~|=` : Contains any of the specified words (`title~|=red blue green`)
- `**=` : Partial word match (ideal for live autocomplete queries)

## 2. Logical OR Expressions

- **OR Values (`|`):** `template=article|news`
- **OR Fields (`|`):** `title|summary*=charter`
- **OR Groups `( ... )`:** `template=boat, (length>=50), (featured=1)` — at least one parenthesized group must match.

## 3. Subfields, Counts, Same-Row (`@`) & Sub-Selectors (`[...]`)

- **Subfield Access:** `category.name=luxury`, `created_users_id.name=admin`
- **Count Selectors:** `images.count>=3`, `categories.count>0`
- **Same-Row Matching (`@`):** Prefix fields with `@` so multi-value / Repeater conditions must match within the **same row**: `@items.sku=ABC, @items.qty>0`
- **Sub-Selectors (`[...]`):** Run a nested query on a related field: `company=[city=Bodrum, employees>10]`

## 4. Inclusion Modes & Access Control

By default, `$pages->find()` and `$pages->findOne()` exclude hidden, unpublished, trashed, and access-restricted pages (`$pages->get()` does not apply exclusions). Override with:
- `include=hidden`: Include hidden pages.
- `include=unpublished`: Include hidden and unpublished pages.
- `include=trash`: Include trashed pages.
- `include=all`: Bypass all status and access restrictions.
- `check_access=0`: Bypass role-based access control while still excluding unpublished/trashed pages.

## 5. Performance & Safety Rules

- **Bound large queries:** Always include `limit=N` and explicit `sort=field` (or `sort=-field` for descending) on `$pages->find()`.
- **Count & existence without hydration:** Use `$pages->count($selector)` instead of `$pages->find($selector)->count()`, and `$pages->has($selector)` for fast existence checks (returns matching page ID or `0`).
- **Chunked iteration:** Use `$pages->findMany($selector)` when iterating large datasets to prevent memory exhaustion.
- **Index order:** Place indexed system fields (`template`, `parent_id`, `id`, `name`) first in selectors.
