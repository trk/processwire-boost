<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

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
}
