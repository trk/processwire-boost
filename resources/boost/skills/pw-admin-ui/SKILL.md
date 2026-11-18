---
name: pw-admin-ui
description: "Use when designing, structuring, or rendering HTML for ProcessWire Admin interfaces, custom Process modules, or Inputfields."
risk: safe
source: processwire-boost
date_added: "2026-04-09"
---

# ProcessWire AdminThemeUikit Design System

ProcessWire's backend (`AdminThemeUikit` with the Konkat theme) is a native, token-aware design system built on UIkit 3.x and ProcessWire's custom CSS properties (`--pw-*`). It guarantees automatic Light/Dark mode adaptability, responsive mobile usability, and seamless cross-module stylistic consistency.

## Design System References (Sources of Truth)

- **Admin Theme Module:** `wire/modules/AdminTheme/AdminThemeUikit/AdminThemeUikit.module`
- **Theme Styles & CSS Variables:** `wire/modules/AdminTheme/AdminThemeUikit/uikit-pw/pw/`
- **Inputfield & Form Wrappers:** `wire/core/Inputfield/InputfieldWrapper.php`, `wire/modules/Inputfield/InputfieldForm/InputfieldForm.module`

---

## 1. Design Tokens (`--pw-*`) & Theming Standards

ProcessWire implements auto-theming natively using CSS `light-dark()`. **Never use explicit hex codes** (`#fff`, `#333`, `#eb1d61`) for backgrounds, borders, or text. Never write custom `@media (prefers-color-scheme: dark)` media queries.

### Official Token Reference

| Token | Purpose / Context |
|---|---|
| `--pw-main-color` | Primary accent color (active tabs, primary links, key highlights) |
| `--pw-text-color` | High-contrast neutral text (adapts light/dark automatically) |
| `--pw-muted-color` | Secondary / muted text, icons, inactive tabs, captions |
| `--pw-border-color` | All panel, input, table, and divider borders |
| `--pw-main-background` | Canvas / page background outside panels |
| `--pw-blocks-background` | Panel, card, dropdown, and form block backgrounds |
| `--pw-inputs-background` | Input fields, striping, inactive icon buttons |
| `--pw-button-background` | Default button fill (`uk-button-primary`, `uk-button-default`) |
| `--pw-button-color` | Text color on default buttons |
| `--pw-button-muted-background` | Muted button fill (`uk-button-secondary`) |
| `--pw-button-hover-background` | Hover background for action buttons |
| `--pw-button-hover-color` | Hover text color for action buttons |
| `--pw-button-radius` | `99999px` (Konkat pill button standard) |
| `--pw-input-radius` | `0` (Clean boxy input standard) |
| `--pw-alert-primary` | Info alert background |
| `--pw-alert-success` | Success alert and badge background |
| `--pw-alert-warning` | Warning alert and badge background |
| `--pw-alert-danger` | Danger alert and error background |
| `--pw-error-inline-text-color` | Inline error messages, required asterisks (`*`) |
| `--pw-notes-background` | Notes and highlight background |
| `--pw-code-color` / `--pw-code-background` | Inline and block code formatting |
| `--pw-spacing` | Spacing standard (`25px` desktop, `20px` tablet, `15px` mobile) |

> [!WARNING]
> **Do NOT invent fake tokens** (e.g. `--pw-input-bg-color`, `--pw-body-bg-color`, `--pw-card-bg`). Only use the official tokens listed above.

---

## 2. The 3 Admin UI Architectural Domains

### Domain A: `Process` Modules (Dashboards, Tools & Workspaces)

For custom admin applications, consoles, and dashboards extending `Process`, combine UIkit 3 utilities with a module-scoped workspace layout (`.pw-module-workspace` — note that `.pw-module-*`, `.pw-stat-*`, `.pw-filter-*`, `.pw-bulk-bar`, `.pw-table-*`, and `.pw-empty-state` are recommended module-stylesheet layout classes built on `--pw-*` tokens, whereas `uk-*`, `pw-button-dropdown-wrap`, `pw-notes`, and `PageListActions` come directly from `AdminThemeUikit`):

```html
<div class="ProcessReportManager pw-wrap pw-module-workspace">
  <!-- 1. Primary Navigation Tabs -->
  <ul class="uk-tab pw-module-tabs" aria-label="Module sections">
    <li class="uk-active"><a href="./">Dashboard</a></li>
    <li><a href="./entries/">Entries</a></li>
    <li><a href="./moderation/">Moderation <span class="uk-badge">3</span></a></li>
    <li><a href="./settings/">Settings</a></li>
  </ul>

  <!-- 2. Header & Top Actions -->
  <div class="pw-module-head">
    <div>
      <h3 class="uk-h3">Entries Management</h3>
      <p class="uk-text-muted">Review content, system state, and recent activity.</p>
    </div>
    <div class="pw-module-actions">
      <a href="./export/" class="uk-button uk-button-default">Export CSV</a>
      <a href="./add/" class="uk-button uk-button-primary">Create Entry</a>
    </div>
  </div>

  <!-- 3. Stat / KPI Cards (Optional) -->
  <div class="uk-grid-small uk-child-width-1-3@m pw-stat-grid uk-margin" uk-grid>
    <div>
      <div class="uk-card uk-card-default uk-card-small uk-card-body">
        <span class="uk-text-meta">Published</span>
        <strong class="pw-stat-value">128</strong>
      </div>
    </div>
    <div>
      <div class="uk-card uk-card-default uk-card-small uk-card-body">
        <span class="uk-text-meta">Pending</span>
        <strong class="pw-stat-value">3</strong>
      </div>
    </div>
    <div>
      <div class="uk-card uk-card-default uk-card-small uk-card-body">
        <span class="uk-text-meta">This Month</span>
        <strong class="pw-stat-value">24</strong>
      </div>
    </div>
  </div>

  <!-- 4. Filter Panel -->
  <form class="pw-filter-panel uk-margin" method="get">
    <div class="pw-filter-row">
      <input class="uk-input" type="search" name="q" placeholder="Search entries...">
      <select class="uk-select" name="status">
        <option value="">Any status</option>
        <option value="published">Published</option>
        <option value="pending">Pending</option>
      </select>
      <button class="uk-button uk-button-primary" type="submit">Filter</button>
      <a href="./" class="uk-button uk-button-default">Clear</a>
    </div>
  </form>

  <!-- 5. Bulk Action Bar (Only visible upon selection) -->
  <div class="pw-bulk-bar uk-margin-small-bottom">
    <span><span class="uk-label">2 selected</span></span>
    <button class="uk-button uk-button-default uk-button-small" type="submit" name="action" value="publish">Publish</button>
    <button class="uk-button uk-button-danger uk-button-small" type="submit" name="action" value="delete">Delete</button>
  </div>

  <!-- 6. Responsive Data Table Panel -->
  <div class="pw-table-panel">
    <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
      <thead>
        <tr>
          <th class="uk-table-shrink"><input class="uk-checkbox" type="checkbox" aria-label="Select all"></th>
          <th>Title</th>
          <th>Type</th>
          <th>Status</th>
          <th>Modified</th>
          <th class="uk-text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><input class="uk-checkbox" type="checkbox"></td>
          <td>
            <a href="./edit/?id=101">Welcome Guide</a>
            <div class="uk-text-small uk-text-muted">Started by admin</div>
          </td>
          <td><span class="uk-label">Article</span></td>
          <td><span class="uk-label uk-label-success">Published</span></td>
          <td>Today, 10:42</td>
          <td>
            <div class="pw-table-actions">
              <span class="pw-pagelist-actions">
                <a href="./edit/?id=101">Edit</a>
                <a href="./view/?id=101">View</a>
              </span>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 7. Empty State (When no records match) -->
  <div class="pw-empty-state uk-margin-top">
    <span uk-icon="icon:file-text;ratio:1.5"></span>
    <h4 class="uk-h4">No matching entries</h4>
    <p class="uk-margin-small-top">Clear the filters or create your first entry.</p>
    <a href="./add/" class="uk-button uk-button-primary">Add Entry</a>
  </div>
</div>
```

---

### Domain B: `Inputfield` Modules & Settings Forms

For settings, configuration pages, or custom field inputs, **prefer ProcessWire's Inputfield Form API**. Do not recreate inputfields using random card structures.

#### Native PHP Form Construction:
```php
/** @var InputfieldForm $form */
$form = $this->wire()->modules->get('InputfieldForm');
$form->action = './';
$form->method = 'post';

/** @var InputfieldText $titleField */
$titleField = $this->wire()->modules->get('InputfieldText');
$titleField->name = 'title';
$titleField->label = $this->_('Title');
$titleField->required = true;
$titleField->description = $this->_('The primary title of this record.');
$form->add($titleField);

/** @var InputfieldFieldset $fieldset */
$fieldset = $this->wire()->modules->get('InputfieldFieldset');
$fieldset->label = $this->_('Settings');

/** @var InputfieldSelect $statusField */
$statusField = $this->wire()->modules->get('InputfieldSelect');
$statusField->name = 'status';
$statusField->label = $this->_('Status');
$statusField->addOptions(['published' => 'Published', 'hidden' => 'Hidden']);
$fieldset->add($statusField);

$form->add($fieldset);
return $form->render();
```

#### Native Wrapper Markup (When Authoring Custom Inputfield HTML):
```html
<ul class="Inputfields">
  <li id="wrap_Inputfield_title" class="Inputfield InputfieldText Inputfield_title InputfieldStateRequired">
    <label class="InputfieldHeader ui-widget-header" for="Inputfield_title">
      <i class="toggle-icon fa fa-fw fa-angle-down"></i>Title
    </label>
    <div class="InputfieldContent ui-widget-content">
      <input id="Inputfield_title" class="uk-input" type="text" name="title" value="Home Page">
      <p class="description">Required page title.</p>
      <!-- Error State (when validation fails) -->
      <!-- <p class="InputfieldError ui-state-error"><i class="fa fa-fw fa-flash"></i><span>Field is required.</span></p> -->
    </div>
  </li>
</ul>
```

---

### Domain C: `Markup` Modules (Tables, Buttons & Components)

#### 1. Save Button with Split Dropdown (`pw-button-dropdown-wrap`)
Used for primary save actions with secondary options:
```html
<span class="pw-button-dropdown-wrap">
  <button class="uk-button uk-button-primary ui-button ui-widget ui-state-default pw-head-button pw-button-dropdown-main" type="submit" name="submit_save">
    <span class="ui-button-text">Save</span>
  </button>
  <button class="uk-button uk-button-primary ui-button ui-widget ui-state-default pw-head-button pw-dropdown-toggle pw-button-dropdown-toggle" type="button" aria-haspopup="true">
    <i class="fa fa-angle-down"></i>
  </button>
</span>
```

#### 2. Page Actions (`.pw-pagelist-actions`)
Compact action badges that match ProcessWire page trees:
```html
<span class="pw-pagelist-actions">
  <a href="./edit/?id=1">Edit</a>
  <a href="./view/?id=1">View</a>
</span>
```

#### 3. Alerts & Notes
- Dashboard alerts:
  ```html
  <div class="uk-alert uk-alert-primary" uk-alert>Notice information.</div>
  <div class="uk-alert uk-alert-success" uk-alert>Operation completed successfully.</div>
  <div class="uk-alert uk-alert-warning" uk-alert>Requires administrator attention.</div>
  <div class="uk-alert uk-alert-danger" uk-alert>An error occurred.</div>
  ```
- Inline settings notes:
  ```html
  <div class="pw-notes"><strong>Note:</strong> Configuration takes effect on next request.</div>
  ```

---

## 3. Responsive & Mobile Rules

On screens `< 640px`:
1. `.pw-module-head` stacks vertically (`align-items: stretch`).
2. `.pw-module-actions` and its buttons expand to `width: 100%`.
3. Filter row inputs expand to `width: 100%`.
4. Tables must be wrapped in `.pw-table-panel` or `<div class="uk-overflow-auto">` with a `min-width: 680px` so horizontal scroll prevents container clipping.

---

## 4. Custom CSS Scoping & Isolation

Whenever writing module-specific CSS:
1. **Scope under the module root class:**
   ```css
   .ProcessReportManager .report-badge {
     background-color: var(--pw-main-color);
     color: var(--pw-blocks-background);
   }
   ```
2. **Never leak global selectors:** Do NOT style bare `table`, `button`, `input`, or `.uk-card` tags globally.
3. **Use existing UIkit utility classes first:** `uk-margin-*`, `uk-grid-*`, `uk-flex-*`, `uk-width-*` before writing custom CSS rules.

---

## 5. Headline & Breadcrumb Control in Process Modules

`AdminThemeUikit` renders the page headline inside `_content-head.php` via `AdminThemeFramework::renderFile()` (`___renderFile` is hookable; `getHeadline()` is not hookable).

To completely hide both headline and breadcrumbs for a custom `Process` module:
```php
public function init(): void
{
	parent::init();
	$wire = $this->wire();

	$wire->addHookBefore('AdminThemeFramework::renderFile', function (HookEvent $event): void {
		if (!($event->wire()->process instanceof self)) {
			return;
		}
		if (basename((string) $event->arguments(0)) !== '_content-head.php') {
			return;
		}
		$event->replace = true;
		$event->return = '';
	});

	$wire->addHookBefore('AdminThemeUikit::renderBreadcrumbs', function (HookEvent $event): void {
		if (!($event->wire()->process instanceof self)) {
			return;
		}
		$event->replace = true;
		$event->return = '';
	});
}
```

---

## 6. AJAX & Partial HTML Endpoints in Admin (`Process` Modules)

When building dynamic partial updates inside `Process` modules:
1. **Separate Full Page vs Partial Endpoint:**
   - Full page (`___execute`): Renders complete layout within admin chrome.
   - Partial endpoint (`___executePartial`): Verifies `$config->ajax` and returns fragment HTML or JSON directly without admin masthead/breadcrumbs:

```php
public function ___executePartial(): string
{
	$config = $this->wire()->config;
	$sanitizer = $this->wire()->sanitizer;

	if (!$config->ajax) {
		$this->wire()->session->redirect('./');
		return '';
	}

	$label = $sanitizer->entities($this->_('Updated panel content'));
	header('Content-Type: text/html; charset=utf-8');
	echo "<div class='pw-panel-body'>{$label}</div>";
	exit; // Prevents AdminTheme from wrapping partial output in admin chrome
}
```
2. **Preserve CSRF Protection on State-Mutating Requests:** Always validate `$this->wire()->session->CSRF->validate()` on `POST` actions before mutating state.

---

## Anti-Patterns to Avoid

- ❌ **DO NOT use TailwindCSS or Bootstrap classes.** The admin is strictly UIkit 3 + PW overloads.
- ❌ **DO NOT use hardcoded colors (`#fff`, `#111`, `#eb1d61`).** Always use `var(--pw-*)` variables.
- ❌ **DO NOT recreate Inputfields with `uk-card` markup.** Use the Inputfield Form API for settings and edit screens.
- ❌ **DO NOT invent fake token names** (`--pw-input-bg-color`, `--pw-card-bg`). Use `--pw-inputs-background`, `--pw-blocks-background`.
- ❌ **DO NOT write media queries for dark mode.** CSS variables with `light-dark()` handle themes automatically.
- ❌ **DO NOT let data tables overflow without horizontal scroll.** Always wrap tables in `.pw-table-panel` or `<div class="uk-overflow-auto">`.
