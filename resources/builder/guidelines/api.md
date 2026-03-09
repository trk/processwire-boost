# API
### API Variables Best Practices
- Use the ProcessWire namespace at the top of files: `<?php namespace ProcessWire;`
- Prefer `wire()->apiVar` or the functions API (e.g. `pages()`) for IDE support.
- In classes, prefer `$this->wire()->apiVar` over `$this->apiVar` or `$this->wire('apiVar')`.
- Avoid `wire('apiVar')` when you want type inference/IDE hints.
- Cache API vars locally if used repeatedly (especially in loops).

```php
// Anywhere
$items = wire()->pages->find("template=basic-page, limit=10");

// Functions API enabled
$items = pages()->find("template=basic-page, limit=10");

// In a class method
public function renderTitles(): string {
  $pages = $this->wire()->pages;
  $out = [];
  foreach($pages->find("template=basic-page") as $p) {
    $out[] = $p->title;
  }
  return implode(', ', $out);
}
```

### Database readers/writers (3.0.184)
- Configure separate read/write database connections; multiple readers are supported.
- Read queries automatically route to the reader connection(s).

```php
// /site/config.php
$config->dbReader = [
  'host' => 'replica-host',
  'user' => 'reader',
  'pass' => 'secret',
  'name' => $config->dbName,
  'port' => 3306,
];
// For multiple readers:
// $config->dbReader = [ [/*conn1*/], [/*conn2*/] ];
```

### Cache::renderFile (3.0.148)
- Render any PHP file with full API context and cache the output for reuse.

```php
$html = wire()->cache->renderFile('/path/to/file.php', [], 'cache-key', 3600);
```

### Datetime::elapsedTimeStr (3.0.148)
- Get a human-friendly difference between two times.

```php
echo wire()->datetime->elapsedTimeStr(strtotime('-2 hours'), time()); // "2 hours"
```

### Config additions (3.0.148)
- sessionForceIP: control the client IP source.
- statusFiles: specify optional include files per ProcessWire status/state.

```php
// /site/config.php
$config->sessionForceIP = 'x-forwarded-for';
$config->statusFiles = [
  'ready' => __DIR__ . '/ready.php',
  'init' => __DIR__ . '/init.php',
];
```

### Database helpers (3.0.148)
- allowTransaction($table): whether a transaction is allowed right now for table.
- tableExists($table): quickly check if a table exists.

```php
if(wire()->database->tableExists('pages')) { /* ... */ }
```

### Bootstrapping ProcessWire (include)
- Include ProcessWire’s index.php from external PHP/CLI to use the API.
- Use the ProcessWire namespace or fully qualified \ProcessWire\wire().
```php
<?php namespace ProcessWire;
include("/path/to/site/index.php");
$home = pages()->get('/');
foreach($home->children as $child) echo $child->title;
```

### Roles & permissions (RBAC)
- Roles group permissions and are assigned to users; users inherit all permissions from their roles.
- Default roles: guest (anonymous), superuser (all permissions).
- Some permissions require template context: enable access control on templates and assign roles there for granular view/edit/create/delete.
- Check role in API: `$user->hasRole('roleName')`

```php
if(wire()->user->hasRole('editor')) {
  echo "<h3>Editor notes</h3>" . $page->editor_notes;
}
```

Notes:
- Enable template access in admin: Setup > Templates > your_template > Access.
- Assign permissions to roles: Access > Roles > your_role (with per-template context where applicable).

### Permissions
- Permissions are Permission objects, assigned to Roles; Users get permissions via their Roles.
- Check a permission globally or in a Page context:
```php
if(wire()->user->hasPermission('page-edit')) { /* ... */ }
if(wire()->user->hasPermission('page-edit', $page)) { /* page-context check */ }
```
- Create a custom permission from the API, then assign it to a Role from admin:
```php
$p = wire()->permissions->add('can-publish-news');
$p->title = 'Can publish news';
$p->save();
```
