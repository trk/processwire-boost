<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;

final class Factory extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'factory';
    }

    public function displayName(): string
    {
        return 'Factory Droid';
    }

    public function mcpConfigPath(): ?string
    {
        return '.factory/mcp.json';
    }

    public function skillsPath(): string
    {
        return '.factory/skills';
    }

    public function systemDetectionPaths(): array
    {
        return ['~/.factory'];
    }

    public function systemDetectionBinaries(): array
    {
        return ['droid'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.factory', '.factory/mcp.json'];
    }
}
