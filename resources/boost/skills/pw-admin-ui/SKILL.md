---
name: pw-admin-ui
description: "Use when designing, structuring, or rendering HTML for ProcessWire Admin interfaces, custom Process modules, or Inputfields."
risk: safe
source: processwire-boost
---

# ProcessWire AdminThemeUikit UI Design System

ProcessWire’s backend (`AdminThemeUikit`) uses native UIkit 3.x classes wrapped in customized CSS Custom Properties (`--pw-*`) that guarantee automatic Light/Dark mode adaptability and cross-module stylistic consistency. 

## When to Use
- Building or modifying `Process` module admin dashboards.
- Rendering HTML for custom `Fieldtype` or `Inputfield` modules inside the backend.
- Creating HTMX components intended for admin interfaces.
- Fixing UI bugs related to hardcoded colors or misaligned fields in ProcessWire.

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

### 2. Standard Admin Containers

#### The Inputfield Wrapper (`.pw-inputfield`)
ProcessWire forms wrap their fields in a standardized, collapsible "Inputfield" box. When creating custom UI forms or config screens, use this exact HTML structure instead of a standard `uk-card`:

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
- Admin warnings: `<div class="uk-alert uk-alert-primary" uk-alert>`
- Quiet field notes: `<div class="pw-notes"><strong>Note:</strong> Explain usage here.</div>`

### 5. UIkit 3 Core Elements
Ensure all standard markup uses native Uikit 3 classes:
- Forms: `uk-input`, `uk-select`, `uk-textarea`, `uk-checkbox`, `uk-radio`
- Tables: `<table class="uk-table uk-table-divider uk-table-small">`
- Buttons: `<button class="uk-button uk-button-primary">`, `uk-button-secondary`, `uk-button-danger`
- Icons: `<span uk-icon="icon:home;ratio:1.2"></span>` or `<button class="uk-icon-button" uk-icon="icon:pencil"></button>`

## Anti-Patterns & Loophole Closing
- ❌ **DO NOT use TailwindCSS or Bootstrap classes.** The admin is strictly UIkit 3 + PW overloads.
- ❌ **DO NOT use hardcoded colors (e.g., `#fff`, `#333`).** Always use `var(--pw-blocks-background)`, `var(--pw-text-color)`, etc., otherwise Dark Mode breaks entirely.
- ❌ **DO NOT invent custom CSS classes for layouts** before checking if `uk-grid`, `uk-flex-between`, or `uk-margin-top` can achieve the result.
- ❌ **DO NOT write media queries for dark mode** (`@media (prefers-color-scheme: dark)`). ProcessWire's built-in `var(--pw-...)` CSS variables handle this for you securely.

## Related Skills
- `pw-module-fieldtype-inputfield`
- `pw-htmx`
