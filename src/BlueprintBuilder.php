<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class BlueprintBuilder
{
    public function __construct(private readonly string $projectRoot) {}

    public function build(array $index, string $targetDir): array
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $written = [];
        foreach ($index as $fqcn => $meta) {
            $name = $this->basename($fqcn);
            $bp = [
                'id' => $fqcn,
                'name' => $name,
                'kind' => 'class',
                'summary' => $meta['summary'] ?? '',
                'className' => $fqcn,
                'sourcePath' => 'wire/core/' . ($meta['file'] ?? ''),
                'since' => $meta['since'] ?? null,
                'related_classes' => $meta['related_classes'] ?? [],
                'methods' => $this->formatMethods($meta['methods'] ?? []),
            ];
            $path = rtrim($targetDir, '/') . '/' . $name . '.json';
            file_put_contents($path, json_encode($bp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $written[] = $path;
        }
        $this->mergeSeedBlueprints($targetDir);
        return $written;
    }

    private function basename(string $fqcn): string
    {
        $p = strrpos($fqcn, '\\');
        return $p === false ? $fqcn : substr($fqcn, $p + 1);
    }

    private function formatMethods(array $methods): array
    {
        $out = [];
        foreach ($methods as $name => $m) {
            $out[] = [
                'name' => $name,
                'description' => $m['summary'] ?? '',
                'pw_group' => $m['pw_group'] ?? null,
                'pw_body' => $m['pw_body'] ?? null,
                'pw_internal' => (bool)($m['pw_internal'] ?? false),
                'pw_hooker' => (bool)($m['pw_hooker'] ?? false),
                'synthesized' => (bool)($m['synthesized'] ?? false),
                'related_classes' => $m['related_classes'] ?? [],
                'return_class' => $m['return_class'] ?? null,
                'params' => $m['params'] ?? [],
                'return' => $m['return'] ?? null,
                'deprecated' => (bool)($m['deprecated'] ?? false),
                'since' => $m['since'] ?? null,
            ];
        }
        return $out;
    }

    private function mergeSeedBlueprints(string $targetDir): void
    {
        $srcDir = $this->projectRoot . '/vendor/processwire/boost/resources/builder/blueprints';
        $fallbackDir = __DIR__ . '/../resources/builder/blueprints';
        $base = is_dir($srcDir) ? $srcDir : $fallbackDir;
        if (!is_dir($base)) {
            return;
        }
        $files = glob($base . '/*.json') ?: [];
        foreach ($files as $f) {
            $name = basename($f);
            copy($f, rtrim($targetDir, '/') . '/' . $name);
        }
    }
}
