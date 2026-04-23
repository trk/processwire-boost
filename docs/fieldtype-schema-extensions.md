# Fieldtype Schema Extensions (Minimal Design Sketch)

## Why

> Note: Initial implementation includes a built-in repeater extender that supports both `FieldtypeRepeater` and `FieldtypeFieldsetPage`, writing configured subfield schema entries (`id`, `type`, `label`) to `fields.{fieldName}.fields`, keyed by subfield name.

Today, Boost map generation stores only a small field shape in `.agents/map.json`:

- `id`
- `type` (fieldtype class)
- `label`

That is useful, but not enough for complex fieldtypes like ProFields (e.g. RepeaterMatrix) or repeater-based core fieldtypes (e.g. FieldtypeFieldsetPage).

## Goals

- Keep the current map format backward-compatible.
- Allow fieldtype-specific metadata to be added safely.
- Make extension possible without editing Boost core each time.
- Fail safely: if extension logic breaks, base schema should still be generated.

## Non-goals

- No breaking change to existing `map.json` readers.
- No deep runtime crawling of content/page data.
- No mandatory dependency on Pro modules.

## Proposed minimal shape change

Keep existing field entry and add optional `extra` object for generic metadata. The extension key `fields` is reserved and promoted to a top-level nested field schema property:

```json
{
  "fields": {
    "content_blocks": {
      "id": 123,
      "type": "FieldtypeRepeaterMatrix",
      "label": "Content blocks",
      "extra": {
        "matrixTypes": ["hero", "text", "gallery"],
        "backingTemplate": "repeater_matrix_content_blocks"
      }
    }
  }
}
```

If no extender supports a field, `extra` is omitted. If an extender returns `fields`, it is written to `fields.{fieldName}.fields`; all other returned keys are written under `fields.{fieldName}.extra`.

## Proposed extension API

Introduce a tiny interface:

```php
<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Schema;

use ProcessWire\Field;

interface FieldSchemaExtender
{
    public function supports(Field $field): bool;

    /**
     * Return additional schema data for a supported field.
     *
     * The $base argument contains Boost's normalized field schema
     * (`id`, `type`, `label`) so extenders do not need to rebuild it.
     *
     * Reserved keys:
     * - `fields` is promoted to the top-level field schema. Use it for
     *   nested field definitions keyed by field or block type name.
     * - All other keys are written under the field's `extra` object.
     *
     * @param array{id:int,type:string,label:string} $base
     * @return array<string,mixed>
     */
    public function extend(Field $field, array $base): array;
}
```

## Discovery (minimal + practical)

Add one optional manifest file convention:

- `site/boost/schema/field-extenders.php`
- `site/modules/*/.agents/schema/field-extenders.php`
- `wire/modules/*/.agents/schema/field-extenders.php`

Each file returns class names or instances implementing `FieldSchemaExtender`.

Example manifest:

```php
<?php

return [
    \Site\Boost\Schema\RepeaterMatrixExtender::class,
    \Site\Boost\Schema\FieldsetPageExtender::class,
];
```

This keeps discovery aligned with Boost’s existing `.agents` module resource pattern.

## Generation flow

In map generation:

1. Build base field schema (`id/type/label`) exactly as now.
2. Resolve extenders once per run.
3. For each field:
   - run extenders where `supports($field) === true`
   - promote returned `fields` to `$base['fields']`
   - merge all other returned keys into `$base['extra']`
4. Wrap each extender check/call in `try/catch` and continue on failure.

Pseudo-flow:

```php
$base = ['id' => ..., 'type' => ..., 'label' => ...];

foreach ($extenders as $extender) {
    try {
        if (!$extender->supports($field)) continue;

        $extension = $extender->extend($field, $base);
        if (array_key_exists('fields', $extension)) {
            $base['fields'] = is_array($extension['fields']) ? $extension['fields'] : [];
            unset($extension['fields']);
        }

        if (!empty($extension)) {
            $base['extra'] = array_merge($base['extra'] ?? [], $extension);
        }
    } catch (\Throwable $e) {
        // log + continue; never fail full map generation
    }
}
```

## Example extenders (high level)

### RepeaterMatrix extender
Adds metadata such as:

- available matrix block types
- backing template name (if available)
- child field names per matrix type (if available)

### FieldtypeFieldsetPage extender
Adds metadata such as:

- backing template name
- child fields exposed by that template/fieldset

## Backward compatibility

- Existing keys remain unchanged.
- New metadata is additive under `extra`.
- Older agents/tools ignoring `extra` continue to work.

## Safety notes

- Extenders must avoid querying page content; schema-only metadata.
- Any missing module/class should be ignored gracefully.
- Invalid extender return data should be sanitized (arrays/scalars only).

## Minimal rollout plan

1. Add `FieldSchemaExtender` interface.
2. Add extender discovery + guarded merge in map generation.
3. Ship with no built-in extenders first (infrastructure only).
4. Add optional core extenders later (FieldsetPage, Repeater, RepeaterMatrix if available).

---

This is intentionally small: one interface, one manifest convention, one additive `extra` key.
