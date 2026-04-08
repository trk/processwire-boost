<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

final class ClaudeCode extends Agent
{
    public function name(): string
    {
        return 'claude_code';
    }

    public function displayName(): string
    {
        return 'Claude Code';
    }

    public function mcpConfigPath(): ?string
    {
        return '.mcp.json';
    }

    public function guidelinesPath(): string
    {
        return 'CLAUDE.md';
    }

    public function skillsPath(): string
    {
        return '.claude/skills';
    }
}
