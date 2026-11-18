# Process Module Reference (Admin Controllers & Pipelines)

Every admin screen in ProcessWire (Pages, Setup, Modules, Access) is powered by a `Process` module (scaffold via `php vendor/bin/wire make:module Process<BaseName> --type=process`). Process modules execute upon admin URL access, dispatch URL segments to `___execute<Segment>()` methods, enforce RBAC permissions, and render admin views.

## Source-of-Truth Documentation
- **Core CLI:** `php index.php docs get-text Process`
- **Core Markdown:** `wire/core/Module/Process/API.md`, `wire/modules/Markup/MarkupAdminDataTable/API.md`, `wire/modules/Inputfield/InputfieldForm/API.md`
- **Admin UI Styling:** Activate the `pw-admin-ui` skill for `AdminThemeUikit` tokens and workspace layouts.

## Architecture Requirements
1. **Inheritance & Loading:** Extend `ProcessWire\Process`. Process modules are automatically `singular => true` and `autoload => false`.
2. **Module Info Properties (`getModuleInfo()`):**
   - `page`: Declaratively installs and removes the admin page (`name`, `parent`, `title`).
   - `permission`: Required permission name to access the module (never omit).
   - `permissions`: Map of module-owned permissions (`['perm-name' => 'Description']`) auto-installed/uninstalled by ProcessWire.
   - `nav`: Optional sub-navigation dropdown entries.
3. **URL Segment Routing:**
   - `public function ___execute(): string` — Default index route.
   - `public function ___execute<PascalSegment>(): string` — Segment handler (e.g., `/add/` -> `___executeAdd()`, `/export-csv/` -> `___executeExportCsv()`).
4. **Admin Chrome Controls:** `$this->headline('...')`, `$this->browserTitle('...')`, and `$this->breadcrumb('path/', 'Label')`.

## Implementation Pattern (PHP 8.3+)

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

		$sanitizer = $this->wire()->sanitizer;
		foreach ($reports as $report) {
			$id = (int) $report['id'];
			$name = $sanitizer->entities($report['name']);
			$date = $sanitizer->entities($report['date']);
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

		$input = $this->wire()->input;
		if ($input->post('submit_save')) {
			// InputfieldForm::processInput() automatically validates $session->CSRF
			$form->processInput($input->post);
			if (!$form->getErrors()) {
				$name = $this->wire()->sanitizer->text((string) $field->val());
				$this->wire()->session->message(sprintf($this->_('Created report: %s'), $name));
				$this->wire()->session->redirect('../');
			}
		}

		return $form->render();
	}
}
```

## Anti-Patterns to Avoid
- ❌ **Do not** manually create admin pages inside `init()`; declare `'page' => [...]` in `getModuleInfo()` so ProcessWire handles install/uninstall cleanly.
- ❌ **Do not** omit `'permission'` in `getModuleInfo()`, and validate `$this->wire()->session->CSRF->validate()` explicitly on custom non-`InputfieldForm` POST/DELETE endpoints.
- ❌ **Do not** hardcode `/processwire/` URLs — use relative admin URLs (`./`, `../`) or `$this->wire()->config->urls->admin`.
