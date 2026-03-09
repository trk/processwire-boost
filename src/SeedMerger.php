<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class SeedMerger
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function mergeGuides(array $dirs): void
    {
        $target = $this->projectRoot . '/.ai/guidelines/_seed';
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
        foreach ($dirs as $d) {
            $sp = $this->projectRoot . '/' . ltrim($d, '/');
            if (!is_dir($sp)) continue;
            foreach (scandir($sp) as $f) {
                if ($f === '.' || $f === '..') continue;
                if (pathinfo($f, PATHINFO_EXTENSION) !== 'md') continue;
                $src = $sp . '/' . $f;
                $dst = $target . '/' . $f;
                @copy($src, $dst);
            }
        }
    }

    public function mergeSkillsFromTaxonomy(?string $jsonPath): void
    {
        if (!$jsonPath) return;
        $sp = $this->projectRoot . '/' . ltrim($jsonPath, '/');
        if (!is_file($sp)) return;
        $data = json_decode(file_get_contents($sp) ?: '', true);
        if (!is_array($data)) return;
        $skills = $data['skills'] ?? [];
        if (!is_array($skills)) return;
        $base = $this->projectRoot . '/.ai/skills/pw_core';
        if (!is_dir($base)) mkdir($base, 0755, true);
        foreach ($skills as $key => $desc) {
            $dir = $base . '/' . $key;
            $path = $dir . '/SKILL.md';
            if (is_file($path)) continue;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $lines = [];
            $title = ucwords(str_replace('_', ' ', (string)$key));
            $lines[] = '# ' . $title;
            $lines[] = 'description: ' . (string)$desc;
            $lines[] = '## Blueprints';
            $lines[] = '## Steps';
            $lines[] = '- Determine';
            $lines[] = '- Execute';
            $lines[] = '## Request';
            $lines[] = '';
            $lines[] = '## Response';
            $lines[] = '';
            $lines[] = '## Example';
            $lines[] = '```php';
            $lines[] = '```';
            $lines[] = '## Compatibility';
            $lines[] = 'Refer to linked blueprints for @since version notes on classes and methods.';
            file_put_contents($path, implode("\n", $lines) . "\n");
        }
    }
}

