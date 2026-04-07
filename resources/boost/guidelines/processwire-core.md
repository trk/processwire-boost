# ProcessWire Core Logic & Variables

ProcessWire is a data-driven CMF where almost everything is represented as a `Page`. Always use the strict `$wire` API (or global variables like `$pages`, `$users`, `$templates`) for operations rather than direct SQL querying unless absolutely necessary for performance.

## Core API Variables (Globally available)

- **`$pages`**: The entry point for finding, creating, and manipulating pages.
  - `$pages->get(selector)`: Find a single page and return it. Returns a `NullPage` (which evaluates to false) if not found.
  - `$pages->find(selector)`: Find multiple pages and return a `PageArray`.
  - `$pages->add(template, parent, name)`: Shortcut to create a new page.
  - `$pages->save($page)`: Save unsaved changes to the database.

- **`$templates` & `$fields`**: Access to the schema.
  - `$templates->get(name)`: Access template properties and fields.
  - `$fields->get(name)`: Access field properties.

- **`$users`, `$roles`, `$permissions`**: Access control system.
  - `$users->get(name)`: Get a user.
  - `$roles->get(name)`: Get a role.
  - `$permissions->get(name)`: Get a permission.

- **`$input`**: Access to request data safely.
  - `$input->get->name`: Equivalent to `$_GET['name']` but safer.
  - `$input->post->name`: Equivalent to `$_POST['name']`.
  - `$input->urlSegment(1)`: Access URL segments.

- **`$config`**: Site configuration and paths.
  - `$config->paths->templates`
  - `$config->urls->assets`
  - `$config->dbHost`, `$config->dbName`

## API Examples (Modern PHP 8+)

### Creating a Page
```php
$p = new Page();
$p->template = "basic-page";
$p->parent = "/about/";
$p->name = "new-page";
$p->title = "New Page Title";
// Other custom fields
$p->custom_field = "Some data";
$p->save();
```

### Accessing Data
Use property accessors for cleaner code:
```php
// Good:
$title = $page->title;
$summary = $page->getUnformatted('summary'); // bypass textformatters if needed.
```

- When checking state:
  ```php
  if ($page->is("status=published")) { ... }
  ```

> [!NOTE]
> All ProcessWire variables are accessible inside classes by calling `wire('variableName')` or `wire()->variableName`. For example, `wire('pages')->find(...)`.
