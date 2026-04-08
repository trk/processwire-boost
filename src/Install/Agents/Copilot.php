<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

final class Copilot extends Agent
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

    public function mcpConfigKey(): string
    {
        return 'servers';
    }

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }

    public function skillsPath(): string
    {
        return '.github/skills';
    }
}
