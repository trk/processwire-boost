# Hooks
### URL/Path Hooks (3.0.184)
```php
wire()->addHook('/hello/world', function($event) { return 'Hello World'; });
wire()->addHook('/hello/{name}', function($event) {
    $name = $event->arguments('name');
    return "Hello $name";
});
```

### Conditional Return Value Hooks (3.0.255+)
```php
// Call hook only when return value matches a condition
$wire->addHookAfter('Field::getInputfield:(label*=Currency)', function($e) {
    $f = $e->return;
    $e->message("Matched '$f->name' with label: $f->label");
});
```

### New Page Hooks (3.0.255+)
```php
// Example: new hookable methods on Page classes and Pages API
wire()->addHookAfter('Pages::addReady', function($e) {
    // Runs after a Page has been prepared for add
});
```
