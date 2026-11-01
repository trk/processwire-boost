<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Schema\Extenders;

use ProcessWire\Field;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Schema\FieldSchemaExtender;

final class RepeaterFieldSchemaExtender implements FieldSchemaExtender
{
	public function supports(Field $field): bool
	{
		$type = $field->type;
		if (!$type) {
			return false;
		}

		$class = (string) $type->className();

		// Match exact repeater/fieldset-page fieldtypes only. Do not use
		// inheritance checks here: repeater-derived fieldtypes like
		// RepeaterMatrix need their own schema extenders.
		return $class === 'FieldtypeRepeater'
			|| str_ends_with($class, '\\FieldtypeRepeater')
			|| $class === 'FieldtypeFieldsetPage'
			|| str_ends_with($class, '\\FieldtypeFieldsetPage');
	}

	/**
	 * @param array{id:int,type:string,label:string} $base
	 * @return array<string,mixed>
	 */
	public function extend(Field $field, array $base): array
	{
		return ['fields' => $this->resolveSchemasFromTemplate($field)];
	}

	/**
	 * @return array<string,array{id:int,type:string,label:string}>
	 */
	private function resolveSchemasFromTemplate(Field $field): array
	{
		$templateId = (int) $field->get('template_id');
		if ($templateId < 1) {
			return [];
		}

		$wire = $field->wire();
		if (!$wire) {
			return [];
		}

		$templates = $wire->templates;
		$template = $templates ? $templates->get($templateId) : null;

		if (!$template || !$template->id || !$template->fieldgroup) {
			return [];
		}

		$schemas = [];
		foreach ($template->fieldgroup as $subfield) {
			if (!$subfield || !$subfield->id || $subfield->name === '') {
				continue;
			}

			$schemas[$subfield->name] = BoostManager::buildFieldBaseSchema($subfield);
		}

		return $schemas;
	}
}
