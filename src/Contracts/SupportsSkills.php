<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Contracts;

interface SupportsSkills
{
    public function skillsPath(): string;

    public function exportSkill(string $skillName, string $skillPath, string $targetDir): string;
}
