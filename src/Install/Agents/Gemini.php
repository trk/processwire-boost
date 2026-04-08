<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

use Totoglu\ProcessWire\Boost\Install\Enums\McpPathStrategy;

final class Gemini extends Agent
{
    public function name(): string
    {
        return 'gemini';
    }

    public function displayName(): string
    {
        return 'Gemini CLI';
    }

    public function mcpConfigPath(): ?string
    {
        return '.gemini/settings.json';
    }

    public function guidelinesPath(): string
    {
        return 'GEMINI.md';
    }

    /**
     * Gemini CLI / Antigravity requires absolute paths for MCP server resolution.
     */
    public function mcpPathStrategy(): McpPathStrategy
    {
        return McpPathStrategy::Absolute;
    }
}
