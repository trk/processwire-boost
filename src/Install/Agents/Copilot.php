<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

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
}

