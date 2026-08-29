<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;
use Totoglu\Console\Boost\Install\Enums\McpPathStrategy;
use Totoglu\Console\Boost\Install\Mcp\FileWriter;
use Totoglu\Console\Boost\Install\Mcp\TomlFileWriter;

abstract class Agent
{
    abstract public function name(): string;
    abstract public function displayName(): string;

    public function mcpConfigPath(): ?string
    {
        return null;
    }

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }

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

    public function frontmatter(): bool
    {
        return false;
    }

    public function transformGuidelines(string $markdown): string
    {
        return $markdown;
    }

    /**
     * @return list<string>
     */
    public function systemDetectionPaths(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function systemDetectionBinaries(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function projectDetectionPaths(): array
    {
        $paths = [];

        $guidelinesPath = $this->guidelinesPath();
        if ($guidelinesPath !== 'AGENTS.md' && $guidelinesPath !== '') {
            $paths[] = $guidelinesPath;
        }

        $mcpConfigPath = $this->mcpConfigPath();
        if (is_string($mcpConfigPath) && $mcpConfigPath !== '') {
            $paths[] = $mcpConfigPath;
        }

        $skillsPath = $this->skillsPath();
        if ($skillsPath !== '.agents/skills' && $skillsPath !== '') {
            $paths[] = $skillsPath;
        }

        return array_values(array_unique($paths));
    }

    public function detectOnSystem(): bool
    {
        foreach ($this->systemDetectionPaths() as $path) {
            if ($this->pathExists($this->expandHome($path))) {
                return true;
            }
        }

        foreach ($this->systemDetectionBinaries() as $binary) {
            if ($this->binaryExists($binary)) {
                return true;
            }
        }

        return false;
    }

    public function detectInProject(string $basePath): bool
    {
        foreach ($this->projectDetectionPaths() as $path) {
            if ($this->pathExists($basePath . '/' . ltrim($path, '/'))) {
                return true;
            }
        }

        return false;
    }

    public function mcpConfigKey(): string
    {
        return str_ends_with((string) $this->mcpConfigPath(), '.toml') ? 'mcp_servers' : 'mcpServers';
    }

    public function defaultMcpConfig(): array
    {
        return [$this->mcpConfigKey() => []];
    }

    public function mcpServerConfig(string $command, array $args = [], array $env = [], string $cwd = ''): array
    {
        $payload = ['command' => $command, 'args' => $args, 'env' => $env];
        if ($cwd !== '') {
            $payload['cwd'] = $cwd;
        }
        return $payload;
    }

    public function installMcp(string $key, string $command, array $args = [], array $env = [], string $cwd = ''): bool
    {
        $path = $this->mcpConfigPath();
        if (!$path) {
            return false;
        }

        if (str_ends_with($path, '.toml')) {
            $w = new TomlFileWriter($path, $this->defaultMcpConfig());
            return $w->configKey($this->mcpConfigKey())->addServerConfig($key, $this->mcpServerConfig($command, $args, $env, $cwd))->save();
        }

        $w = new FileWriter($path, $this->defaultMcpConfig());
        return $w->configKey($this->mcpConfigKey())->addServerConfig($key, $this->mcpServerConfig($command, $args, $env, $cwd))->save();
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

    /**
     * @return array<class-string, bool>
     */
    public function supportedContracts(): array
    {
        return [
            SupportsGuidelines::class => $this instanceof SupportsGuidelines,
            SupportsSkills::class => $this instanceof SupportsSkills,
            SupportsMcp::class => $this instanceof SupportsMcp,
        ];
    }

    private function binaryExists(string $binary): bool
    {
        $escaped = escapeshellarg($binary);
        $command = PHP_OS_FAMILY === 'Windows'
            ? "where {$escaped} >NUL 2>&1"
            : "command -v {$escaped} >/dev/null 2>&1";

        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    private function pathExists(string $path): bool
    {
        return file_exists($path) || is_dir($path);
    }

    private function expandHome(string $path): string
    {
        if (!str_starts_with($path, '~/')) {
            return $path;
        }

        $home = getenv('HOME');

        return is_string($home) && $home !== '' ? $home . substr($path, 1) : $path;
    }
}
