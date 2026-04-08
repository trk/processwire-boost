# ProcessWire Selectors (Queries)

ProcessWire uses a string-based query language called Selectors. For detailed operator reference and advanced patterns, activate the `pw-selectors` skill.

## Critical Rules

> [!WARNING]
> Never run an unbounded query on a large dataset. Always use `limit=`.

- **Always sanitize user input** before using in selectors:
  ```php
  $q = $sanitizer->selectorValue($input->get->q);
  $results = $pages->find("title%=$q, limit=50");
  ```
- **Use `$pages->count()`** instead of `count($pages->find())` to avoid loading all pages into memory.
- **Prefer indexed fields first** (`id`, `name`, `template`) in selectors for performance.
