# ProcessWire API Variables Reference

ProcessWire provides several global API variables that are always available in templates, modules, and hooks.

## Core Variables

### $pages

The entry point for finding, creating, and manipulating pages.

- `$pages->get(selector)`: Get a single page.
- `$pages->find(selector)`: Find multiple pages.
- `$pages->add(template, parent, name)`: Create a new page.
- `$pages->save($page)`: Save changes.

### $templates & $fields

Access to the schema.

- `$templates->get(name)`: Access template properties and fields.
- `$fields->get(name)`: Access field properties.

### $users, $roles, $permissions

Access control system.

- `$users->get(name)`: Get a user.
- `$roles->get(name)`: Get a role.
- `$permissions->get(name)`: Get a permission.

## Input & Output

### $input

Access to GET, POST, and Cookie data.

- `$input->get->name`: Access `$_GET`.
- `$input->post->name`: Access `$_POST`.
- `$input->urlSegment(n)`: URL segments (1-indexed).

### $sanitizer

Essential for security.

- `$sanitizer->text($string)`: Clean plain text.
- `$sanitizer->email($email)`: Validate email.
- `$sanitizer->name($name)`: Sanitize for page/field names.

## System & Utilities

### $config

Site configuration and paths.

- `$config->paths->templates`: Path to templates directory.
- `$config->dbHost`, `$config->dbName`: Database info.

### $files

File system utilities.

- `$files->render($filename, $vars)`: Render a file with variables.

### $database

Direct access to the PDO instance.

- `$database->prepare($sql)`: Prepare a query.
