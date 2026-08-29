<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Schema\Extenders;

use ProcessWire\Field;
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
        // Primary source: repeater template fieldgroup
        $schemas = $this->resolveSchemasFromTemplate($field);

        // Backward-compatible fallback: repeaterFields IDs
        if (empty($schemas)) {
            $schemas = $this->resolveSchemasFromRepeaterFieldIds($field);
        }

        // Last fallback: migration/config payload keys
        if (empty($schemas)) {
            $schemas = $this->resolveSchemasFromConfigFields($field);
        }

        return ['fields' => $schemas];
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

            $schemas[$subfield->name] = $this->toSchema($subfield);
        }

        return $schemas;
    }

    /**
     * @return array<string,array{id:int,type:string,label:string}>
     */
    private function resolveSchemasFromRepeaterFieldIds(Field $field): array
    {
        $raw = $field->get('repeaterFields');
        $ids = $this->normalizeIds($raw);

        if (empty($ids)) {
            return [];
        }

        $wire = $field->wire();
        if (!$wire) {
            return [];
        }

        $fieldsApi = $wire->fields;
        if (!$fieldsApi) {
            return [];
        }

        $schemas = [];

        foreach ($ids as $id) {
            $subfield = $fieldsApi->get($id);
            if (!$subfield || !$subfield->id || $subfield->name === '') {
                continue;
            }

            $schemas[$subfield->name] = $this->toSchema($subfield);
        }

        return $schemas;
    }

    /**
     * @return array<string,array{id:int,type:string,label:string}>
     */
    private function resolveSchemasFromConfigFields(Field $field): array
    {
        $raw = $field->get('fields');
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        $wire = $field->wire();
        $fieldsApi = $wire?->fields;

        $schemas = [];
        foreach ($raw as $name => $config) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $subfield = $fieldsApi?->get($name);
            if ($subfield && $subfield->id && $subfield->name !== '') {
                $schemas[$subfield->name] = $this->toSchema($subfield);
                continue;
            }

            // Config fallback: ProcessWire fields API not available or
            // field not yet installed; synthesize schema from payload.
            $fallback = $this->toFallbackSchema($name, is_array($config) ? $config : []);
            if ($fallback !== null) {
                $schemas[$fallback['label'] ? $name : $name] = $fallback;
            }
        }

        return $schemas;
    }

    /**
     * Synthesize a field schema when ProcessWire fields API is unavailable
     * (e.g. CLI bootstraps without full ProcessWire kernel).
     *
     * @param array<string,mixed> $config
     * @return array{id:int,type:string,label:string}|null
     */
    private function toFallbackSchema(string $name, array $config): ?array
    {
        if ($name === '') {
            return null;
        }

        $type = isset($config['type']) ? (string) $config['type'] : '';
        $label = isset($config['label']) ? (string) $config['label'] : $name;

        return [
            'id' => 0,
            'type' => $type,
            'label' => $label,
        ];
    }

    /**
     * Intentionally mirrors BoostManager's base field schema shape.
     *
     * @return array{id:int,type:string,label:string}
     */
    private function toSchema(Field $field): array
    {
        $type = $field->type;

        return [
            'id' => (int) $field->id,
            'type' => $type ? (string) $type->className() : '',
            'label' => (string) $field->label,
        ];
    }

    /**
     * @return int[]
     */
    private function normalizeIds(mixed $raw): array
    {
        if (is_int($raw) || is_string($raw)) {
            $raw = explode(',', (string) $raw);
        }

        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
