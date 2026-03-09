# ProcessWire Security Guidelines

## Sanitization

Always sanitize input before saving to the database.

```php
$title = $sanitizer->text($input->post->title);
$email = $sanitizer->email($input->post->email);
```

## Permission Checks

Ensure the user has permission to perform an action.

```php
if ($user->hasPermission('page-edit', $page)) {
    // Authorized
}
```

## Preventing SQL Injection

Use ProcessWire's database API or selectors. Selectors automatically handle sanitization for database queries.

## Output Encoding

Always encode output in templates to prevent XSS.

```php
echo $sanitizer->entities($page->title);
```

Note: PW 3.x often handles this automatically via Output Formatting, but be explicit when needed.
