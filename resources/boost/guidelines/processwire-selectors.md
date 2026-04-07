# ProcessWire Selectors (Queries)

ProcessWire uses a string-based query language called Selectors to find pages. 

## Operator Reference

- `=`: Equal
- `!=`: Not equal
- `%=`: Contains string (SQL LIKE). Good for partial matches.
- `*=`: Contains words (Fulltext). Good for text search.
- `~=`: Contains all words.
- `^=`: Starts with.
- `$=`: Ends with.
- `<`, `>`, `<=`, `>=`: Numerical or date comparisons.

## Best Practices & Performance Optimization

> [!WARNING]
> Never run an unbounded query on a large dataset. Always use `limit=`.

- **Explicit Identifier First**: Prefer using `id`, `name`, or `template` as the primary identifier to utilize database indexes effectively.
- **`limit=N`**: Always restrict query results.
- **`check_access=0`**: Include pages the user doesn't have access to (bypass RBAC). Use cautiously.
- **`include=all`**: Include hidden, unpublished, and trashed pages.

## Complex Queries

- **OR condition (Pipes)**: `template=project|article`
- **AND condition (Commas)**: `template=project, status=published`
- **Sub-selectors (OR groups)**: `$pages->find("template=project, (tags=web), (tags=design)")` -> finds projects with either tag.
- **Date Selectors**: `$pages->find("created>2024-01-01, created<2024-12-31")`
- **Parent/Children Matching**: 
  - `parent=/about/` (exact parent)
  - `has_parent=123` (descendant of page ID 123)

## Counting Pages

When checking for existence or getting a total, use `$pages->count()` instead of `count($pages->find())` to avoid loading all pages into memory:
```php
// Good: Returns an integer immediately
$total = $pages->count("template=user, age>18"); 

// Bad: Loads all pages into RAM just to count them
$total = count($pages->find("template=user, age>18")); 
```
