<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class ConfigReader
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function read(string $path = '.llms/docgen.yml'): array
    {
        $p = $this->projectRoot . '/' . ltrim($path, '/');
        if (!is_file($p)) {
            return [];
        }
        $txt = file_get_contents($p) ?: '';
        $txt = trim($txt);
        if ($txt === '') {
            return [];
        }
        if (str_starts_with($txt, '{')) {
            $json = json_decode($txt, true);
            return is_array($json) ? $json : [];
        }
        $cfg = [];
        $cur = null;
        foreach (preg_split('/\R/', $txt) as $line) {
            $s = trim($line);
            if ($s === '' || str_starts_with($s, '#')) {
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $s, $m)) {
                $cur = $m[1];
                $val = trim($m[2]);
                if ($val === 'true' || $val === 'false') {
                    $cfg[$cur] = $val === 'true';
                } elseif ($val !== '') {
                    $cfg[$cur] = $val;
                } else {
                    if (!isset($cfg[$cur])) $cfg[$cur] = [];
                }
                continue;
            }
            if ($cur && str_starts_with($s, '-')) {
                $item = trim(substr($s, 1));
                if (is_array($cfg[$cur] ?? null)) {
                    $cfg[$cur][] = $item;
                }
            }
        }
        foreach (['includes','excludes','seed_guides_dirs'] as $key) {
            $val = $cfg[$key] ?? null;
            if (is_string($val)) {
                $s = trim($val);
                if ($s === '' || $s === '[]') {
                    $cfg[$key] = [];
                } elseif (str_starts_with($s, '[') && str_ends_with($s, ']')) {
                    $arr = json_decode($s, true);
                    $cfg[$key] = is_array($arr) ? $arr : array_filter(array_map('trim', explode(',', trim($s, '[]'))));
                } else {
                    $cfg[$key] = [$s];
                }
            }
        }
        return $cfg;
    }
}

