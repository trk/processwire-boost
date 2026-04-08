<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

final class Codex extends Agent
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

    public function mcpConfigKey(): string
    {
        return 'mcp_servers';
    }

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }
}
