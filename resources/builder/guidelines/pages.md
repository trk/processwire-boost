# Pages
### Pages
```php
$items = wire('pages')->find("template=basic-page, limit=10");
foreach($items as $p) { echo $p->title; }
```

### Selector basics
- Field/operator/value: `field=foo`, multiple selectors separated by comma (AND).
- OR fields: `title|name=products` matches either field.
- OR values: `template=basic-page|article`.
- Sorting and limiting: `sort=title, limit=10` or `sort=-created`.
- Count/subselectors: `children.count>0`, `images.count>0`.

```php
// AND selectors
$items = wire()->pages->find("template=basic-page, status=published");

// OR fields
$items = wire()->pages->find("title|name~=products");

// OR values
$items = wire()->pages->find("template=basic-page|article");

// Sorting and limiting
$items = wire()->pages->find("template=basic-page, sort=-created, limit=10");

// Count selector
$items = wire()->pages->find("children.count>0");
```

### URL segments (routing)
- Enable per template: Setup > Templates > [template] > URLs > Enable URL segments.
- Use `$input->urlSegment1..3` or `$input->urlSegmentStr()` in template.
- Best practice: throw 404 for unknown/extra segments; adjust `$config->maxUrlSegments` if needed.
```php
if(strlen($input->urlSegment2)) throw new Wire404Exception();
switch($input->urlSegment1) {
  case '':
    break;
  case 'photos':
    // render gallery
    break;
  case 'map':
    // render map
    break;
  default:
    throw new Wire404Exception();
}
```

### $pages selector additions (3.0.200)
- Order by explicit IDs: `id.sort=2|1|3` guarantees return order by given IDs.
- OR status matching: `status=hidden|unpublished` finds pages matching either status.

```php
// Keep result order by id list
$items = wire()->pages->find('id.sort=10|7|15');

// Find pages that are hidden OR unpublished
$items = wire()->pages->find('status=hidden|unpublished');
```

### $pages->findRaw template.* (3.0.210)
- findRaw() now returns template property values like `template.name`.

```php
$rows = wire()->pages->findRaw('template=basic-page, limit=5', ['id','title','template.name']);
```

### $pages->findRaw (3.0.184)
- Return raw field/property values without loading Page objects for performance.

```php
$rows = wire()->pages->findRaw('template=blog-post', ['title','date']);
```

### Programmatic autojoin (3.0.172)
- Choose which fields to autojoin for specific outputs.
- Other autojoins are disabled; provides significant speed-ups for listings.

```php
// Autojoin only title
$items = wire()->pages->find('id>0, field=title');

// For search results, autojoin title and summary
$q = 'search query';
$items = wire()->pages->find("title|body*=$q, field=title|summary");
```

### $pages->has(selector) (3.0.164)
- Quickly check if any page matches a selector; returns the first matching page ID (or 0).

```php
$id = wire()->pages->has('template=basic-page, title%=Design');
if($id){ /* found */ }
```

- See also: $pages->parents(), $pages->getByIDs(), $pages->getID() (3.0.164)

### Advanced text operators (3.0.160)
- Powerful new text operators for search-like queries in selectors.
- Examples:

```php
// Partial word match
$items = wire()->pages->find("title~*=web image");

// Live search: last word partial
$items = wire()->pages->find("title~~=api pro");

// LIKE-based word/partial match
$items = wire()->pages->find("title~%=build site");

// Query expansion
$items = wire()->pages->find("title~+=books");

// Any word (OR logic)
$items = wire()->pages->find("title~|=architecture engineering construction");
```

### $pages->touch(date type) (3.0.148)
- Update page timestamps with a specific date type: modified, created, or published.

```php
wire()->pages->touch($page, 'modified');    // or 'created' / 'published'
```
