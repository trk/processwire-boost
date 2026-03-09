<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class SkillBuilder
{
    public function __construct(private readonly string $projectRoot) {}

    public function buildFromSources(?array $select = null): array
    {
        $srcDir = $this->projectRoot . '/vendor/processwire/boost/resources/builder/skills';
        $fallbackDir = __DIR__ . '/../resources/builder/skills';
        $base = is_dir($srcDir) ? $srcDir : $fallbackDir;
        $files = glob($base . '/*.md') ?: [];
        $outDir = $this->projectRoot . '/.ai/skills/pw_core';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        $written = [];
        foreach ($files as $f) {
            $name = pathinfo($f, PATHINFO_FILENAME);
            if ($select && !in_array($name, $select, true)) continue;
            $sd = $outDir . '/' . $name;
            if (!is_dir($sd)) mkdir($sd, 0755, true);
            $target = $sd . '/SKILL.md';
            $content = rtrim((string)file_get_contents($f)) . "\n";
            file_put_contents($target, $content);
            $written[] = $target;
        }
        return $written;
    }

    public function exportForTrae(?array $select = null, ?string $targetDir = null): array
    {
        $srcDir = $this->projectRoot . '/vendor/processwire/boost/resources/builder/skills';
        $fallbackDir = __DIR__ . '/../resources/builder/skills';
        $base = is_dir($srcDir) ? $srcDir : $fallbackDir;
        $files = glob($base . '/*.md') ?: [];
        $outDir = $targetDir ?: ($this->projectRoot . '/.trae/skills');
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        $written = [];
        foreach ($files as $f) {
            $name = pathinfo($f, PATHINFO_FILENAME);
            if ($select && !in_array($name, $select, true)) continue;
            $raw = (string)file_get_contents($f);
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
            $skillDir = $outDir . '/' . $name;
            if (!is_dir($skillDir)) mkdir($skillDir, 0755, true);
            $target = $skillDir . '/SKILL.md';
            $frontmatter = "---\nname: \"{$name}\"\ndescription: \"" . addcslashes($desc !== '' ? $desc : $title, "\"") . "\"\n---\n\n";
            $body = $raw;
            if (!str_starts_with(ltrim($body), '#')) {
                $body = "# {$title}\n\n" . $body;
            }
            file_put_contents($target, $frontmatter . $body);
            $written[] = $target;
        }
        return $written;
    }
}
