# ProcessWire Core Logic & Variables

ProcessWire is a data-driven CMF where almost everything is represented as a `Page`. Always use the `$wire` API (or global variables like `$pages`, `$users`, `$templates`) for operations rather than direct SQL unless absolutely necessary. For detailed context-safe API access patterns, activate the `pw-expert` skill.

## Core API Variables

- **`$pages`**: `get(selector)` → single Page/NullPage, `find(selector)` → PageArray, `add(template, parent, name)`, `save($page)`. **NullPage instances**: use `$pages->newNullPage()` — never `new NullPage()` directly (won't be wired).
- **`$templates` & `$fields`**: `$templates->get(name)`, `$fields->get(name)` — schema access
- **`$users`, `$roles`, `$permissions`**: Access control — `$users->get(name)`, `$user->hasRole('editor')`
- **`$input`**: Safe request data — `$input->get->name`, `$input->post->name`, `$input->urlSegment(1)`
- **`$config`**: Paths and configuration — `$config->paths->templates`, `$config->urls->assets`
- **`$sanitizer`**: Input sanitization — `$sanitizer->text()`, `$sanitizer->selectorValue()`
- **`$database`**: Database access — Always access via property (e.g., `$this->wire()->database`), not method. In ProcessWire core this is typically a `WireDatabasePDO` wrapper (PDO-like API), not a native `PDO` instance.

> [!NOTE]
> Inside classes use `$this->wire()->apiVar`. Inside hooks use `$event->wire()->apiVar`. In templates use native `$page`, `$pages` variables directly.

## Optional: Function API (`pages()`, `wire()`, etc.)
If `$config->useFunctionsAPI === true`, ProcessWire may expose helper functions like `pages()` and `wire()`.  
Prefer `$pages` / `$this->wire()->pages` in modules because it is more reliably bound to the correct ProcessWire instance.

## Orienting on an Existing Site (Fast Path)
When working on an unfamiliar ProcessWire site, start here:
1) `site/templates/`  
2) `site/modules/`  
3) `site/config.php`  
4) Admin → Setup → Templates (fields per template)  
5) Admin → Setup → Fields (all fields and types)

### Optional: AgentTools site-map (only if installed)
**AgentTools is an optional module** and will not be available in every project.
Use it only when you have verified the module is installed (e.g., it exists in `site/modules/AgentTools/` or via module listing/MCP).

If installed, it can generate a fast JSON snapshot for orientation:
`php index.php --at-sitemap-generate`

If not installed, fall back to:
- `pw_schema_read` (if MCP available) for templates/fields
- Reading `site/templates/` + `site/modules/` for architecture
