<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;
use Totoglu\Console\Boost\Install\Enums\McpPathStrategy;

final class Trae extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'trae';
    }

    public function displayName(): string
    {
        return 'Trae';
    }

    public function mcpConfigPath(): ?string
    {
        return '.trae/mcp.json';
    }

    public function systemDetectionPaths(): array
    {
        return ['/Applications/Trae.app', '~/.trae'];
    }

    public function projectDetectionPaths(): array
    {
        return ['.trae', '.trae/mcp.json'];
    }

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }

    public function skillsPath(): string
    {
        return '.trae/skills';
    }

    /**
     * Trae IDE supports ${workspaceFolder} variable natively.
     */
    public function mcpPathStrategy(): McpPathStrategy
    {
        return McpPathStrategy::WorkspaceFolder;
    }

    public function exportSkill(string $skillName, string $skillPath, string $targetDir): string
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $skillDir = $targetDir . '/' . $skillName;
        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }

        if (is_dir($skillPath)) {
            $this->copyDirectory($skillPath, $skillDir);
        }

        $skillMdPath = is_file($skillPath . '/SKILL.md') ? $skillPath . '/SKILL.md' : $skillPath;
        $raw = is_file($skillMdPath) ? (string) file_get_contents($skillMdPath) : '';
        $targetPath = $skillDir . '/SKILL.md';

        if (str_starts_with(trim($raw), '---')) {
            file_put_contents($targetPath, $raw);
            return $targetPath;
        }

        $lines = preg_split('/\R/', $raw) ?: [];
        $title = '';
        $desc = '';
        foreach ($lines as $i => $line) {
            if ($i === 0 && str_starts_with(trim($line), '#')) {
                $title = trim(ltrim(trim($line), '# '));
            }
            if (str_starts_with(trim($line), 'description:')) {
                $desc = trim(substr(trim($line), strlen('description:')));
            }
        }

        $frontmatter = "---\nname: \"{$skillName}\"\ndescription: \"" . addcslashes($desc !== '' ? $desc : $title, '"') . "\"\n---\n\n";
        $body = $raw;
        if (!str_starts_with(ltrim($body), '#')) {
            $body = "# {$title}\n\n" . $body;
        }
        file_put_contents($targetPath, $frontmatter . $body);

        return $targetPath;
    }
}
