---
name: pw-module-textformatter
description: Use when building Textformatter modules to securely and dynamically format string-based fields immediately prior to output rendering in ProcessWire.
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# Textformatter Modulator Architecture (wire-module-textformatter)

The ProcessWire `Textformatter` module type exclusively intercepts raw data pulled natively from a Field (commonly generalized string-based types like `FieldtypeTextarea`) securely re-formatting it dynamically exactly preceding output rendering. It does not manipulate the actual database storage block; instead, it formats the active memory instance immediately before displaying the result on the frontend or backend admin area.

## Pre-Computation / Anti-Rationalization Check

Before committing `Textformatter` structures, critically evaluate:

- **Inheritance Base:** Does the class explicitly extend `ProcessWire\Textformatter`? If not, the system will not recognize it as a valid formatter selectable within field configuration interfaces.
- **Reference Variable Mutation:** Did you construct the primary formatting method signature correctly passing `$value` definitively by reference (`&$value`)? Textformatters manipulate variables sequentially; they do not explicitly return transformed strings back to the pipeline.
- **Performance Consideration:** These formatters trigger extensively, sometimes processing hundreds of array nodes simultaneously iteratively mapping nested output. Are your regex blocks optimized? Have you deployed caching if executing network-bound evaluations?

## Execution Phases

### Phase 1: Foundational Formatter Construction

A Textformatter mandates overriding the base system architecture manipulating runtime output securely via `formatValue`. 

```php
<?php 

declare(strict_types=1);

namespace ProcessWire;

class TextformatterHighlightTag extends Textformatter 
{
    public static function getModuleInfo(): array 
    {
        return [
            'title' => 'Textformatter: Highlight Tag Processor',
            'version' => 100,
            'summary' => 'Dynamically highlights specific keywords wrapped locally in explicit `[[tag]]` declarations immediately prior to HTML rendering.',
            'requires' => [
                'ProcessWire>=3.0.0',
                'PHP>=8.4.0'
            ]
        ];
    }

    /**
     * Primary parsing method mandated universally across all formatters.
     * 
     * @param Page $page Page entity harboring the functional field explicitly being parsed.
     * @param Field $field Field mapping currently evaluating formatting directives.
     * @param string|mixed $value Current active string. Must be targeted BY REFERENCE (&$value).
     */
    public function formatValue(Page $page, Field $field, &$value): void 
    {
        // Validation check handling array structures or native empty returns immediately
        if (!is_string($value) || empty($value)) {
            return;
        }

        // Perform explicit substitutions natively
        // Converting instances of [[keyword]] resolving physically mapping toward <mark>keyword</mark>
        $value = preg_replace('/\[\[(.*?)\]\]/s', '<mark class="highlighted">\\1</mark>', $value);
        
        // Return null internally; the process purely mutates the reference argument natively.
    }
}
```

### Phase 2: Dual Context Handling (Single Strings vs Active Page Integrations)

ProcessWire frequently utilizes Textformatters outside field contexts natively rendering raw internal string manipulations via the `$sanitizer` pipeline. Implementing robust fall-backs ensuring non-page variables parse correctly validates structural consistency. 

```php
    /**
     * Optional utility expanding pipeline formatting resolving generic string arrays bypassing strict Page binding.
     *
     * @param string|mixed $value The referenced string modified natively.
     */
    public function formatString(&$value): void 
    {
        if (!is_string($value) || empty($value)) {
            return;
        }
        
        // Shared business logic mapping identical replacements mapped previously
        $value = preg_replace('/\[\[(.*?)\]\]/s', '<mark class="highlighted">\\1</mark>', $value);
    }
```

### Phase 3: Configurable Textformatters

You can implement configurable textformatters allowing site administrators specific choices (i.e. modifying regex rules dynamically via UI contexts).

```php
namespace ProcessWire;

class TextformatterHighlightTag extends Textformatter implements ConfigurableModule 
{
    // Establishing native PHP 8.4 typed structural declarations matching form settings automatically
    public string $cssClass = 'highlighted';
    
    // Build admin elements deploying standard input arrays
    public static function getModuleConfigInputfields(array $data): InputfieldWrapper 
    {
        $wrapper = new InputfieldWrapper();
        
        /** @var InputfieldText $f */
        $f = wire()->modules->get('InputfieldText');
        $f->attr('name', 'cssClass');
        $f->label = 'Default CSS Highlight Class';
        $f->attr('value', $data['cssClass'] ?? 'highlighted');
        $wrapper->add($f);
        
        return $wrapper;
    }
    
    public function formatValue(Page $page, Field $field, &$value): void 
    {
        if (!is_string($value) || empty($value)) {
            return;
        }
        
        $class = $this->wire()->sanitizer->entities($this->cssClass);
        $value = preg_replace('/\[\[(.*?)\]\]/s', "<mark class=\"{$class}\">\\1</mark>", $value);
    }
}
```

## Essential Tools & Ecosystem
- ProcessWire `Textformatter` basic class mappings.
- Relying explicitly upon native string replacements, DOMDocument DOM rendering evaluations, or the internal library `html_entity_decode` mechanics depending on parsing complexity logic boundaries.

## Copy-Paste Prompts

(Pass these direct prompts to the agent to initiate workflows instantly)

**[Construct Textformatter Module - Markdown Processor Extension]**
> "Build a class `TextformatterAlertBlocks` targeting explicit Textformatter modular structures. Reconfigure the `formatValue` mapping parsing elements bounded securely within native markdown quotes traversing `> [!ALERT]` formatting into physical UIkit explicitly mapping HTML class properties matching `<div class='uk-alert uk-alert-danger'>`. Ensure execution modifies variable assignments safely universally strictly relying upon reference declarations `&$value`."

## Context Awareness (ProcessWire API Docs)

**CRITICAL RULE FOR ALL AI AGENTS:**
When you need to understand, use, or hook into a ProcessWire core class or module, you **MUST NEVER** guess or hallucinate the API methods.
- You **MUST** consult the local AI-optimized Markdown API documentation starting at `.llms/docs/index.md`.
- Navigate through the index, find the relevant class document (e.g. `.llms/docs/core/Page.md`), and use your file reading tools to read its methods, parameters, and hookable (🪝) events before writing any code.
