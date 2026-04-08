---
name: pw-manipulate-pages
description: Full playbook for discovering, filtering, creating, and editing ProcessWire Page objects.
---

# ProcessWire Page Manipulation Playbook

ProcessWire relies fundamentally on the `Page` object. This skill provides the exact patterns required for interacting with Pages safely in a production environment.

## 1. Finding Pages (Discovery)

Never use raw SQL to find pages. Use the `$pages` API.

**Single Result (`$pages->get()`)**
```php
$page = wire('pages')->get("template=article, name=my-article");
if($page->id) { 
    // Always check for ->id to verify a page was found
}
```

**Multiple Results (`$pages->find()`)**
```php
// CRITICAL: Always use limit= on large collections to prevent memory exhaustion
$articles = wire('pages')->find("template=article, status=published, limit=50, sort=-created");

foreach($articles as $item) {
    echo $item->title;
}
```

## 2. Creating Pages

Always use `Page` instances to insert new content.

```php
$p = new \ProcessWire\Page();
$p->template = "article";
$p->parent = wire('pages')->get("/articles/"); // MUST have a parent
$p->name = wire('sanitizer')->pageName("My New Article!"); // URL slug
$p->title = "My New Article!";
$p->save();

// Alternatively, use the shortcut:
$p = wire('pages')->add("article", "/articles/", "my-new-article", [
    'title' => 'My New Article!'
]);
```

## 3. Editing & Updating Pages

You must call `->of(false)` to turn off output formatting before saving a page that has already been loaded from the database, otherwise textformatters might corrupt data.

```php
$p = wire('pages')->get(1234);
$p->of(false); // TRUN OFF OUTPUT FORMATTING
$p->title = "Updated Title";
$p->my_custom_field = "New Value";
$p->save();
```

## 4. Deleting & Trashing Pages

Use `$pages->trash()` to move a page to the trash securely. Only use `$pages->delete()` when absolutely necessary as it skips the trash.

```php
$p = wire('pages')->get(1234);
if ($p->id && $p->trashable()) {
    wire('pages')->trash($p);
}
```
