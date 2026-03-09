# Fieldtype
### Fieldtype Presets (3.0.226)
- Fieldtype modules can expose predefined configurations when creating new fields.
- Lets you pick complex setups (e.g. “Multiple sortable pages using AsmSelect”) at creation time.
- You can also define your own hookable presets.

### Toggle field (3.0.148)
- New on/off Fieldtype offering a simple boolean-like selection with faster UI than a checkbox.

### Fields overview
- Fields store content and are reusable across templates; each field has a Fieldtype (storage) and an Inputfield (input UI).
- Common Fieldtypes: Text, Textarea, Page (references), Options, Images/Files, Repeater, etc.
- Inputfield is responsible for rendering and processing input; many Fieldtypes choose their Inputfield automatically, or let you select it.

### Textarea field basics
- Use Textarea for multi-line text or rich text (CKEditor).
- Configure “Text Formatters” and “Content Type” (Unknown vs Markup/HTML) in field settings.
- Example output:
```php
echo $page->body; // Textarea or CKEditor
```

### Select Options field basics
- Use a Select Options (FieldtypeOptions) field to store one or more choices from predefined options.
- Example output:
```php
foreach($page->options as $opt){ echo $opt->title; }
```

### Repeater basics
- Repeaters group fields into repeatable items; the value is a PageArray of items.
```php
foreach($page->buildings as $b){
  echo $b->title.' '.$b->year_built;
}
```

### Multi-language fields
- Multi-language text fields return the value for the current $user->language.
- To access a specific language:
```php
$de = wire()->languages->get('de');
echo $page->getLanguageValue($de, 'title');
```
