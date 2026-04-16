---
name: pw-admin-ui
description: "Use when designing, structuring, or rendering HTML for ProcessWire Admin interfaces, custom Process modules, or Inputfields."
risk: safe
source: processwire-boost
---

# ProcessWire AdminThemeUikit UI Design System

ProcessWire’s backend (`AdminThemeUikit`) uses native UIkit 3.x classes wrapped in customized CSS Custom Properties (`--pw-*`) that guarantee automatic Light/Dark mode adaptability and cross-module stylistic consistency. 

**Design system reference (source of truth):**
https://raw.githubusercontent.com/mxmsmnv/pw-design-system/refs/heads/main/Draft-AdminThemeUikit-DesignSystem-UIKit.html

## When to Use
- Building or modifying `Process` module admin dashboards.
- Rendering HTML for custom `Fieldtype` or `Inputfield` modules inside the backend.
- Creating HTMX components intended for admin interfaces.
- Fixing UI bugs related to hardcoded colors or misaligned fields in ProcessWire.

## RED Baseline Failures (Observed in Real Work)
These are common failure modes when an agent "tries to be helpful" without correctly following AdminThemeUikit conventions:
- **Layout confusion:** mixing `.pw-inputfield` (collapsible settings widgets) and `uk-card` (dashboards) randomly, resulting in inconsistent admin UX.
- **Admin chrome nested inside partials:** returning Process HTML from an HTMX partial endpoint causes the full admin frame to render inside the content area.
- **HTMX component tampering:** nesting state-aware components inside another state-aware component (and swapping outerHTML) leads to `State payload does not belong to this component`.
- **Headline/breadcrumb won't go away:** setting `$this->headline('')` is not sufficient because AdminTheme falls back to page title.

Use the rules below to prevent these.

## Baseline & Context (The "RED" Scenario)
If an agent attempts to build an admin UI without this skill, they typically rely on raw `uk-card` structures, hardcode standard hex colors (e.g. `#ffffff` or `#eaeaea`), or attempt to write custom media queries for dark mode. This causes severe theme breakage when the user toggles dark mode or changes the primary accent color.

## Core Concepts & Standards

### 1. The Custom Properties (Auto-Theming)
ProcessWire implements `color-scheme: light dark;` natively via the `light-dark()` CSS function on the following variables. **Never use explicit hex codes for backgrounds, borders, or standard text.**

Use the following `--pw-*` variables in your CSS or style tags:
- `var(--pw-main-color)`: Primary accent color (e.g., brand red/blue).
- `var(--pw-text-color)`: Neutral text (auto-adjusts light/dark).
- `var(--pw-muted-color)`: Faded text or subtle borders.
- `var(--pw-main-background)`: For the main page canvas.
- `var(--pw-blocks-background)`: For card and form block backgrounds.
- `var(--pw-inputs-background)`: For input fields and striping.
- `var(--pw-border-color)`: All panel, input, and structural borders.

**Additional tokens that frequently matter in real UIs (from the reference):**
- `--pw-button-background`, `--pw-button-color`, `--pw-button-hover-background`, `--pw-button-hover-color`
- `--pw-alert-primary`, `--pw-alert-warning`, `--pw-alert-success`, `--pw-alert-danger`
- `--pw-code-color`, `--pw-code-background`
- `--pw-button-radius`, `--pw-input-radius`

### 2. Standard Admin Containers

#### Decision Matrix: `uk-card` vs `.pw-inputfield` (CRITICAL)
Pick ONE primary container style per screen:

**Use `uk-card` when:**
- The UI is a dashboard / SPA-like tool / feature panel (queue, backups, migrations, etc.).
- You need multiple sections with headers/footers and clear separation.
- You are building a “console” page or data tables.

**Use `.pw-inputfield` when:**
- The UI is a settings/config form that should look like native module config.
- You need collapsible sections that behave like ProcessWire Inputfields.

❌ **Do not** embed `.pw-inputfield` “widgets” inside cards unless you are intentionally mimicking a module config screen inside a card (rare).

#### The Inputfield Wrapper (`.pw-inputfield`)
When building module config screens (not dashboards), use this exact HTML structure:

```html
<div class="pw-inputfield">
  <div class="pw-inputfield-header pw-bold">
    Your Field Title <span uk-icon="icon:chevron-down;ratio:0.7" class="uk-float-right"></span>
  </div>
  <div class="pw-inputfield-content">
    <p class="uk-text-small uk-text-muted">A description for the field goes here.</p>
    <input class="uk-input" type="text" placeholder="Value...">
  </div>
</div>
```
*(Add `.collapsed` to the `.pw-inputfield` wrapper if it should be collapsed by default)*

#### Page Actions (`.pw-pagelist-actions`)
Link buttons shown near page titles or entities that behave like the ProcessWire PageTree action buttons:
```html
<span class="pw-pagelist-actions">
  <a href="#">Edit</a>
  <a href="#">View</a>
</span>
```

### 3. The "Save + Dropdown" Button Pattern
When rendering the primary "Save" button for complex modules, utilize the split dropdown toggle ProcessWire provides:

```html
<span class="pw-button-dropdown-wrap">
  <button class="pw-head-button" type="submit" name="submit_save">Save</button>
  <button class="pw-button-dropdown-toggle" type="button">▾</button>
</span>
```

### 4. Alerts & Notes
**Preferred (dashboards/tools):** use UIkit alerts inside cards.
- Warnings: `<div class="uk-alert uk-alert-warning" uk-alert>…</div>`
- Notes/info: `<div class="uk-alert uk-alert-primary" uk-alert>…</div>`
- Success: `<div class="uk-alert uk-alert-success" uk-alert>…</div>`
- Errors: `<div class="uk-alert uk-alert-danger" uk-alert>…</div>`

**Preferred (config forms):** `pw-notes` for short inline hints:
`<div class="pw-notes"><strong>Note:</strong> …</div>`

### 5. UIkit 3 Core Elements
Ensure all standard markup uses native Uikit 3 classes:
- Forms: `uk-input`, `uk-select`, `uk-textarea`, `uk-checkbox`, `uk-radio`
- Tables: `<table class="uk-table uk-table-divider uk-table-small">`
- Buttons: `<button class="uk-button uk-button-primary">`, `uk-button-secondary`, `uk-button-danger`
- Icons: `<span uk-icon="icon:home;ratio:1.2"></span>` or `<button class="uk-icon-button" uk-icon="icon:pencil"></button>`

## Process Modules: Headline/Breadcrumb Control
AdminThemeUikit renders headline/breadcrumb from `AdminThemeFramework::getHeadline()` and will **fallback to the page title**.

✅ To hide both headline and breadcrumbs for a specific Process module, hook and replace:
```php
<?php
declare(strict_types=1);

namespace ProcessWire;

class ProcessFoo extends Process {
  public function init(): void {
    parent::init();
    $wire = $this->wire();
    $isThis = fn() => $wire->process instanceof self;

    $wire->addHookBefore('AdminThemeFramework::getHeadline', function($event) use ($isThis) {
      if(!$isThis()) return;
      $event->replace = true;
      $event->return = '';
    });

    $wire->addHookBefore('AdminThemeUikit::renderBreadcrumbs', function($event) use ($isThis) {
      if(!$isThis()) return;
      $event->replace = true;
      $event->return = '';
    });
  }
}
```

## HTMX Mini-SPA in Admin (Process modules)
When building SPA-like Process modules:

✅ **Rule 1:** Navigation links should `hx-get` a **partial endpoint** and `hx-push-url` a friendly URL.
```html
<a href="/admin/setup/console/?view=queue"
   hx-get="/admin/setup/console/partial/?view=queue"
   hx-target="#console-content"
   hx-swap="innerHTML"
   hx-push-url="/admin/setup/console/?view=queue">
  Queue
</a>
```

✅ **Rule 2:** The partial endpoint must output RAW HTML (no admin chrome):
```php
public function ___executePartial(): string {
  /** @var Htmx $htmx */
  $htmx = $this->wire()->modules->get('Htmx');
  $html = $htmx->renderComponent(MyComponent::class, [...]);
  header('Content-Type: text/html; charset=utf-8');
  echo $html;
  exit;
}
```

✅ **Rule 3:** Avoid HTMX component tampering: do not nest state-aware components inside another state-aware component during `/hx/req` swaps.
Instead, swap the outer shell, then `hx-get` the inner view content separately (or swap only inner content).

## Pressure Scenarios / Tests (pw-writing-skills)
Use these as the “RED -> GREEN -> REFACTOR” checklist to verify this skill actually prevents failures.

### Test 1 — Container Choice (Cards vs Inputfields)
**RED (without skill):** Agent mixes `uk-card` sections with `.pw-inputfield` widgets inside a dashboard page.  
**GREEN (with skill):** Agent picks one primary container style per screen using the decision matrix.
- [ ] Dashboard/tool screens use `uk-card` sections.
- [ ] Config screens use `.pw-inputfield` sections.
- [ ] No random `.pw-inputfield` blocks embedded in card dashboards.

### Test 2 — HTMX Partial Does Not Render Admin Chrome
**RED:** Agent returns a string from `___executePartial()` and the response contains admin masthead/breadcrumb/headline HTML.  
**GREEN:** Partial endpoint outputs raw HTML only.
- [ ] Partial endpoint uses `header('Content-Type: text/html; charset=utf-8'); echo $html; exit;`
- [ ] Network response for `/partial/` contains only component HTML, not full admin page markup.

### Test 3 — HTMX Component Tampering Avoidance
**RED:** Agent nests state-aware components inside another state-aware component and swaps `outerHTML`, producing:  
`HTMX Component Tampering Detected: State payload does not belong to this component.`
**GREEN:** Agent avoids nesting/hydrating mismatched state payloads.
- [ ] Shell swaps do not attempt to hydrate inner components with the shell state payload.
- [ ] Inner content is loaded via separate `hx-get` to `/partial/` or only inner content is swapped.

### Test 4 — Headline/Breadcrumb Truly Hidden
**RED:** Agent sets `$this->headline('')` but headline still renders due to AdminTheme fallback.  
**GREEN:** Agent hooks `AdminThemeFramework::getHeadline` and `AdminThemeUikit::renderBreadcrumbs` with `replace=true`.
- [ ] `<h1 id='pw-content-title'>` not present for the Process page.
- [ ] Breadcrumbs HTML not present for the Process page.

## Anti-Patterns & Loophole Closing
- ❌ **DO NOT use TailwindCSS or Bootstrap classes.** The admin is strictly UIkit 3 + PW overloads.
- ❌ **DO NOT use hardcoded colors (e.g., `#fff`, `#333`).** Always use `var(--pw-blocks-background)`, `var(--pw-text-color)`, etc., otherwise Dark Mode breaks entirely.
- ❌ **DO NOT invent custom CSS classes for layouts** before checking if `uk-grid`, `uk-flex-between`, or `uk-margin-top` can achieve the result.
- ❌ **DO NOT write media queries for dark mode** (`@media (prefers-color-scheme: dark)`). ProcessWire's built-in `var(--pw-...)` CSS variables handle this for you securely.
- ❌ **DO NOT return** strings from HTMX partial Process actions if you expect “partial HTML only”. ProcessWire will wrap it in admin chrome.
- ❌ **DO NOT mix** dashboard `uk-card` layout with `.pw-inputfield` widget layout unless the screen is explicitly a “settings form”.

## Related Skills
- `pw-module-fieldtype-inputfield`
- `pw-htmx`
