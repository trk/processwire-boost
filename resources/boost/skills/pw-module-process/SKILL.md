---
name: pw-module-process
description: "Use when constructing custom admin page pipelines, dashboards, or routing structures within ProcessWire implementing isolated Process models and RBAC."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Process Module Architecture

Every admin page in ProcessWire (Pages, Setup, Modules, Access, etc.) is powered by a `Process` module (scaffold via `php vendor/bin/wire make:module <ModuleName> --type=process`). Process modules act as backend controllers: they execute upon URL access, handle URL segments, enforce RBAC permissions, and render admin views.

## Documentation Reference (Source of Truth)

Before writing or modifying any Process module, consult the core documentation:
- **Core CLI:** `php index.php docs get-text Process`
- **Core Markdown:** `wire/core/Module/Process/API.md`
- **Related Core Docs:**
  - Data Tables: `wire/modules/Markup/MarkupAdminDataTable/API.md`
  - Form API: `wire/modules/Inputfield/InputfieldForm/API.md`
  - Admin UI Theme: See `pw-admin-ui` skill

## Architecture Requirements

1. **Inheritance:** Class must extend `ProcessWire\Process`.
2. **Singular & On-Demand:** Process modules are automatically `singular => true` and are **not** autoloaded.
3. **Module Info Properties:**
   - `page`: Configures the auto-created admin page (`name`, `parent`, `title`).
   - `permission`: Name of the permission required to access (defaults to class name if omitted).
   - `permissions`: Additional permissions to install alongside the module.
   - `nav`: Sub-navigation items for admin dropdown menus.
4. **Execution Methods:**
   - `public function ___execute(): string` — Default action when page is requested.
   - `public function ___execute[Segment](): string` — URL segment action (camelCase, e.g. `/add/` -> `executeAdd()`, `/export-csv/` -> `executeExportCsv()`).
5. **Admin Frame Controls:**
   - `$this->headline('Section Title')`
   - `$this->browserTitle('Browser Tab Title')`
   - `$this->breadcrumb('path/', 'Label')`

## Implementation Pattern (PHP 8.4+)

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
			'version' => 100,
			'summary' => 'Manage system and business reports from ProcessWire admin.',
			'icon' => 'pie-chart',
			'permission' => 'report-manager',
			'permissions' => [
				'report-manager' => 'Access the report manager dashboard',
			],
			'page' => [
				'name' => 'reports',
				'parent' => 'setup',
				'title' => 'System Reports',
			],
			'nav' => [
				[
					'url' => 'add/',
					'label' => 'New Report',
					'icon' => 'plus',
				],
			],
		];
	}

	/**
	 * Default action: List reports in a native MarkupAdminDataTable
	 */
	public function ___execute(): string
	{
		$this->headline($this->_('System Reports'));
		$this->browserTitle($this->_('Reports'));

		/** @var MarkupAdminDataTable $table */
		$table = $this->wire()->modules->get('MarkupAdminDataTable');
		$table->setEncodeEntities(false);
		$table->headerRow([
			$this->_('Report Name'),
			$this->_('Created Date'),
			$this->_('Actions'),
		]);

		$reports = [
			['id' => 1, 'name' => 'Monthly Volume', 'date' => '2026-04-01'],
			['id' => 2, 'name' => 'Weekly Output', 'date' => '2026-04-06'],
		];

		foreach ($reports as $report) {
			$id = (int) $report['id'];
			$name = $this->wire()->sanitizer->entities($report['name']);
			$date = $this->wire()->sanitizer->entities($report['date']);
			$editUrl = "./edit/?id={$id}";

			$table->row([
				$name,
				$date,
				"<a href='{$editUrl}' class='uk-button uk-button-small uk-button-default'><i class='fa fa-edit'></i> " . $this->_('Edit') . "</a>",
			]);
		}

		$out = "<div class='uk-margin-bottom'>";
		$out .= "<a href='./add/' class='uk-button uk-button-primary'><i class='fa fa-plus'></i> " . $this->_('Create Report') . "</a>";
		$out .= "</div>";
		$out .= $table->render();

		return $out;
	}

	/**
	 * URL Segment Action: /setup/reports/add/
	 */
	public function ___executeAdd(): string
	{
		$this->headline($this->_('Create Report'));
		$this->breadcrumb('../', $this->_('System Reports'));

		/** @var InputfieldForm $form */
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->action = './';
		$form->method = 'post';

		/** @var InputfieldText $field */
		$field = $this->wire()->modules->get('InputfieldText');
		$field->name = 'report_name';
		$field->label = $this->_('Report Name');
		$field->required = true;
		$form->add($field);

		/** @var InputfieldSubmit $submit */
		$submit = $this->wire()->modules->get('InputfieldSubmit');
		$submit->name = 'submit_save';
		$submit->value = $this->_('Save Report');
		$form->add($submit);

		if ($this->wire()->input->post('submit_save')) {
			$form->processInput($this->wire()->input->post);
			if (!$form->getErrors()) {
				$name = $this->wire()->sanitizer->text($field->val());
				$this->wire()->session->message(sprintf($this->_('Created report: %s'), $name));
				$this->wire()->session->redirect('../');
			}
		}

		return $form->render();
	}
}
```

## Anti-Patterns to Avoid

- ❌ **Do not** manually insert pages into the page tree in `init()`. Use `'page' => [...]` in `getModuleInfo()`; ProcessWire automatically installs and manages the admin page.
- ❌ **Do not** omit the `permission` definition. Admin modules must always enforce RBAC.
- ❌ **Do not** return raw HTML with hardcoded themes or colors. Use `AdminThemeUikit` CSS classes (`uk-button`, `uk-table`) and CSS variables (`var(--pw-*)`). See `pw-admin-ui`.
- ❌ **Do not** mix HTMX partials with full admin executions. For partial responses without admin chrome, use explicit exit headers (see `pw-admin-ui`).
