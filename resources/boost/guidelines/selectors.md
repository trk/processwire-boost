# ProcessWire Selector Guidelines

## Basic Selectors

Selectors are used to find pages based on criteria.
Example: `template=basic-page, limit=10, sort=-created`

## Common Operators

- `=`: Equal
- `!=`: Not equal
- `*=`: Contains phrase (like)
- `~=`: Contains all words
- `^=`: Starts with
- `$=`: Ends with
- `<`: Less than
- `>`: Greater than

## Finding by Field

```php
$pages->find("title*=Hello, color=red");
```

## Sub-selectors (OR conditions)

```php
$pages->find("template=project, (tags=web), (tags=design)");
```

## Date Selectors

```php
$pages->find("created>2024-01-01, created<2024-12-31");
```
