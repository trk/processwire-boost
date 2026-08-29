<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Contracts;

interface SupportsGuidelines
{
    public function guidelinesPath(): string;

    public function frontmatter(): bool;

    public function transformGuidelines(string $markdown): string;
}
