# Pager navigation
description: Render pagination controls for paginated results.
## Steps
- Use limit in selectors to paginate
- Call $items->renderPager() to output controls
## Request
list posts with pager
## Response
HTML pager below list
## Example
```php
<?php
$posts = pages()->find("template=post, sort=-created, limit=10");
foreach($posts as $p){
  echo "<h3>{$p->title}</h3>";
}
echo $posts->renderPager();
```
## Blueprints
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
