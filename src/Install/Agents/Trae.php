<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

final class Trae extends Agent
{
    public function name(): string
    {
        return 'trae';
    }
    public function displayName(): string
    {
        return 'Trae';
    }
    public function mcpConfigPath(): ?string
    {
        return '.trae/mcp.json';
    }
}

