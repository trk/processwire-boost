<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Skills\Remote;

final class AuditResult
{
    /**
     * @param list<string> $signals
     */
    public function __construct(
        public string $partner,
        public Risk $risk,
        public array $signals = [],
    ) {
    }
}
