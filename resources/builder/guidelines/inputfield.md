# Inputfield
### Inputfield visibility: Tab (3.0.210)
- Any Inputfield can render as a page editor tab: 'Tab', 'Tab (AJAX)', 'Tab (locked)'.
- Configure from the field’s Input tab in admin.

### InputfieldForm::isSubmitted() (3.0.210)
- More reliable form submission check.
- Usage: `$form->isSubmitted()` before processing.

### InputfieldTextTags (3.0.184)
- Selectize-based tags/options input for fields. Enable from field input settings.

### Field dependencies (show-if / require-if)
- Show field if (visibility) and Required if (validation) accept selector-like expressions.
- Configure from field editor or field-template context; or via API:
```php
$f = $modules->get('InputfieldText');
$f->showIf = "other_field=1";
$f->required = 1;
$f->requiredIf = "name!=''";
```
