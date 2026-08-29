<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;

final class Kiro extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'kiro';
    }

    public function displayName(): string
    {
        return 'Kiro';
    }

    public function mcpConfigPath(): ?string
    {
        return '.kiro/settings/mcp.json';
    }

    public function skillsPath(): string
    {
        return '.kiro/skills';
    }

    public function systemDetectionPaths(): array
    {
        return ['/Applications/Kiro.app', '/opt/kiro', '~/.local/bin/kiro'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.kiro', '.kiro/settings/mcp.json'];
    }
}
