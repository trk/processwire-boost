# ProcessWire Core Logic & Variables

ProcessWire is a data-driven CMF where almost everything is represented as a `Page`. Always use the `$wire` API (or global variables like `$pages`, `$users`, `$templates`) for operations rather than direct SQL unless absolutely necessary. For detailed context-safe API access patterns, activate the `pw-api-variables` skill.

## Core API Variables

- **`$pages`**: `get(selector)` → single Page/NullPage, `find(selector)` → PageArray, `add(template, parent, name)`, `save($page)`. **NullPage instances**: use `$pages->newNullPage()` — never `new NullPage()` directly (won't be wired).
- **`$templates` & `$fields`**: `$templates->get(name)`, `$fields->get(name)` — schema access
- **`$users`, `$roles`, `$permissions`**: Access control — `$users->get(name)`, `$user->hasRole('editor')`
- **`$input`**: Safe request data — `$input->get->name`, `$input->post->name`, `$input->urlSegment(1)`
- **`$config`**: Paths and configuration — `$config->paths->templates`, `$config->urls->assets`
- **`$sanitizer`**: Input sanitization — `$sanitizer->text()`, `$sanitizer->selectorValue()`
- **`$database`**: Database access — Always access via property (e.g., `$this->wire()->database`), not method.

> [!NOTE]
> Inside classes use `$this->wire()->apiVar`. Inside hooks use `$event->wire()->apiVar`. In templates use native `$page`, `$pages` variables directly.
