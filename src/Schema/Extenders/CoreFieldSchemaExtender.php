<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Schema\Extenders;

use ProcessWire\Field;
use Totoglu\Console\Boost\Schema\FieldSchemaExtender;

/**
 * Enriches built-in ProcessWire core fieldtypes (File/Image, Page Reference, Options)
 * with formatted PHP return types, cardinality, target templates, and selectable options.
 */
final class CoreFieldSchemaExtender implements FieldSchemaExtender
{
	public function supports(Field $field): bool
	{
		$type = $field->type;
		if (!is_object($type) || !method_exists($type, 'className')) {
			return false;
		}

		$class = (string) $type->className();

		return $this->isImageFieldtype($type, $class)
			|| $this->isFileFieldtype($type, $class)
			|| $this->isPageFieldtype($type, $class)
			|| $this->isOptionsFieldtype($type, $class);
	}

	/**
	 * @param array{id:int,type:string,label:string} $base
	 * @return array<string,mixed>
	 */
	public function extend(Field $field, array $base): array
	{
		$type = $field->type;
		if (!is_object($type) || !method_exists($type, 'className')) {
			return [];
		}

		$class = (string) $type->className();

		if ($this->isImageFieldtype($type, $class)) {
			return $this->describeFileOrImageField($field, true);
		}

		if ($this->isFileFieldtype($type, $class)) {
			return $this->describeFileOrImageField($field, false);
		}

		if ($this->isPageFieldtype($type, $class)) {
			return $this->describePageField($field);
		}

		if ($this->isOptionsFieldtype($type, $class)) {
			return $this->describeOptionsField($field, $type);
		}

		return [];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describeFileOrImageField(Field $field, bool $isImage): array
	{
		$maxFiles = (int) ($field->get('maxFiles') ?? 0);
		$rawFormat = (int) ($field->get('outputFormat') ?? 0);
		$formatName = match ($rawFormat) {
			1 => 'array',
			2 => 'single',
			3 => 'string',
			default => 'auto',
		};

		$singleClass = $isImage ? 'Pageimage' : 'Pagefile';
		$multiClass = $isImage ? 'Pageimages' : 'Pagefiles';

		$valueType = match ($formatName) {
			'string' => 'string',
			'array' => $multiClass,
			'single' => "{$singleClass}|null",
			default => ($maxFiles === 1 ? "{$singleClass}|null" : $multiClass),
		};

		$meta = [
			'maxFiles' => $maxFiles,
			'outputFormat' => $formatName,
			'valueType' => $valueType,
		];

		$extensions = trim((string) ($field->get('extensions') ?? ''));
		if ($extensions !== '') {
			$meta['extensions'] = $extensions;
		}

		return $meta;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describePageField(Field $field): array
	{
		$deref = (int) ($field->get('derefAsPage') ?? 0);
		$valueType = match ($deref) {
			1 => 'Page|false',
			2 => 'Page|NullPage',
			default => 'PageArray',
		};

		$meta = [
			'derefAsPage' => $deref,
			'valueType' => $valueType,
		];

		$wire = $field->wire();
		if (!$wire) {
			return $meta;
		}

		$templateIds = [];
		$primaryTplId = (int) ($field->get('template_id') ?? 0);
		if ($primaryTplId > 0) {
			$templateIds[] = $primaryTplId;
		}
		$extraTplIds = $field->get('template_ids');
		if (is_array($extraTplIds)) {
			foreach ($extraTplIds as $tid) {
				$tidInt = (int) $tid;
				if ($tidInt > 0) {
					$templateIds[] = $tidInt;
				}
			}
		}

		$templateIds = array_values(array_unique($templateIds));
		if ($templateIds !== []) {
			$templates = $wire->templates;
			if ($templates) {
				$tplNames = [];
				foreach ($templateIds as $tid) {
					$tpl = $templates->get($tid);
					if ($tpl && $tpl->id && $tpl->name !== '') {
						$tplNames[] = (string) $tpl->name;
					}
				}
				if ($tplNames !== []) {
					$meta['targetTemplates'] = $tplNames;
				}
			}
		}

		$parentId = (int) ($field->get('parent_id') ?? 0);
		if ($parentId > 0) {
			$pages = $wire->pages;
			if ($pages) {
				$parentPage = $pages->get($parentId);
				if ($parentPage && $parentPage->id && $parentPage->path !== '') {
					$parentPath = (string) $parentPage->path;
					$adminRootId = (int) ($wire->config->adminRootPageID ?? 2);
					$adminRoot = $adminRootId > 0 ? $pages->get($adminRootId) : null;
					$adminPath = ($adminRoot && $adminRoot->id) ? (string) $adminRoot->path : '';
					if ($adminPath !== '' && $adminPath !== '/' && str_starts_with($parentPath, $adminPath)) {
						$parentPath = '{admin}/' . ltrim(substr($parentPath, strlen($adminPath)), '/');
					}
					$meta['parentPath'] = $parentPath;
				}
			}
		}

		return $meta;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describeOptionsField(Field $field, object $type): array
	{
		$meta = [
			'valueType' => 'SelectableOptionArray',
		];

		if (!method_exists($type, 'getOptions')) {
			return $meta;
		}

		try {
			$options = $type->getOptions($field);
			if (is_iterable($options)) {
				$items = [];
				$count = 0;
				foreach ($options as $opt) {
					if (!is_object($opt)) {
						continue;
					}
					$items[] = [
						'id' => (int) ($opt->id ?? 0),
						'value' => (string) ($opt->value ?? ''),
						'title' => (string) ($opt->title ?? ''),
					];
					$count++;
					if ($count >= 50) {
						break;
					}
				}
				if ($items !== []) {
					$meta['options'] = $items;
				}
			}
		} catch (\Throwable) {
			// Ignore option resolution errors in unbootstrapped test mocks
		}

		return $meta;
	}

	private function isImageFieldtype(object $type, string $class): bool
	{
		return $class === 'FieldtypeImage'
			|| str_ends_with($class, '\\FieldtypeImage')
			|| is_a($type, 'ProcessWire\\FieldtypeImage');
	}

	private function isFileFieldtype(object $type, string $class): bool
	{
		return $class === 'FieldtypeFile'
			|| str_ends_with($class, '\\FieldtypeFile')
			|| is_a($type, 'ProcessWire\\FieldtypeFile');
	}

	private function isPageFieldtype(object $type, string $class): bool
	{
		return $class === 'FieldtypePage'
			|| str_ends_with($class, '\\FieldtypePage')
			|| is_a($type, 'ProcessWire\\FieldtypePage');
	}

	private function isOptionsFieldtype(object $type, string $class): bool
	{
		return $class === 'FieldtypeOptions'
			|| str_ends_with($class, '\\FieldtypeOptions')
			|| is_a($type, 'ProcessWire\\FieldtypeOptions');
	}
}
