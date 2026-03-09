# ProcessWire Hooking System Guidelines

## Before Hooks

Intercept a method call before it executes.

```php
$wire->addHookBefore('Pages::save', function(HookEvent $event) {
    $page = $event->arguments(0);
    // Modify $page before saving
});
```

## After Hooks

Intercept a method call after it executes.

```php
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    // Log something or send an email
});
```

## Replace Hooks

Replace the entire method logic (rarely used).

```php
$wire->addHook('Page::hello', function(HookEvent $event) {
    $event->return = "Hello World!";
});
```

## Accessing Context

Inside a hook, use `$event->object` to access the object instance and `$event->arguments()` to get parameters.
