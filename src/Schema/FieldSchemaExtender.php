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
