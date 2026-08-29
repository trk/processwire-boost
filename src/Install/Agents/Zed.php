<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;

final class Zed extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'zed';
    }

    public function displayName(): string
    {
        return 'Zed';
    }

    public function mcpConfigPath(): ?string
    {
        return '.zed/settings.json';
    }

    public function mcpConfigKey(): string
    {
        return 'context_servers';
    }

    public function systemDetectionPaths(): array
    {
        return ['/Applications/Zed.app'];
    }

    public function systemDetectionBinaries(): array
    {
        return ['zed', 'zeditor'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.zed', '.zed/settings.json'];
    }
}
