<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class DocIndex
{
    private array $discoveredTags = [];
    private array $synthesizedMethods = [];
    private array $classRelationships = [];

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function getDiscoveredTags(): array
    {
        return $this->discoveredTags;
    }

    public function getSynthesizedMethods(): array
    {
        return $this->synthesizedMethods;
    }

    public function getClassRelationships(): array
    {
        return $this->classRelationships;
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
        $this->discoveredTags = [];
        $this->synthesizedMethods = [];
        $this->classRelationships = [];
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
                            $methodParams = $this->extractParamsFromTokens($tokens, $j);
                            $methods[$name] = $this->parseDocBlock($lastDoc, $name, $methodParams);
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
                $this->trackRelatedClasses($fqcn, $methods);
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
        if (preg_match('/#pw-summary\s+(.+)/', $doc, $matches)) {
            return trim($matches[1]);
        }
        $lines = preg_split('/\R/', $doc) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\/\*\*|\*\/$/', '', $line);
            $line = preg_replace('/^\*/', '', $line);
            $line = trim($line ?? '');
            if ($line === '' || str_starts_with($line, '@') || str_starts_with($line, '#pw-')) {
                continue;
            }
            $out[] = $line;
            if (count($out) >= 1) {
                break;
            }
        }
        return implode(' ', $out);
    }

    private function extractPwBody(?string $doc): ?string
    {
        if (!$doc) {
            return null;
        }
        if (preg_match('/#pw-body\s*=\s*(.+)/s', $doc, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function extractPwGroups(string $doc): ?string
    {
        if (preg_match('/#pw-group-(\w+)/', $doc, $matches)) {
            return $matches[1];
        }
        if (preg_match('/#pw-order-groups\s+([\w,]+)/', $doc, $matches)) {
            $groups = explode(',', $matches[1]);
            return trim($groups[0]);
        }
        return null;
    }

    private function extractPwInternal(string $doc): bool
    {
        return (bool) preg_match('/#pw-internal/', $doc);
    }

    private function extractPwVar(string $doc): bool
    {
        return (bool) preg_match('/#pw-var/', $doc);
    }

    private function extractPwConstants(string $doc): array
    {
        if (preg_match('/#pw-use-constants\s+(.+)/', $doc, $matches)) {
            return array_filter(array_map('trim', explode(',', $matches[1])));
        }
        return [];
    }

    private function extractReturnClass(?string $returnType): ?string
    {
        if (!$returnType) {
            return null;
        }
        $type = trim($returnType);
        $type = preg_replace('/\?/', '', $type);
        $type = preg_replace('/\[\]/', '', $type);
        $type = preg_replace('/\|.*/', '', $type);
        
        $classMap = [
            'Page' => 'ProcessWire\Page',
            'PageArray' => 'ProcessWire\PageArray',
            'Pages' => 'ProcessWire\Pages',
            'User' => 'ProcessWire\User',
            'Users' => 'ProcessWire\Users',
            'Template' => 'ProcessWire\Template',
            'Templates' => 'ProcessWire\Templates',
            'Field' => 'ProcessWire\Field',
            'Fields' => 'ProcessWire\Fields',
            'Module' => 'ProcessWire\Module',
            'Modules' => 'ProcessWire\Modules',
            'NullPage' => 'ProcessWire\NullPage',
            'WireArray' => 'ProcessWire\WireArray',
            'WireData' => 'ProcessWire\WireData',
            'Wire' => 'ProcessWire\Wire',
        ];
        
        return $classMap[$type] ?? null;
    }

    private function extractPwHooker(string $doc): bool
    {
        return (bool) preg_match('/#pw-hooker/', $doc);
    }

    private function synthesizeUndocumentedMethod(string $methodName, array $params, ?string $returnType): array
    {
        $verb = '';
        $subject = '';
        
        $patterns = [
            'get' => ['Retrieves', 'the'],
            'set' => ['Sets', 'the value of'],
            'add' => ['Adds a new', ''],
            'remove' => ['Removes the', ''],
            'has' => ['Checks whether', 'exists'],
            'is' => ['Returns whether', 'is true'],
            'find' => ['Finds and returns', ''],
            'save' => ['Saves', ''],
            'delete' => ['Deletes', ''],
            'create' => ['Creates a new', ''],
            'load' => ['Loads', ''],
            'build' => ['Builds', ''],
            'render' => ['Renders', ''],
            'format' => ['Formats', ''],
            'validate' => ['Validates', ''],
        ];
        
        $lowerName = strtolower($methodName);
        foreach ($patterns as $pattern => $replacement) {
            if (str_starts_with($lowerName, $pattern)) {
                $verb = $replacement[0];
                $subject = str_replace('_', ' ', substr($methodName, strlen($pattern)));
                break;
            }
        }
        
        if (!$verb) {
            $verb = 'Performs operation on';
            $subject = str_replace('_', ' ', $methodName);
        }
        
        $summary = "[Synthesized] {$verb} {$subject}.";
        
        $this->synthesizedMethods[] = [
            'method' => $methodName,
            'summary' => $summary,
            'params' => count($params),
            'return' => $returnType,
        ];
        
        return [
            'summary' => $summary,
            'synthesized' => true,
        ];
    }

    private function trackRelatedClasses(string $fqcn, array $methods): void
    {
        $related = [];
        foreach ($methods as $m) {
            $returnClass = $m['return_class'] ?? null;
            if ($returnClass && $returnClass !== $fqcn) {
                $related[] = $returnClass;
            }
        }
        if (!empty($related)) {
            $this->classRelationships[$fqcn] = array_unique($related);
        }
    }

    private function trackDiscoveredTags(string $doc): void
    {
        if (preg_match_all('/#pw-[a-zA-Z0-9_-]+/', $doc, $matches)) {
            foreach ($matches[0] as $tag) {
                $this->discoveredTags[$tag] = ($this->discoveredTags[$tag] ?? 0) + 1;
            }
        }
    }

    private function parseDocBlock(?string $doc, string $methodName = '', array $params = []): array
    {
        $this->trackDiscoveredTags($doc ?? '');
        
        $summary = $this->extractSummary($doc);
        $pwBody = $this->extractPwBody($doc);
        $pwGroup = $this->extractPwGroups($doc ?? '');
        $pwInternal = $this->extractPwInternal($doc ?? '');
        $pwHooker = $this->extractPwHooker($doc ?? '');
        
        $synthesized = false;
        $returnClass = null;
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
                    $returnType = $parts[1] ?? '';
                    $return = ['type' => $returnType, 'description' => $parts[2] ?? ''];
                    $returnClass = $this->extractReturnClass($returnType);
                } elseif (str_starts_with($line, '@deprecated')) {
                    $deprecated = true;
                } elseif (str_starts_with($line, '@since')) {
                    $parts = preg_split('/\s+/', $line, 2);
                    $since = trim($parts[1] ?? '');
                }
            }
        }
        
        if (!$summary) {
            $synthData = $this->synthesizeUndocumentedMethod($methodName, $params, $return['type'] ?? null);
            $summary = $synthData['summary'];
            $synthesized = $synthData['synthesized'];
        }
        
        return [
            'summary' => $summary,
            'pw_body' => $pwBody,
            'pw_group' => $pwGroup,
            'pw_internal' => $pwInternal,
            'pw_hooker' => $pwHooker,
            'synthesized' => $synthesized,
            'related_classes' => $returnClass ? [$returnClass] : [],
            'return_class' => $returnClass,
            'params' => $params,
            'return' => $return,
            'deprecated' => $deprecated,
            'since' => $since,
        ];
    }

    private function extractParamsFromTokens(array $tokens, int $funcTokenIndex): array
    {
        $params = [];
        $j = $funcTokenIndex + 1;
        $c = count($tokens);
        
        while ($j < $c) {
            if (is_array($tokens[$j])) {
                if ($tokens[$j][0] === T_VARIABLE) {
                    $varName = ltrim($tokens[$j][1], '$');
                    $type = '';
                    $default = null;
                    
                    $k = $funcTokenIndex + 1;
                    while ($k < $j) {
                        if (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) {
                            $type = $tokens[$k][1];
                        }
                        if ($tokens[$k] === '=') {
                            $k++;
                            while ($k < $c && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;
                            if ($k < $c) $default = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                        }
                        $k++;
                    }
                    
                    $params[] = [
                        'name' => $varName,
                        'type' => $type,
                        'default' => $default,
                    ];
                }
            } elseif (!is_array($tokens[$j]) && $tokens[$j] === ')') {
                break;
            }
            $j++;
        }
        
        return $params;
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
