---
name: pw-custom-page-classes
description: Use when extending ProcessWire's base Page class to create specialized, strongly-typed custom classes representing specific templates.
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Custom Page Classes Skill Playbook

**CRITICAL RULE:** Custom Page classes must reside in `/site/classes/` and always declare `namespace ProcessWire;`.

By default, every page in ProcessWire is an instance of the `Page` class. Custom page classes allow you to extend the base `Page` class into specialized, strongly-typed classes based on the page's template (e.g., a `blog-post` template uses `BlogPostPage`).

## 1. Prerequisites and Enabling
Ensure Custom Page Classes are enabled in `/site/config.php`:
```php
$config->usePageClasses = true;
```

## 2. Naming Conventions
The custom class file must be placed in `/site/classes/` and named using the **PascalCase version of the template name**, appended with the word **Page**. 
- `home` -> `HomePage`
- `blog-post` -> `BlogPostPage`
- `category` -> `CategoryPage`

## 3. Basic Implementation Example
```php
<?php 

declare(strict_types=1);

namespace ProcessWire;

/**
 * /site/classes/BlogPostPage.php
 * 
 * PHPDoc properties tell the IDE what fields exist on this type of page.
 * 
 * @property string $title
 * @property string $summary
 * @property string $body
 * @property string $date
 * @property User|NullPage $author
 * @property PageArray|CategoryPage[] $categories
 * @property-read string $authorName
 */
class BlogPostPage extends Page 
{
    // You can add helper logic specific to this template
    public function getAuthorName(): string 
    {
        $author = $this->createdUser; 
        
        if (!$author) {
            return 'Unknown Author';
        }
        
        return trim("{$author->first_name} {$author->last_name}");
    }

    // You can override the core get() method to enable property-like access ($page->authorName)
    public function get($key) 
    {
        if ($key === 'authorName') {
            return $this->getAuthorName();
        }
        
        return parent::get($key);
    }
}
```

## 4. Usage in Templates (Type Hinting)
To fully utilize these in your logic, provide IDE hints (PHPDoc).
```php
// In a template file (e.g., blog-post.php)
/** @var BlogPostPage $page */
echo $page->authorName; 

// When retrieving multiple pages
/** @var PageArray|BlogPostPage[] $posts */
$posts = $pages->find("template=blog-post, limit=10");

foreach ($posts as $post) {
    echo $post->getAuthorName();
}
```

You can now use **Strong Typing** in procedural functions:
```php
function renderBlogCard(BlogPostPage $post): string { ... }
```

## 5. Extending Different Types
Instead of `Page`, you can extend other core types if adapting users, permissions, languages, or roles:
- `class UserPage extends User {}`
- `class RolePage extends Role {}`
- `class LanguagePage extends Language {}`

**Universal `DefaultPage`:**
Create `class DefaultPage extends Page` in `/site/classes/DefaultPage.php`. ProcessWire will use this class for any page that doesn't have a specialized custom class. You can also have your custom classes extend `DefaultPage` instead of `Page`.

**Repeaters & Fieldsets:**
You can also extend types representing sub-items:
- `class QuotesRepeaterPage extends RepeaterPage {}`
- `class MatrixBlockRepeaterMatrixPage extends RepeaterMatrixPage {}`
- `class SeoFieldsetPage extends FieldsetPage {}`

## 6. Advanced Patterns

### Inheritance and Interfaces
If `BlogPostPage` shares fields with `ArticlePage`, it can extend it:
```php
class BlogPostPage extends ArticlePage { ... }
```
If multiple unrelated pages share behavioral traits (like different Tour items), use PHP Interfaces:
```php
interface TourPage {
    public function getPrice(): float;
}

class BoatTourPage extends Page implements TourPage { ... }
```

### DRY Strategy Selection
Choose the right reuse mechanism based on your actual need:

| Need | Use |
|------|-----|
| Shared behavior + type identity | Abstract base class (`abstract class ContentPage extends Page`) |
| Contract enforcement across unrelated types | Interface (`interface Bookable`) |
| Shared implementation, no type identity needed | Trait (`trait ExcerptTrait`) |
| Few classes, simple shared logic | Base class inheritance (`extends ArticlePage`) |
| Runtime extension from outside | Hooks (`addHookAfter('ProductPage::saveReady', ...)`) |

**Caveat:** Traits do NOT support `instanceof` checks. If you need polymorphic code (`if ($page instanceof Bookable)`), use interfaces or abstract classes instead of traits.

### Hookable Methods (Triple-Underscore Convention)
Make your custom methods hookable by prefixing with `___` (three underscores) and documenting with `@method`:
```php
/**
 * @method string formatPrice()
 */
class ProductPage extends Page 
{
    public function ___formatPrice(): string 
    {
        return '$' . number_format($this->get('price'), 2);
    }
}
```
Now external code can hook before/after:
```php
// In ready.php or a module
$wire->addHookAfter('ProductPage::formatPrice', function(HookEvent $event): void {
    $event->return .= ' USD';
});
```

### Helper Class Pattern (Memory Optimization)
If your page class creates expensive objects (DB queries, API clients), delegate to a shared static singleton helper. This avoids duplicating heavyweight objects when hundreds of pages are loaded:
```php
class ProductPageOrders extends Wire 
{
    public function getOrders(ProductPage $product): array 
    {
        $db = $this->wire()->database;
        $query = $db->prepare('SELECT * FROM orders WHERE product_id = :id');
        $query->bindValue(':id', $product->id, \PDO::PARAM_INT);
        $query->execute();
        
        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }
}

class ProductPage extends Page 
{
    protected static ?ProductPageOrders $orders = null;

    protected function orders(): ProductPageOrders 
    {
        if (self::$orders === null) {
            self::$orders = new ProductPageOrders();
        }
        
        return self::$orders;
    }

    public function getOrders(): array 
    {
        return $this->orders()->getOrders($this);
    }
}
```
**Why:** With 100 products loaded, `ProductPageOrders` is created only once (static), not 100 times.

### Hooks Targeting Specific Classes
Standard hooks usually target `Page::saveReady`. With custom classes, you can target the hook directly at the subclass, avoiding manual template checks:
```php
// Only fires when a ProductPage is saved
$wire->addHookBefore('ProductPage::saveReady', function(HookEvent $event) {
    $product = $event->object; 
    /** @var ProductPage $product */
    
    if ($product->num_available === 0) {
        $product->addStatus('hidden');
    }
});
```

### Admin Appearance (`getPageListLabel`)
You can natively override how specific pages look in the ProcessWire Admin Page Tree:
```php
class ProductPage extends Page 
{
    public function getPageListLabel(): string 
    {
        $title = $this->getFormatted('title');
        // Warning: must HTML Entity Encode any output to the admin
        return "{$title} (<b>{$this->num_available}</b> in stock)";
    }
}
```

### Typing Sub-Fields (ProFields, Combos, Tables)
To make your IDE recognize properties inside fields (e.g. `$page->seo->browser_title`), create empty classes that extend the Field's native value class, and provide `@property` hints, then hint them in the Page class:
```php
/**
 * /site/classes/fields/SeoValue.php
 * @property string $browser_title
 * @property string $meta_description
 */
class SeoValue extends ComboValue {}

// In DestinationPage.php:
// @property SeoValue $seo
```

## 7. Pitfalls to Avoid
- **DO NOT** register hooks inside your Custom Page classes (`addHook()` should reside in `ready.php` or a module). Page classes can be instantiated hundreds of times in a single loop, adding duplicate hooks.
- **INTERNAL vs EXTERNAL ACCESS:** Inside the class, calling `$this->template` yields the raw `template_id` or `null` due to lazy-loading bypass (accessing internal protected props). To be safe, use `$this->get('template')` to get the Object.
- **API VARIABLES:** You cannot access API variables magically via `$this->pages` in a class. Use `$this->wire()->pages` or `$this->wire('pages')`.
- **OUTPUT FORMATTING:** Remember that your methods might act differently if `$this->of()` (Output Formatting) is `true` (frontend) vs `false` (admin). Don't assume.
