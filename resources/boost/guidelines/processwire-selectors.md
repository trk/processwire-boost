# ProcessWire Selectors (Queries)

Selectors use comma-separated `field<operator>value` clauses (`AND`). Activate `pw-pages` for complex queries and page lifecycle operations.

- **Operators:** Exact/numeric `=`, `!=`, `<`, `<=`, `>`, `>=`; fulltext phrase `*=`, all words `~=`, any word `~|=`, partial word `**=`; SQL `LIKE` substring `%=`, prefix `^=`, suffix `$=`.
- **OR & Nesting:** OR values `template=article|news`; OR fields `title|summary*=term`; OR groups `(featured=1), (views>500)`; subfields `category.name=news`; counts `images.count>0`; same-row Repeater/multi-value `@items.sku=A, @items.qty>0`; sub-selectors `company=[city=Rome]`.
- **Inclusion Modes:** `$pages->find()` / `findOne()` exclude hidden, unpublished, trashed, and restricted pages by default (`$pages->get()` does not). Override explicitly only when authorized via `include=hidden|unpublished|trash|all` or `check_access=0`.
- **Performance & Safety:**
  - Always enforce a **positive top-level `limit=N`** (`N >= 1`) and explicit `sort=field` on `$pages->find()` — `limit=0` means unlimited, and `limit=` inside `[...]` or `(...)` does not cap the outer query.
  - Use `$pages->count($selector)` for totals, `$pages->has($selector)` for existence checks, and `$pages->findMany($selector)` for memory-safe streaming. Place indexed system fields (`template`, `parent_id`, `id`, `name`) first.
