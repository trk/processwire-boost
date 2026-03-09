# Files
### Files & Images (3.0.244)
- New getFiles() on Pagefile/Pagefiles/Pageimage/Pageimages returns all connected files (originals, variations, extras like WebP).
- InputfieldFile/InputfieldImage now support `file_context` to show the same field more than once.
- Multiple image field items can be hidden/disabled; WebP extras can be deleted independently in variations modal.

```php
// List all files for an images field
foreach(wire()->pages->get(123)->images->getFiles() as $f) {
  echo $f->filename;
}
```

### WebP support (3.0.148)
- Core adds support for generating WebP images for drastically smaller delivery.
- Configure in image field settings; ProcessWire generates WebP variations alongside originals.

### Custom fields on file/image (3.0.148)
- File and Image fields can have custom subfields like any template: add extra fields (e.g., caption, author) in field settings and populate per item in the editor.

### Images field basics
- Access Pageimages: `$page->images`, get first image, resize and output URL.
```php
if($page->images->count){
  $img = $page->images->first();
  $thumb = $img->size(800, 500);
  echo "<img src='{$thumb->url}' alt='" . $img->description . "'>";
}
```
