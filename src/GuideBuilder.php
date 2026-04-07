<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class GuideBuilder
{
    public function __construct(private readonly string $projectRoot) {}

    public function build(array $index, string $targetPath): void
    {
        $sections = [];
        $guideNames = [
            'header',
            'api',
            'page',
            'pages',
            'hooks',
            'files',
            'fieldtype',
            'inputfield',
            'process',
            'markup',
            'module',
        ];
        foreach ($guideNames as $i => $name) {
            $sections[] = $this->includeGuide($name);
            if ($i === 0) {
                $sections[] = '## Common Tasks';
            }
        }
        $sections[] = '## Class Summaries';
        foreach ($index as $fqcn => $meta) {
            $sections[] = '### ' . $fqcn;
            $sum = trim($meta['summary'] ?? '');
            if ($sum !== '') {
                $sections[] = $sum;
            }
            $since = $meta['since'] ?? null;
            if ($since) {
                $sections[] = 'Since: ' . $since;
            }
            $bpName = $this->basename($fqcn);
            $sections[] = 'Blueprint: .llms/blueprints/pw_core/' . $bpName . '.json';
            if (!empty($meta['methods'])) {
                $sections[] = '**Featured methods**';
                $n = 0;
                foreach ($meta['methods'] as $name => $ms) {
                    $desc = is_array($ms) ? ($ms['summary'] ?? '') : (string)$ms;
                    $mSince = is_array($ms) ? ($ms['since'] ?? null) : null;
                    $suffix = $mSince ? ' (since ' . $mSince . ')' : '';
                    $sections[] = '- ' . $name . ': ' . $desc . $suffix;
                    $n++;
                    if ($n >= 5) {
                        break;
                    }
                }
            }
        }
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($targetPath, implode("\n\n", $sections) . "\n");
    }

    private function basename(string $fqcn): string
    {
        $p = strrpos($fqcn, '\\');
        return $p === false ? $fqcn : substr($fqcn, $p + 1);
    }

    private function includeGuide(string $name): string
    {
        $candidates = [
            $this->projectRoot . '/vendor/processwire/boost/resources/builder/guidelines/' . $name . '.md',
            __DIR__ . '/../resources/builder/guidelines/' . $name . '.md',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return rtrim((string)file_get_contents($path));
            }
        }
        return '';
    }
}
