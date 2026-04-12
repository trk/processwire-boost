---
name: pw-module-markup
description: "Use when creating independent frontend rendering systems explicitly extending the ProcessWire Markup module ecosystem."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# Markup Generator Operations (wire-module-markup)

**Markup Modules** within ProcessWire do not alter core mechanics, run inherently in the background, or specifically format string arrays like Textformatters. Instead, they act as **HTML generation engines and widget generators** deployed freely universally across frontend templates locally by developer invocations (`$modules->get('MarkupMyWidget')->render()`).

These modules strictly separate complex HTML nesting structures from the local template `php` files, effectively maintaining structural cleanliness universally matching independent Controller/UI patterns.

## Pre-Computation / Anti-Rationalization Check

Verify module logic universally adhering to standard markup architecture limits:

- **Class Purpose Structure:** Are you attempting to place form submission validation logic into this file? Halt. Markup modules securely process explicit DOM outputs generating static or interactive HTML components visually. Form logic should utilize Process models or discrete Components natively.
- **Dynamic Render Methods:** Have you provided an explicit public method (most universally labeled `render()`) granting other developers simplified usage access deploying localized variables securely?
- **CSS / JS Injection Patterns:** If the Markup generates UI mapping elements structurally requiring JavaScript files, process explicit injection techniques locally dispatching dependencies efficiently inside `wire('config')->scripts->add()`.

## Execution Phases

### Phase 1: Bootstrapping Markup Subsystems

Creating a robust interface mapping generating explicit standard HTML strings globally structured intuitively.

```php
<?php

declare(strict_types=1);

namespace ProcessWire;

class MarkupPricingTable extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Markup: Pricing Table Generator',
            'version' => 101,
            'summary' => 'Dynamically builds complex UIkit pricing matrix components pulling data directly via system page children arrays targeting service domains.',
            'requires' => [
                'ProcessWire>=3.0.0',
                'PHP>=8.4.0'
            ]
        ];
    }

    /**
     * Executes generation dynamically injecting mandatory CSS dependencies globally natively.
     */
    public function init(): void
    {
        // Optionally attach static resources explicitly verifying they deploy onto global configurations universally.
        // $this->wire()->config->styles->add($this->wire()->config->urls->MarkupPricingTable . 'markup-pricing.css');
    }

    /**
     * Public method universally triggered specifically when templates build display domains explicitly.
     *
     * @param PageArray $packages Explicit list mapping active Pricing Option pages.
     * @return string Compiled HTML UI rendering.
     */
    public function render(PageArray $packages): string
    {
        if (!$packages->count()) {
            return '';
        }

        $out = "<div class='uk-grid uk-child-width-1-3@m' uk-grid>";

        foreach ($packages as $pkg) {
            $title = $this->wire()->sanitizer->entities($pkg->title);
            $price = $this->wire()->sanitizer->float($pkg->get('price'));
            $features = $this->wire()->sanitizer->entities($pkg->get('summary'));

            $out .= "
            <div>
                <div class='uk-card uk-card-default uk-card-body uk-text-center'>
                    <h3 class='uk-card-title'>{$title}</h3>
                    <div class='uk-text-large uk-text-primary'>₺{$price}</div>
                    <p class='uk-text-meta'>{$features}</p>
                    <a href='{$pkg->url}' class='uk-button uk-button-secondary'>Select Plan</a>
                </div>
            </div>";
        }

        $out .= "</div>";
        return $out;
    }

    /**
     * Integrates caching mechanism directly relying on native MarkupCache configurations.
     */
    public function renderCached(PageArray $packages, int $minutes = 60): string
    {
        $cache = $this->wire()->modules->get('MarkupCache');
        // Produce explicit reliable cache keys mapping node configurations safely
        $cacheKey = "pricing_tables_" . $packages->count() . "_" . $packages->first()->id;

        if ($data = $cache->get($cacheKey, $minutes * 60)) {
            return $data; // Deliver compiled native cache directly
        }

        // Compilation fails evaluating inside cached nodes, rendering fully natively globally
        $data = $this->render($packages);

        $cache->save($data);
        return $data;
    }
}
```

## Essential Tools & Ecosystem

- Explicit reliance mapped strictly toward `ProcessWire\WireData`.
- Engaging `ProcessWire\MarkupCache` directly mapping large DOM evaluations robustly maintaining site metrics effectively.

## Copy-Paste Prompts

(Pass these direct prompts to the agent to initiate workflows instantly)

**[Build Markup Component Framework]**

> "Generate a standalone Markup module constructed strictly targeting explicit HTML generating models labeled safely as `MarkupFeatureSlider`. Outline a robust programmatic standard `render()` method mapped explicitly executing iteration sequences spanning native `PageArray` variables structuring individual HTML domains strictly mapped around explicit UIkit 3 `uk-slider` DOM properties properly deploying internal structural attributes automatically. Enforce strict variable sanitation consistently throughout string creations restricting XSS vectors explicitly."

## Context Awareness (ProcessWire API Docs)

**CRITICAL RULE FOR ALL AI AGENTS:**
When you need to understand, use, or hook into a ProcessWire core class or module, you **MUST NEVER** guess or hallucinate the API methods.

- You **MUST** consult the local AI-optimized Markdown API documentation starting at `.agents/docs/index.md`.
- Navigate through the index, find the relevant class document (e.g. `.agents/docs/core/Page.md`), and use your file reading tools to read its methods, parameters, and hookable (🪝) events before writing any code.
