# Do Things the ProcessWire Way

- Use `wire('pages')`, `wire('modules')`, etc., in your code to access API variables.
- Use `wire()->addHookBefore('Pages::save', function($event) { ... });` to modify core behavior.
- Favor `wire('modules')->get('ModuleName')` over direct instantiation.

## Selectors

- Construct efficient selectors for `find()` calls.
- Use `$pages->count('selector')` instead of `count($pages->find('selector'))`.
- Use `$pages->get('selector')` when expecting exactly one result.

## Fields & Templates

- Define fields and templates using the API when programmatic management is necessary.
- Ensure field labels and descriptions are user-friendly.

## Security

- Always sanitize input using `$sanitizer->text($value)`, `$sanitizer->email($value)`, etc.
- Check user permissions using `$user->hasPermission('permission-name')`.
- Prevent CSRF in forms using `$session->CSRF->getTokenName()` and `$session->CSRF->getTokenValue()`.
