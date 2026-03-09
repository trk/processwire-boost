<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class DocIndex
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function scanPaths(array $includes, array $excludes): array
    {
        $files = $this->collectFiles($includes, $excludes);
        $classFiles = [];
        foreach ($files as $p) {
            $rel = str_starts_with($p, $this->projectRoot.'/') ? substr($p, strlen($this->projectRoot.'/')) : $p;
            if (str_starts_with($rel, 'wire/core/')) {
                $classFiles[] = substr($rel, strlen('wire/core/'));
            }
        }
        $index = $this->scan($classFiles);
        foreach ($index as $fqcn => &$meta) {
            $file = $meta['file'] ?? '';
            $meta['file'] = $file;
        }
        return $index;
    }

    private function collectFiles(array $includes, array $excludes): array
    {
        $exts = ['php','module','module.php'];
        $out = [];
        foreach ($includes as $base) {
            $dir = $this->projectRoot . '/' . ltrim($base, '/');
            if (!is_dir($dir)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $fn = $file->getFilename();
                $ok = false;
                foreach ($exts as $e) {
                    if (str_ends_with($fn, '.' . $e) || $fn === $e) { $ok = true; break; }
                }
                if (!$ok) continue;
                $rel = ltrim(str_replace($this->projectRoot.'/', '', $file->getPathname()), '/');
                $skip = false;
                foreach ($excludes as $ex) {
                    $pattern = '#^' . str_replace(['**','*'], ['.*','[^/]*'], preg_quote($ex, '#')) . '$#';
                    if (preg_match($pattern, $rel)) { $skip = true; break; }
                }
                if ($skip) continue;
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }
    public function scan(array $classFiles): array
    {
        $results = [];
        foreach ($classFiles as $file) {
            $path = $this->projectRoot . '/wire/core/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $content = file_get_contents($path) ?: '';
            $tokens = token_get_all($content);
            $ns = '';
            $class = '';
            $classDoc = null;
            $methods = [];
            $lastDoc = null;
            $captureNs = false;
            for ($i = 0, $c = count($tokens); $i < $c; $i++) {
                $t = $tokens[$i];
                if (is_array($t)) {
                    if ($t[0] === T_DOC_COMMENT) {
                        $lastDoc = $t[1];
                    } elseif ($t[0] === T_NAMESPACE) {
                        $captureNs = true;
                        $ns = '';
                    } elseif ($captureNs && ($t[0] === T_STRING || (defined('T_NAME_QUALIFIED') && $t[0] === T_NAME_QUALIFIED) || $t[0] === T_NS_SEPARATOR)) {
                        $ns .= $t[1];
                    } elseif ($t[0] === T_CLASS) {
                        $j = $i + 1;
                        while ($j < $c && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                            $j++;
                        }
                        if ($j < $c && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $class = $tokens[$j][1];
                            $classDoc = $lastDoc;
                            $lastDoc = null;
                        }
                    } elseif ($t[0] === T_FUNCTION && $class) {
                        $j = $i + 1;
                        while ($j < $c && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                            $j++;
                        }
                        $name = null;
                        if ($j < $c && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $name = $tokens[$j][1];
                        }
                        if ($name && $name !== '__construct') {
                            $methods[$name] = $this->parseDocBlock($lastDoc);
                        }
                        $lastDoc = null;
                    }
                } else {
                    if ($captureNs && $t === ';') {
                        $captureNs = false;
                    }
                }
            }
            if ($class) {
                $fqcn = ltrim($ns . '\\' . $class, '\\');
                $results[$fqcn] = [
                    'file' => $file,
                    'summary' => $this->extractSummary($classDoc),
                    'since' => $this->extractSince($classDoc),
                    'methods' => $methods,
                ];
            }
        }
        return $results;
    }

    private function extractSummary(?string $doc): string
    {
        if (!$doc) {
            return '';
        }
        $lines = preg_split('/\R/', $doc) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\/\*\*|\*\/$/', '', $line);
            $line = preg_replace('/^\*/', '', $line);
            $line = trim($line ?? '');
            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }
            $out[] = $line;
            if (count($out) >= 1) {
                break;
            }
        }
        return implode(' ', $out);
    }

    private function parseDocBlock(?string $doc): array
    {
        $summary = $this->extractSummary($doc);
        $params = [];
        $return = null;
        $deprecated = false;
        $since = null;
        if ($doc) {
            $lines = preg_split('/\R/', $doc) ?: [];
            foreach ($lines as $line) {
                $line = trim(preg_replace('/^\* ?/', '', trim($line)) ?? '');
                if (str_starts_with($line, '@param')) {
                    $parts = preg_split('/\s+/', $line, 4);
                    $type = $parts[1] ?? '';
                    $name = $parts[2] ?? '';
                    $desc = $parts[3] ?? '';
                    $params[] = ['type' => $type, 'name' => ltrim($name, '$'), 'description' => $desc];
                } elseif (str_starts_with($line, '@return')) {
                    $parts = preg_split('/\s+/', $line, 3);
                    $return = ['type' => $parts[1] ?? '', 'description' => $parts[2] ?? ''];
                } elseif (str_starts_with($line, '@deprecated')) {
                    $deprecated = true;
                } elseif (str_starts_with($line, '@since')) {
                    $parts = preg_split('/\s+/', $line, 2);
                    $since = trim($parts[1] ?? '');
                }
            }
        }
        return [
            'summary' => $summary,
            'params' => $params,
            'return' => $return,
            'deprecated' => $deprecated,
            'since' => $since,
        ];
    }

    private function extractSince(?string $doc): ?string
    {
        if (!$doc) {
            return null;
        }
        $lines = preg_split('/\R/', $doc) ?: [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\* ?/', '', trim($line)) ?? '');
            if (str_starts_with($line, '@since')) {
                $parts = preg_split('/\s+/', $line, 2);
                return trim($parts[1] ?? '');
            }
        }
        return null;
    }
}
