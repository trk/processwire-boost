<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;

final class Codex extends Agent implements SupportsGuidelines, SupportsMcp
{
    public function name(): string
    {
        return 'codex';
    }

    public function displayName(): string
    {
        return 'Codex';
    }

    public function mcpConfigPath(): ?string
    {
        return '.codex/config.toml';
    }

    public function systemDetectionPaths(): array
    {
        return ['~/.codex'];
    }

    public function systemDetectionBinaries(): array
    {
        return ['codex'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.codex', '.codex/config.toml'];
    }

    public function mcpConfigKey(): string
    {
        return 'mcp_servers';
    }

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }
}
