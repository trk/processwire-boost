<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;

final class Copilot extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'copilot';
    }

    public function displayName(): string
    {
        return 'GitHub Copilot';
    }

    public function mcpConfigPath(): ?string
    {
        return '.vscode/mcp.json';
    }

    public function systemDetectionPaths(): array
    {
        return [
            '/Applications/Visual Studio Code.app',
            '~/.config/Code',
        ];
    }

    public function systemDetectionBinaries(): array
    {
        return ['code'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.vscode', '.github/copilot-instructions.md'];
    }

    public function mcpConfigKey(): string
    {
        return 'servers';
    }

    public function guidelinesPath(): string
    {
        return '.github/copilot-instructions.md';
    }

    public function skillsPath(): string
    {
        return '.github/skills';
    }
}
