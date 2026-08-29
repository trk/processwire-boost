<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;

final class Amp extends Agent implements SupportsGuidelines, SupportsMcp
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

    public function systemDetectionPaths(): array
    {
        return ['/Applications/Amp.app', '~/.amp', '~/.config/amp'];
    }

    public function systemDetectionBinaries(): array
    {
        return ['amp'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.amp', '.amp/settings.json'];
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
