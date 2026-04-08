<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

use Totoglu\ProcessWire\Boost\Install\Enums\McpPathStrategy;

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

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }

    /**
     * Junie cannot resolve relative paths — requires absolute filesystem paths.
     */
    public function mcpPathStrategy(): McpPathStrategy
    {
        return McpPathStrategy::Absolute;
    }

    public function skillsPath(): string
    {
        return '.junie/skills';
    }
}
