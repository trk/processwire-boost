# ProcessWire Advanced Development

ProcessWire provides a hook system, custom page classes, and a modular plugin architecture. Activate `pw-module-development`, `pw-custom-page-classes`, or the specialized `pw-module-*` skills when working in these areas.

## Hooks (`WireHooks`)

Register hooks in `site/init.php`, `site/ready.php`, or a module's `init()` / `ready()` method:
- **`addHookBefore`**: Inspect or mutate `$event->arguments(0)` before execution, or set `$event->replace = true` and `$event->return = ...` to short-circuit the original method.
- **`addHookAfter`**: Inspect or modify `$event->return` or run side effects after execution (e.g., `Pages::saved`).
- **URL/Path Hooks**: Register virtual routes directly via `$wire->addHook('/api/items/{id}', ...)` (activate `pw-url-routing`).
- **Defining Hookable Methods**: Prefix hookable methods with three underscores (`public function ___methodName()`) in a `Wire`-derived class and document the unprefixed name in the class PHPDoc with `@method ReturnType methodName(...)`.

```php
$wire->addHookAfter('Pages::saved', function(HookEvent $event): void {
	/** @var Page $page */
	$page = $event->arguments(0);
	if ($page->template->name !== 'article') {
		return;
	}
	$event->wire()->log->save('articles', "Saved article {$page->id}");
});
```

## Custom Page Classes (`site/classes/`)

When `$config->usePageClasses = true`, ProcessWire maps templates to `[PascalCaseTemplate]Page` classes in `site/classes/` (e.g., template `blog-post` maps to `site/classes/BlogPostPage.php` in `namespace ProcessWire;`). Activate `pw-custom-page-classes` before creating or extending page classes.

## Module Architecture (`site/modules/`)

- **`Module` / `ConfigurableModule`**: Backend services and autoloaded hook providers (`pw-module-development`).
- **`Process`**: Admin controllers, dashboards, and sub-routes (`pw-module-process` & `pw-admin-ui`).
- **`Fieldtype` & `Inputfield`**: Custom database field types and form controls (`pw-module-fieldtype-inputfield`).
- **`Textformatter`**: Runtime string formatters applied when `$page->of()` is `true` (`pw-module-textformatter`).
- **`FileValidatorModule`**: Upload security scanners invoked by `InputfieldFile` and `$sanitizer->validateFile()` (`pw-module-filevalidator`).
- **`Markup`**: Reusable HTML rendering modules (`pw-module-markup`).

## Module Documentation & IDE Typing Conventions

### `API.md` Files & `#pw-` Docblock Tags
Modules and Fieldtypes that expose developer APIs should include an `API.md` file alongside the module code:
- `# ClassName` (H1 class name without "API" suffix) followed by a one-line summary.
- Sections: `## Value type`, `## Getting and setting values`, `## Selectors`, `## Output / markup`, `## Notes`.
- When writing core-compatible PHPDoc blocks, see `wire/core/Tools/WireApiDocs/HOWTO-API.md` for `#pw-summary`, `#pw-body`, `#pw-group-<name>`, `#pw-hooker`, and `#pw-internal` tags.

### `[Type]Field.php` Companion Classes
Each custom `Fieldtype` module should provide a `[Type]Field.php` companion class in the same directory extending `Field` with `@property` PHPDoc annotations for all configurable field and inputfield settings, and return its class name from `getFieldClass(): string`.
