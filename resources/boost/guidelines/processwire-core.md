# ProcessWire Core Logic & Variables

ProcessWire is a data-driven CMF where almost everything is represented as a `Page`. Always use the `$wire` API (or global variables like `$pages`, `$users`, `$templates`) for operations rather than direct SQL unless absolutely necessary. For detailed context-safe API access patterns, activate the `pw-expert` skill.

## Core API Variables

- **`$pages`**: Page retrieval and manipulation:
  - `find(selector)`: Returns `PageArray` (excludes hidden, unpublished, and restricted pages by default).
  - `findOne(selector)`: Finds a single page matching selector with normal access/status filtering (unlike `get()`).
  - `get(selector)`: Retrieves a single page by ID, path, or selector — includes hidden/unpublished/restricted pages. Returns `NullPage` (id=0) when not found.
  - `findMany(selector)`: Memory-safe chunked iterator for iterating large datasets without memory exhaustion.
  - `count(selector)`: Counts matching pages without loading them into memory.
  - `has(selector)`: Lightweight existence check — returns first matching page ID or 0.
  - `new([...])`: Creates and saves a page with array syntax.
  - `add(template, parent, name, [...])`: Creates, populates, and saves a page.
  - **NullPage instances**: Use `$pages->newNullPage()` — never `new NullPage()` directly (won't be wired).
- **`$templates` & `$fields`**: `$templates->get(name)`, `$fields->get(name)` — schema access.
- **`$users`, `$roles`, `$permissions`**: Access control — `$users->get(name)`, `$user->hasRole('editor')`.
- **`$input`**: Safe request data — `$input->get->name`, `$input->post->name`, `$input->urlSegment(1)`.
- **`$sanitizer`**: Input sanitization — `$sanitizer->text()`, `$sanitizer->selectorValue()`, `$sanitizer->email()`.
- **`$database`**: Direct database access — Always access via property (e.g., `$this->wire()->database`), not method. This is a `WireDatabasePDO` wrapper (PDO-like API).
- **`$config`**: System configuration, paths, URLs — `$config->paths->templates`, `$config->urls->assets`.

## Working with Pages and Fields

```php
// Reading field values
echo $page->title;
echo $page->body;

// Images and files
$image = $page->images->first();
if ($image) {
	echo $image->size(800, 600)->url;
}

// Page reference fields
$category = $page->category; // Returns single Page or NullPage
foreach ($page->related_products as $related) {
	echo $related->title;
}

// PageArray::each formatting
echo $page->children('sort=title')->each("<li><a href='{url}'>{title}</a></li>");

// Setting and saving values
$page->of(false); // Turn off output formatting before modifying loaded pages
$page->title = 'New Title';
$page->save('title'); // Save single field
// Or combined atomic set and save:
$page->setAndSave('title', 'New Title');
```

## Selector Status & Access Inclusion Modes
By default, `$pages->find()` and `$pages->findOne()` exclude hidden, unpublished, and restricted pages. Override with `include=[mode]`:
- `include=hidden`: Allows hidden pages.
- `include=unpublished`: Allows hidden and unpublished pages.
- `include=trash`: Includes pages residing in the trash.
- `include=all`: Overrides all restrictions (hidden, unpublished, access control).

## API Variable Scoping & Multi-Instance Safety

- **In Wire-derived classes (Modules, Custom Pages):** Use `$this->wire()->apiVar` (e.g. `$this->wire()->pages`). Avoid `$this->pages` (slow `__get()` magic methods and property collisions).
- **Local caching in methods and loops:** For multiple calls in the same method, assign a locally scoped variable at the top:
  ```php
  $pages = $this->wire()->pages;
  $sanitizer = $this->wire()->sanitizer;
  ```
- **In Hooks (`ready.php`, Modules):** Use `$event->wire()->apiVar`.
- **In Templates (`/site/templates/*.php`):** Use native injected `$page`, `$pages` or optional Functions API `pages()` (if `$config->useFunctionsAPI === true`).

## Orienting on an Existing Site (Fast Path)
When working on an unfamiliar ProcessWire site, start here:
1) Core CLI documentation: `php index.php docs list` and `php index.php docs vars`
2) `site/templates/`
3) `site/modules/`
4) `site/config.php`
5) Admin → Setup → Templates (fields per template)
6) Admin → Setup → Fields (all fields and types)

### Optional: AgentTools site-map (only if installed)
**AgentTools is an optional module** and will not be available in every project.
Use it only when you have verified the module is installed (e.g., it exists in `site/modules/AgentTools/` or via module listing/MCP).

If installed, it can generate a fast JSON snapshot for orientation:
`php index.php --at-sitemap-generate`
`php index.php --at-sitemap-generate-schema`

If not installed, fall back to:
- `pw_schema_read` (if MCP available) for templates/fields
- Reading `site/templates/` + `site/modules/` for architecture
