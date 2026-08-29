<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;

final class Cursor extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'cursor';
    }

    public function displayName(): string
    {
        return 'Cursor';
    }

    public function mcpConfigPath(): ?string
    {
        return '.cursor/mcp.json';
    }

    public function systemDetectionPaths(): array
    {
        return ['/Applications/Cursor.app', '/opt/cursor', '~/.local/bin/cursor'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.cursor', '.cursorrules'];
    }

    public function guidelinesPath(): string
    {
        return '.cursorrules';
    }

    public function skillsPath(): string
    {
        return '.cursor/skills';
    }
}
