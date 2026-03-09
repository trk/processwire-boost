# Custom Page class
description: Add a custom Page subclass and use methods in templates.
## Steps
- Create /site/classes/ProductPage.php extending Page
- Assign class to the template
- Call custom methods in templates
## Request
define priceWithTax() on ProductPage
## Response
computed price output
## Example
```php
<?php namespace ProcessWire;
class ProductPage extends Page {
  public function priceWithTax(float $rate): float {
    return $this->price * (1 + $rate);
  }
}
```
```php
<?php /** @var ProductPage $page */ echo $page->priceWithTax(0.2); ?>
```
## Blueprints
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
