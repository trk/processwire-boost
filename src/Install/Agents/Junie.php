<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

final class Junie extends Agent
{
    public function name(): string
    {
        return 'junie';
    }
    public function displayName(): string
    {
        return 'Junie';
    }
    public function mcpConfigPath(): ?string
    {
        return '.junie/mcp/mcp.json';
    }
}

