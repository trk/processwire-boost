<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

final class OpenCode extends Agent
{
    public function name(): string
    {
        return 'opencode';
    }
    public function displayName(): string
    {
        return 'OpenCode';
    }
    public function mcpConfigPath(): ?string
    {
        return 'opencode.json';
    }
    public function mcpConfigKey(): string
    {
        return 'mcp';
    }
    public function defaultMcpConfig(): array
    {
        return ['$schema' => 'https://opencode.ai/config.json'];
    }
    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return [
            'type' => 'local',
            'enabled' => true,
            'command' => array_merge([$command], $args),
            'environment' => $env,
        ];
    }

    public function exportSkill(string $skillName, string $skillPath, string $targetDir): string
    {
        $skillDir = $targetDir . '/' . $skillName;
        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }
        $targetPath = $skillDir . '/SKILL.md';
        copy($skillPath, $targetPath);
        return $targetPath;
    }
}

