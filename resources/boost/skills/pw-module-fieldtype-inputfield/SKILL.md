---
name: pw-module-fieldtype-inputfield
description: Use when building custom database fieldtypes and their corresponding interface inputfields in ProcessWire.
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# Data and Form Management (wire-fieldtype-inputfield)

When creating custom data types within ProcessWire, the architecture is strictly split into two independent modules:

1. **Fieldtype (Database Layer):** Manages the database schema, waking data up from the database (`wakeupValue`), saving data back (`sleepValue`), and defining absolute empty states (`getBlankValue`).
2. **Inputfield (Interface Layer):** Handles the localized formatting of the data presented as HTML on admin interface forms or frontends (`render`), and intercepts values post-submission over HTTP (`processInput`).

When rendering Inputfields locally, you must always wrap them securely using the native Form API classes (`InputfieldWrapper`, `InputfieldForm`).

## Pre-Computation / Anti-Rationalization Check

Check these specific markers before attempting execution:

- **When authoring a Fieldtype:** Have you fully specified the `getDatabaseSchema()` method? Standard schemas map `data` components appropriately while integrating indexing features.
- **When authoring an Inputfield:** While constructing dynamic HTML output strings in `render()`, have you encapsulated user variables within `htmlspecialchars()` or utilized explicit sanitizer functions to proactively deny XSS vulnerabilities?
- **Defining Blank Values (`getBlankValue`):** Have you correctly identified what constitutes a definitively empty value within your specific ecosystem? Should it be `null`, a blank string `""`, `0`, or a customized class object?

## Execution Phases

### Phase 1: Database Logic Extensibility (`FieldtypeCustom`)

This class defines precisely how the database indexes, holds, and manipulates raw backend records.

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class FieldtypeCustom extends Fieldtype
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Custom Fieldtype',
            'version' => 100,
            'summary' => 'Encapsulates complex string manipulations natively in the DB.',
            'installs' => 'InputfieldCustom', // Triggers combined module installations securely
        ];
    }

    // Indicates the paired Inputfield for automatic administration assignments
    public function getInputfield(Page $page, Field $field): Inputfield
    {
        return $this->wire()->modules->get('InputfieldCustom');
    }

    // How the schema maps physically into MariaDB / MySQL architectures
    public function getDatabaseSchema(Field $field): array
    {
        $schema = parent::getDatabaseSchema($field);
        // Elevate standard data allocations explicitly
        $schema['data'] = 'varchar(512) NOT NULL DEFAULT ""';
        return $schema;
    }

    // Transformations triggered when data leaves the database mapping back into PHP
    public function wakeupValue(Page $page, Field $field, $value)
    {
        if (empty($value)) {
            return $this->getBlankValue($page, $field);
        }
        return (string) $value;
    }

    // Restructuring PHP payloads correctly to slide cleanly back into database persistence
    public function sleepValue(Page $page, Field $field, $value)
    {
        return $this->wire()->sanitizer->text($value);
    }

    // Baseline definitions
    public function getBlankValue(Page $page, Field $field): string
    {
        return '';
    }
}
```

### Phase 2: User Interface Implementation (`InputfieldCustom`)

Registers directly onto Form APIs for UI implementations capable of isolating inputs intuitively.

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class InputfieldCustom extends Inputfield
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Custom Inputfield',
            'version' => 100,
            'summary' => 'Complex UI rendering interface targeting the Custom Fieldtype.',
            'requires' => 'FieldtypeCustom'
        ];
    }

    // Structural HTML mappings required to present the form element physically
    public function render(): string
    {
        // Essential properties mapped instantly
        $id = $this->attr('id');
        $name = $this->attr('name');

        // Anti-XSS operations natively enforced utilizing absolute quoting
        $val = htmlspecialchars((string) $this->attr('value'), ENT_QUOTES, 'UTF-8');

        $out = "<div class='uk-inline uk-width-1-1'>";
        $out .= "<span class='uk-form-icon' uk-icon='icon: star'></span>";
        $out .= "<input type='text' id='{$id}' name='{$name}' value='{$val}' class='uk-input uk-form-large' />";
        $out .= "</div>";

        return $out;
    }

    // Process mapped form submissions executing upon explicit triggers
    public function processInput(WireInputData $input): self
    {
        $name = $this->attr('name');
        if (!isset($input->$name)) {
            return $this;
        }

        // Critical Operation: Purge via native sanitizer elements
        $value = $this->wire()->sanitizer->text($input->$name);

        if ($value !== $this->attr('value')) {
            $this->attr('value', $value);
            $this->trackChange('value');
        }

        return $this;
    }
}
```

### Phase 3: Engaging with the Form API

Refrain from manually creating random HTML structures for dynamic forms on Admin/Frontend contexts. Embrace and inject through the ProcessWire Form API core structures dynamically.

```php
$form = $this->wire()->modules->get('InputfieldForm');
$form->action = './submit';
$form->method = 'post';
$form->attr('id', 'my-custom-form');

// Isolating and building functional elements structurally
$f = $this->wire()->modules->get('InputfieldText');
$f->name = 'full_name';
$f->label = 'Full Name';
$f->required = true;
$form->add($f);

// Organizing arrays systematically utilizing fieldsets
$fieldset = $this->wire()->modules->get('InputfieldFieldset');
$fieldset->label = 'Advanced Options';

$f = $this->wire()->modules->get('InputfieldCustom');
$f->name = 'special_code';
$f->label = 'Special Parameter Code';
$fieldset->add($f);

$form->add($fieldset);

// Pushing raw executions successfully through render chains
echo $form->render();
```

## Essential Tools & Ecosystem

- Target classes bound within `ProcessWire\Fieldtype` operations (`FieldtypeText`, `FieldtypePage`, etc.).
- Target classes restricted inside `ProcessWire\Inputfield` ecosystems (`InputfieldForm`, `InputfieldSubmit`).
- Inheriting the generalized administrative UX protocols utilizing UIkit 3 configurations (`uk-input`, `uk-button`).

## Copy-Paste Prompts

(Pass these direct prompts to the agent to initiate workflows instantly)

**[Complex Fieldtype Prototyping]**

> "Generate an advanced Fieldtype designated to store array elements containing geographical latitude and longitude coordinates. Name it `FieldtypeGeoLocation`. Restructure the `getDatabaseSchema` mapping native FLOAT data columns physically labeled `lat` and `lng`. Transform incoming data appropriately traversing between JSON strings or generalized associative arrays inside SleepValue and WakeupValue integrations securely. Standardize blank return values strictly targeting an empty `{lat:0, lng:0}` map."

**[Complex Inputfield Custom Scaffolding]**

> "Develop a functional input tracking UI component named `InputfieldGeoLocation`. The `render()` execution string must physically generate exactly two discrete HTML mapped inputs. Map the physical interface elements dynamically relying strictly upon specific UIkit 3 Grid utilities structured across `uk-grid uk-child-width-1-2`."

## Context Awareness (ProcessWire API Docs)

**CRITICAL RULE FOR ALL AI AGENTS:**
When you need to understand, use, or hook into a ProcessWire core class or module, you **MUST NEVER** guess or hallucinate the API methods.

- You **MUST** consult the local AI-optimized Markdown API documentation starting at `.agents/docs/index.md`.
- Navigate through the index, find the relevant class document (e.g. `.agents/docs/core/Page.md`), and use your file reading tools to read its methods, parameters, and hookable (🪝) events before writing any code.
