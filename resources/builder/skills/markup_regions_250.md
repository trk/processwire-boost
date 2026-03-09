# Markup Regions (3.0.250)
description: Populate regions by unique tags, class or tag; use pw-update.
## Blueprints
## Steps
- Populate unique tags without id: <head>, <title>, <body>, <main>
- Use class selector in pw-* value: .uk-container
- Use tag selector in pw-* value: <footer> or <footer.terms>
- Use pw-update to modify attributes (and optionally content)
## Request
Append CSS to <head>, prepend to .uk-container, update .uk-container class
## Response
Updated markup regions and attributes
## Example
```html
<!-- Populate unique tags without id -->
<head pw-append><link rel="stylesheet" href="/file.css"></head>
<title>Hello world</title>

<!-- Prepend content to all elements with uk-container class -->
<p class="uk-text-primary" pw-prepend=".uk-container">Hello world</p>

<!-- Append to footer(s) -->
<div pw-append="<footer.terms>"><p>Copyright 2025</p></div>

<!-- Update attributes on targets -->
<div pw-update=".uk-container" class="uk-container-large"></div>
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
