# Advanced ProcessWire Selectors

Selectors are powerful strings used to find pages.

## Performance Optimization

- `check_access=0`: Include pages the user doesn't have access to.
- `include=all`: Include hidden, unpublished, and trashed pages.
- `limit=N`: Always use limits for large datasets.
- `start=N`: For pagination.

## Complex Queries

- `field=value1|value2`: OR condition.
- `field1=val, field2=val`: AND condition.
- `template=A|B`: Multiple templates.
- `parent=/path/`: Match children of a path.
- `has_parent=123`: Match descendants of a page ID.

## Sub-Selectors

- `field.subfield=value`: Match properties of object fields (e.g., `image.description%=text`).
- `field=(sub-selector)`: Nested criteria.

## Operator Reference

- `=`: Equal.
- `!=`: Not equal.
- `%=`: Contains string (SQL LIKE).
- `*=`: Contains words (Fulltext).
- `~=`: Contains all words.
- `^=`: Starts with.
- `$=`: Ends with.
- `<`, `>`, `<=`, `>=`: Numerical comparisons.
