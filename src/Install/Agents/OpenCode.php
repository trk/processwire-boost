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
        $skillDirName = str_replace('_', '-', $skillName);
        $skillDir = $targetDir . '/' . $skillDirName;
        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }
        $targetPath = $skillDir . '/SKILL.md';
        $content = file_get_contents($skillPath);
        $description = $this->extractDescription($content);
        $frontmatter = $this->buildFrontmatter($skillDirName, $description);
        file_put_contents($targetPath, $frontmatter . "\n" . $content);
        return $targetPath;
    }

    private function extractDescription(string $content): string
    {
        $lines = explode("\n", trim($content));
        $description = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            $description = trim(ltrim($line, '- '));
            $description = preg_replace('/^description:\s*/i', '', $description);
            break;
        }
        $description = str_replace(["\n", '*', '`'], '', $description);
        $description = preg_replace('/\s+/', ' ', $description);
        return substr($description, 0, 1024);
    }

    private function buildFrontmatter(string $name, string $description): string
    {
        return <<<YAML
---
name: {$name}
description: {$description}
license: MIT
compatibility: opencode
---
YAML;
    }
}

