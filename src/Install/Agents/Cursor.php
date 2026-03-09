<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

final class Cursor extends Agent
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
}

