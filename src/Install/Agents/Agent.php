<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Install\Enums\McpPathStrategy;
use Totoglu\Console\Boost\Install\Mcp\FileWriter;
use Totoglu\Console\Boost\Install\Mcp\TomlFileWriter;

abstract class Agent
{
    abstract public function name(): string;
    abstract public function displayName(): string;
    abstract public function mcpConfigPath(): ?string;
    abstract public function guidelinesPath(): string;

    /**
     * The MCP path strategy for this agent.
     *
     * - Relative:        `vendor/bin/wire` (default, most agents)
     * - Absolute:        `/full/path/vendor/bin/wire` (Junie, Gemini)
     * - WorkspaceFolder: `${workspaceFolder}/vendor/bin/wire` (Trae)
     */
    public function mcpPathStrategy(): McpPathStrategy
    {
        return McpPathStrategy::Relative;
    }

    /**
     * @deprecated Use mcpPathStrategy() instead.
     */
    public function useAbsolutePathForMcp(): bool
    {
        return $this->mcpPathStrategy() === McpPathStrategy::Absolute;
    }

    /**
     * Get the PHP binary path based on the agent's path strategy.
     */
    public function getPhpPath(): string
    {
        return match ($this->mcpPathStrategy()) {
            McpPathStrategy::Absolute => PHP_BINARY,
            McpPathStrategy::WorkspaceFolder, McpPathStrategy::Relative => 'php',
        };
    }

    /**
     * Get the wire CLI path based on the agent's path strategy.
     */
    public function getWirePath(string $projectRoot): string
    {
        return match ($this->mcpPathStrategy()) {
            McpPathStrategy::Absolute => $projectRoot . '/vendor/bin/wire',
            McpPathStrategy::WorkspaceFolder => '${workspaceFolder}/vendor/bin/wire',
            McpPathStrategy::Relative => 'vendor/bin/wire',
        };
    }

    public function skillsPath(): string
    {
        return '.agents/skills';
    }

    public function mcpConfigKey(): string
    {
        return str_ends_with((string) $this->mcpConfigPath(), '.toml') ? 'mcp_servers' : 'mcpServers';
    }

    public function defaultMcpConfig(): array
    {
        return [$this->mcpConfigKey() => []];
    }

    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return ['command' => $command, 'args' => $args, 'env' => $env];
    }

    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        $path = $this->mcpConfigPath();
        if (!$path) {
            return false;
        }

        if (str_ends_with($path, '.toml')) {
            $w = new TomlFileWriter($path, $this->defaultMcpConfig());
            return $w->configKey($this->mcpConfigKey())->addServerConfig($key, $this->mcpServerConfig($command, $args, $env))->save();
        }

        $w = new FileWriter($path, $this->defaultMcpConfig());
        return $w->configKey($this->mcpConfigKey())->addServerConfig($key, $this->mcpServerConfig($command, $args, $env))->save();
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
