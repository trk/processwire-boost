<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

use Totoglu\ProcessWire\Boost\Install\Mcp\FileWriter;
use Totoglu\ProcessWire\Boost\Install\Mcp\TomlFileWriter;

abstract class Agent
{
    abstract public function name(): string;
    abstract public function displayName(): string;
    abstract public function mcpConfigPath(): ?string;
    public function mcpConfigKey(): string
    {
        return str_ends_with((string) $this->mcpConfigPath(), '.toml') ? 'mcp_servers' : 'mcpServers';
    }
    public function defaultMcpConfig(): array
    {
        $key = $this->mcpConfigKey();
        return [$key => []];
    }
    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return ['command' => $command, 'args' => $args, 'env' => $env];
    }
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        $path = $this->mcpConfigPath();
        if (!$path) return false;
        if (str_ends_with($path, '.toml')) {
            $w = new TomlFileWriter($path, $this->defaultMcpConfig());
            return $w->configKey($this->mcpConfigKey())->addServerConfig($key, $this->mcpServerConfig($command, $args, $env))->save();
        }
        $w = new FileWriter($path, $this->defaultMcpConfig());
        return $w->configKey($this->mcpConfigKey())->addServerConfig($key, $this->mcpServerConfig($command, $args, $env))->save();
    }
}

