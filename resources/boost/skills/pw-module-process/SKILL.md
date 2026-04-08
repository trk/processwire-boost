---
name: pw-module-process
description: Use when constructing custom admin page pipelines, dashboards, or routing structures within ProcessWire implementing isolated Process models and RBAC.
metadata:
  triggers:
    - processwire
    - process module
    - admin routing
    - rbac permission
    - backend ui
---

# Personalized Admin Views & Routing Structures (wire-process-admin)

Within the internal ProcessWire administrative ecosystem, Controller methodologies utilized for accessing localized systems, generating functional dashboards, or navigating private toolkits definitively require explicit **Process** modules. This skill outlines precisely how to engage and construct modular endpoints safely deployed directly into backend operational segments.

## Pre-Computation / Anti-Rationalization Check

Ensure this internal validation cascade resolves cleanly prior to active class modifications:

- **Is the active Module logically extending the `Process` system class?** Any deviation means the framework cannot appropriately hook onto localized routing functions. (`class ProcessMyTool extends Process`)
- **Have native Permission matrices been engaged securely?** Verify an explicit string has been configured into the `permission` array key located within `getModuleInfo`. This physically restricts rendering procedures inherently guarding external endpoint exposure.
- **Is URL segment capability mapped physically?** Segment structures traverse linearly down internal executions formatted actively via camelCase configurations (`executeYourSegment()`). Are routing sequences fully vetted?

## Execution Phases

### Phase 1: Controller Construction and Context Bindings (RBAC)

Construct the module clearly noting specialized admin properties embedded systematically across internal maps.

```php
<?php 

declare(strict_types=1);

namespace ProcessWire;

class ProcessReportManager extends Process 
{
    public static function getModuleInfo(): array 
    {
        return [
            'title' => 'Report Manager',
            'summary' => 'Dynamically manage customized business reports explicitly bound to backend interactions.',
            'version' => 101,
            'icon' => 'pie-chart',
            // RBAC - Configures automated explicit permission scopes upon successful installation
            'permission' => 'report-manager', 
            'permissions' => [
                'report-manager' => 'Access and resolve queries actively within the report dashboard.'
            ],
            // Hard bind to dedicated URL hierarchy mappings automatically upon setup
            'page' => [
                'name' => 'reports',
                'parent' => 'setup', 
                'title' => 'System Reports'
            ],
            // Dynamically generate navigation elements localized uniquely to the module interface targeting immediate tasks
            'nav' => [
                [
                    'url' => 'add/',
                    'label' => 'Register Output',
                    'icon' => 'plus'
                ]
            ]
        ];
    }
    
    // BASE ACTION (Triggered inherently directly across initial load occurrences)
    public function ___execute(): string 
    {
        $this->headline('System Reports Pipeline'); // Mutating core module header designations
        
        $table = $this->wire()->modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false); 
        $table->headerRow(['Classification Profile', 'Registered Deployment', 'Action Vectors']);
        
        // Abstracting generalized response blocks conceptually modeling generalized system logic:
        $reports = [
            ['id' => 1, 'name' => 'Monthly Volume Map', 'date' => '2026-04-01'],
            ['id' => 2, 'name' => 'Weekly Output Register', 'date' => '2026-04-06']
        ];

        foreach ($reports as $row) {
            $table->row([
                $row['name'],
                $row['date'],
                "<a href='./edit/?id={$row['id']}' class='uk-button uk-button-small uk-button-default'><i class='fa fa-edit'></i> Render Operations</a>"
            ]);
        }

        $out = "<div class='uk-container uk-margin-top'>";
        $out .= "<p>Initiating localized report sequence rendering context.</p>";
        $out .= "<a href='./add/' class='uk-button uk-button-primary uk-margin-bottom'>Register Output Node</a>";
        $out .= $table->render();
        $out .= "</div>";
        
        return $out;
    }

    // Specific segments bound uniquely tracking explicitly defined action operations (Triggered via trailing /add/)
    public function ___executeAdd(): string 
    {
        $this->headline('Construct Specialized Output Node');
        $this->breadcrumb('../', 'Reports Pipeline Matrix'); // Implementing dynamic trailing linkages reliably
        
        // Leveraging robust form APIs definitively ensures security protocols resolve explicitly
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './';
        
        $field = $this->wire()->modules->get('InputfieldText');
        $field->name = 'report_name';
        $field->label = 'Assignment Classification';
        $form->add($field);
        
        $form->add($this->wire()->modules->get('InputfieldSubmit'));
        
        return $form->render();
    }
}
```

## Essential Tools & Ecosystem

- Focus extensively upon integrating within `AdminThemeUikit` generalized parameters (implementing raw uk-* CSS classes directly onto strings inside execution returns resolves fully native styling dynamically universally across back-ends environments).
- Incorporating internal modular component logic requires scaling natively accessing the local `Totoglu\Htmx` framework parameters explicitly utilizing HTMX implementations detailed natively within the explicitly configured `wire-htmx-integration` operations if dynamic swaps are mandated without standard localized redirects.

## Copy-Paste Prompts

(Pass these direct prompts to the agent to initiate workflows instantly)

**[Scaffolding - Specialized Segment Access Controllers]**
> "Deploy a core structure targeting a new Process class segment called `ProcessImportProducts`. Bind execution parameters securely verifying exact matched user permission layers mapped internally alongside `product-importer`. Assign dynamic integration inside `getModuleInfo` binding physical segments securely operating underneath `setup` labeled cleanly as `Products Import`. Deliver an initial string rendered actively through `___execute()` providing brief configuration introductions properly."

**[Operation Controls - Deploying Specific Matrix Form Handlers]**
> "Bind a defined segment actively configured mapping `___executeUpload()` functions routing linearly targeting the `/upload/` sub-parameter explicitly connected internal tracking toward the defined `ProcessImportProducts` structure. Build localized rendering contexts leveraging explicit form configurations initiating native `InputfieldFile` mapping controls deployed across InputfieldForm matrices. Include explicit controller logic parsing uploaded elements accurately landing fully inside safe isolated .tmp target domains effectively."

## Context Awareness (ProcessWire API Docs)

**CRITICAL RULE FOR ALL AI AGENTS:**
When you need to understand, use, or hook into a ProcessWire core class or module, you **MUST NEVER** guess or hallucinate the API methods.
- You **MUST** consult the local AI-optimized Markdown API documentation starting at `.llms/docs/index.md`.
- Navigate through the index, find the relevant class document (e.g. `.llms/docs/core/Page.md`), and use your file reading tools to read its methods, parameters, and hookable (🪝) events before writing any code.
