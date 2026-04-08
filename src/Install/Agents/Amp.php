<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

final class Amp extends Agent
{
    public function name(): string
    {
        return 'amp';
    }

    public function displayName(): string
    {
        return 'Amp';
    }

    public function mcpConfigPath(): ?string
    {
        return '.amp/settings.json';
    }

    public function mcpConfigKey(): string
    {
        return 'amp.mcpServers';
    }

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }
}
