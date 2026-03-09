---
name: manipulate_pages
description: Can create/edit/delete any Page using the $pages API. Expert in selector logic.
---

# ProcessWire Page Manipulation Guidelines

- Activation: Activate this skill when creating, editing, deleting, or finding pages in the ProcessWire ecosystem.
- Best Practices: Use `$pages->get()`, `$pages->find()`, and `$p->save()`. Ensure sanitizer is used for input.
- Selector logic: Always limit findings, use appropriate sort, and avoid `include=all` if possible.
