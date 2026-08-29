<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Install\Enums\McpPathStrategy;

final class Gemini extends Agent implements SupportsGuidelines, SupportsMcp
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

    public function systemDetectionPaths(): array
    {
        return ['~/.gemini'];
    }

    public function systemDetectionBinaries(): array
    {
        return ['gemini'];
    }

    public function projectDetectionPaths(): array
    {
        return ['GEMINI.md', '.gemini', '.gemini/settings.json'];
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
