<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Agents;

use Totoglu\ProcessWire\Boost\Install\Enums\McpPathStrategy;

final class Trae extends Agent
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

    public function guidelinesPath(): string
    {
        return 'AGENTS.md';
    }

    public function skillsPath(): string
    {
        return '.trae/rules';
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

        $raw = (string) file_get_contents($skillPath);
        $lines = preg_split('/\R/', $raw);
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

        $skillDir = $targetDir . '/' . $skillName;
        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }

        $targetPath = $skillDir . '/SKILL.md';
        $frontmatter = "---\nname: \"{$skillName}\"\ndescription: \"" . addcslashes($desc !== '' ? $desc : $title, '"') . "\"\n---\n\n";
        $body = $raw;
        if (!str_starts_with(ltrim($body), '#')) {
            $body = "# {$title}\n\n" . $body;
        }
        file_put_contents($targetPath, $frontmatter . $body);

        return $targetPath;
    }
}
