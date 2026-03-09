# ProcessWire Schema API

You can manage the entire system schema (fields and templates) using the API.

## Fields

### Creating a Field

```php
$f = new Field();
$f->type = wire('fieldtypes')->get('FieldtypeText');
$f->name = 'my_field';
$f->label = 'My Field Label';
$f->save();
```

### Modifying a Field

```php
$f = $fields->get('my_field');
$f->label = 'Updated Label';
$f->save();
```

## Templates & Fieldgroups

Templates require a `Fieldgroup`.

### Creating a Template

```php
$fg = new Fieldgroup();
$fg->name = 'my_template';
$fg->add('title'); // Always include title
$fg->add('my_field');
$fg->save();

$t = new Template();
$t->name = 'my_template';
$t->fieldgroup = $fg;
$t->save();
```

## Deleting Schema

```php
$templates->delete($templates->get('my_template'));
$fields->delete($fields->get('my_field'));
```

> [!WARNING]
> Deleting a template or field also deletes all associated data in the database.
