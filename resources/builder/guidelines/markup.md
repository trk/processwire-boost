# Markup
### Direct output
- Output markup directly in templates; extract reusable parts with include().
```php
include("./_head.php");
echo $page->body;
include("./_foot.php");
```

### Delayed output
- Populate variables and place them in _main.php at the end.
```php
$headline = $page->get("headline|title");
$bodycopy = $page->body;
include("./_main.php");
```
```php
?><!DOCTYPE html>
<html>
  <head><title><?php echo $headline; ?></title></head>
  <body>
    <h1><?php echo $headline; ?></h1>
    <?php echo $bodycopy; ?>
  </body>
</html>
```

### Markup Regions (enable)
- Add settings to /site/config.php and use _main.php.
```php
$config->useMarkupRegions = true;
$config->appendTemplateFile = '_main.php';
```

### Front‑end editing (PageFrontEdit)
- Install core PageFrontEdit module; permissions: page-edit and page-edit-front.
- Option A: automatic editing of formatted field values; use `$page->edit(false/true)` to toggle.
- Option B: API call for a single field: `echo $page->edit('body');`
- Options C/D: <edit> tags or edit attributes for complex fields/markup.

### Pagination (Markup Pager)
- Render pager for paginated results: `$items->renderPager()`.
- Or use MarkupPagerNav/markupPagerNav with options array for custom output.
```php
$items = pages()->find("template=post, limit=10, sort=-created");
echo $items->renderPager();
```

### Markup Regions (3.0.250)
- Unique tags (no id needed): populate <html>, <head>, <title>, <body>, <main>.
- Target by class: use a CSS-like selector with a leading dot in pw-* value.
- Target by tag: use literal tag name in pw-* value; add .class to narrow.
- New action: pw-update updates attributes and may also append content.

```html
<!-- Populate unique tags without id -->
<title>Hello world</title>
<head pw-append>
  <link rel="stylesheet" href="/file.css">
</head>
<body pw-append>
  <script>alert('hello world');</script>
</body>

<!-- Prepend content to all elements with uk-container class -->
<p class="uk-text-primary" pw-prepend=".uk-container">Hello world</p>

<!-- Append to all <footer> or to <footer class="terms"> -->
<div pw-append="<footer>"><p>Copyright 2025</p></div>
<div pw-append="<footer.terms>"><p>Copyright 2025</p></div>

<!-- Update attributes (and optionally content) on targets -->
<div pw-update=".uk-container" class="uk-container-large"></div>
<div pw-update=".uk-container" class="uk-container-large">
  <p>Hello world</p>
</div>
```
