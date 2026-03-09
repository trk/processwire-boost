# Page
### Custom Page Classes (3.0.164)
```php
// /site/config.php
$config->usePageClasses = true;

// /site/classes/HomePage.php
namespace ProcessWire;
class HomePage extends Page {
    public function featuredTitle(): string {
        return strtoupper($this->title);
    }
}

// Template name "blog-post" => class BlogPostPage in /site/classes/BlogPostPage.php
```

### $page->get advanced (3.0.210)
- Dot syntax regardless of output formatting: `field.subfield.title`
- Force multi-value with brackets: `field[]`
- Index single item from multi-value: `field[0]`
- Filter multi-value with selector: `multiField[title%=design]`

```php
$v = $page->get('author.name');
$all = $page->get('images[]');           // array/WireArray even if one item
$first = $page->get('images[0]');        // first image
$filtered = $page->get('children[template=article]'); 
```

### $page->getMultiple (3.0.210)
- Get multiple properties/fields in one call.
- Returns numeric array by default, or associative with 2nd arg true.

```php
$values = $page->getMultiple(['title','summary']);
$assoc = $page->getMultiple('title,subtitle,body', true);
```

### $page->meta() (3.0.148)
- Store/retrieve page-specific metadata independent of fields.

```php
$page->meta('view_count', (int)$page->meta('view_count') + 1);
```

### $page->if() (3.0.148)
- Conditionally render string or execute a closure when an expression matches.

```php
echo $page->if("template=basic-page", fn() => strtoupper($page->title));
```

### Page statuses: Unique/Flagged (3.0.148)
- statusUnique: enforce globally unique name.
- statusFlagged: editor flags page when last interactive save produced errors.

### $page->references() (3.0.123)
- Returns a PageArray of other pages that reference this page (via Page reference fields).
- Also see $page->referencing property (PageArray).

```php
$refs = $page->references();
foreach($refs as $p){ echo $p->id; }
```

### $page->restorable() (3.0.123)
- Returns true if a trashed page can be restored to its original location.

```php
if($page->restorable()) {
  // show restore option
}
```

### Multi‑language URLs (basics)
- Enable LanguageSupportPageNames for per‑language names/URLs.
- Get URL for a specific language:
```php
$de = wire()->languages->get('de');
echo $page->url($de);
```

### Custom Page class (basics)
- Create a class extending Page in /site/classes/, assign it to a template.
```php
<?php namespace ProcessWire;
class ProductPage extends Page {
  public function priceWithTax(float $rate): float {
    return $this->price * (1 + $rate);
  }
}
```
Then in your template:
```php
/** @var ProductPage $page */
echo $page->priceWithTax(0.2);
```
