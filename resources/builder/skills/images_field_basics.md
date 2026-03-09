# Images field basics
description: Access images, create size variations and output URLs.
## Blueprints
- .ai/blueprints/pw_core/Pageimages.json
## Steps
- Access $page->images
- Get first image and create a variation
- Output <img> with alt from description
## Request
render first image as 800x500 thumbnail
## Response
img tag with resized URL
## Example
```php
<?php
if($page->images->count){
  $img = $page->images->first();
  $thumb = $img->size(800, 500);
  echo "<img src='{$thumb->url}' alt='" . $img->description . "'>";
}
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
