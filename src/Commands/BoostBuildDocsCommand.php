<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;

final class BoostBuildDocsCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('boost:build:docs')
            ->setDescription('Generate ProcessWire Core API reference documentation from source files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Build API Documentation');

        $projectRoot = getcwd();
        $wireDir = realpath($projectRoot . '/wire');
        $docsDir = $projectRoot . '/.agents/docs';

        if (!$wireDir || !is_dir($wireDir)) {
            $output->writeln("<error>Wire library directory not found at {$projectRoot}/wire. Cannot build core docs.</error>");
            return Command::FAILURE;
        }

        if (!is_dir($docsDir)) {
            mkdir($docsDir, 0755, true);
        }

        $files = $this->getPhpFiles($wireDir);

        spin(function () use ($files, $wireDir, $docsDir) {
            $this->processFiles($files, $wireDir, $docsDir);
        }, 'Generating API reference markdown files...');

        info('ProcessWire Core API Documentation generated into .agents/docs');

        outro('Docs Build Complete');
        return Command::SUCCESS;
    }

    private function getPhpFiles(string $dir): array
    {
        $files = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function processFiles(array $files, string $wireDir, string $docsDir): void
    {
        $rootTree = new DocTree('', '');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (!$content) continue;

            if (!preg_match('/\b(class|trait|interface)\s+([a-zA-Z0-9_]+)/', $content)) {
                continue;
            }

            $namespace = '';
            if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/', $content, $m)) {
                $namespace = $m[1];
            }

            $tokens = token_get_all($content);
            $classes = [];
            $currentClass = null;
            $currentDoc = null;
            $currentVisibility = 'public';
            $isStatic = false;

            $i = 0;
            $tokenCount = count($tokens);
            while ($i < $tokenCount) {
                $token = $tokens[$i];

                if (is_array($token)) {
                    $type = $token[0];
                    $value = $token[1];

                    if ($type === T_DOC_COMMENT) {
                        $currentDoc = $value;
                    } elseif ($type === T_CLASS || $type === T_INTERFACE || $type === T_TRAIT) {
                        $i++;
                        while ($i < $tokenCount && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) $i++;
                        if ($i < $tokenCount && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                            $className = $tokens[$i][1];
                            $classes[] = [
                                'name' => $className,
                                'doc' => $this->parseDocBlock($currentDoc),
                                'methods' => []
                            ];
                            $currentClass = &$classes[count($classes) - 1];
                            $currentDoc = null;
                        }
                    } elseif ($type === T_PUBLIC || $type === T_PROTECTED || $type === T_PRIVATE) {
                        $currentVisibility = strtolower($value);
                    } elseif ($type === T_STATIC) {
                        $isStatic = true;
                    } elseif ($type === T_FUNCTION && $currentClass !== null) {
                        $i++;
                        while ($i < $tokenCount && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) $i++;
                        if ($i < $tokenCount && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                            $funcName = $tokens[$i][1];

                            $signature = "";
                            $bracketCount = 0;
                            $started = false;
                            for ($j = $i; $j < $tokenCount; $j++) {
                                $t = $tokens[$j];
                                $val = is_array($t) ? $t[1] : $t;
                                $signature .= $val;

                                if ($val === '(') {
                                    $bracketCount++;
                                    $started = true;
                                } elseif ($val === ')') {
                                    $bracketCount--;
                                    if ($started && $bracketCount === 0) {
                                        break;
                                    }
                                }
                            }

                            if ($currentVisibility === 'public' || strpos($funcName, '___') === 0) {
                                $parsedDoc = $this->parseDocBlock($currentDoc);
                                $isMagic = strpos($funcName, '__') === 0;
                                $isHookable = strpos($funcName, '___') === 0 || $parsedDoc['hooker'];
                                $hasDoc = !empty($parsedDoc['summary']) || !empty($parsedDoc['body']) || !empty($parsedDoc['params']) || !empty($parsedDoc['return']);

                                $keep = false;
                                if (!$parsedDoc['internal']) {
                                    if ($isHookable) {
                                        $keep = true;
                                    } elseif ($isMagic) {
                                        if (!empty($parsedDoc['summary'])) {
                                            $keep = true;
                                        }
                                    } elseif ($hasDoc) {
                                        $keep = true;
                                    }
                                }

                                if ($keep) {
                                    $currentClass['methods'][] = [
                                        'name' => $funcName,
                                        'visibility' => $currentVisibility,
                                        'static' => $isStatic,
                                        'signature' => trim($signature),
                                        'doc' => $parsedDoc
                                    ];
                                }
                            }
                        }
                        $currentDoc = null;
                        $currentVisibility = 'public';
                        $isStatic = false;
                    } else {
                        if ($type !== T_WHITESPACE) {
                            // Reset attrs if needed
                        }
                    }
                } else {
                    if ($token === '{' || $token === '}' || $token === ';') {
                        $currentDoc = null;
                        $currentVisibility = 'public';
                        $isStatic = false;
                    }
                }
                $i++;
            }

            foreach ($classes as $cls) {
                if ($cls['doc']['internal']) continue;

                $hasContent = !empty($cls['doc']['summary']) || 
                              !empty($cls['doc']['body']) || 
                              !empty($cls['doc']['properties']) || 
                              !empty($cls['methods']);

                if (!$hasContent) {
                    continue;
                }

                $relPath = str_replace($wireDir, '', $file);
                $relPath = ltrim($relPath, '/\\');

                $outPath = $docsDir . '/' . str_replace('.php', '.md', $relPath);
                $outDir = dirname($outPath);
                if (!is_dir($outDir)) {
                    mkdir($outDir, 0755, true);
                }

                $md = "---\n";
                $md .= "title: {$cls['name']}\n";
                $md .= "namespace: " . ($namespace ?: '\\') . "\n";
                $md .= "cso: processwire, core, {$cls['name']}\n";
                $md .= "---\n\n";

                $md .= "# `{$cls['name']}`\n\n";

                if (!empty($cls['doc']['summary'])) {
                    $md .= "**" . $cls['doc']['summary'] . "**\n\n";
                }

                if (!empty($cls['doc']['body'])) {
                    $md .= $cls['doc']['body'] . "\n\n";
                }

                if (!empty($cls['doc']['properties'])) {
                    $md .= "## Properties\n";
                    foreach ($cls['doc']['properties'] as $prop) {
                        $md .= "- `{$prop}`\n";
                    }
                    $md .= "\n";
                }

                $groupedMethods = [];
                foreach ($cls['methods'] as $m) {
                    $group = $m['doc']['group'];
                    if (!isset($groupedMethods[$group])) {
                        $groupedMethods[$group] = [];
                    }
                    $groupedMethods[$group][] = $m;
                }

                foreach ($groupedMethods as $groupName => $methods) {
                    $groupTitle = ucfirst(str_replace('-', ' ', (string) $groupName));
                    $md .= "## {$groupTitle}\n\n";

                    foreach ($methods as $m) {
                        $hookableStr = (strpos($m['name'], '___') === 0 || $m['doc']['hooker']) ? ' 🪝 Hookable' : '';
                        $staticStr = $m['static'] ? ' static' : '';
                        $md .= "### `{$m['visibility']}{$staticStr} {$m['signature']}`{$hookableStr}\n\n";

                        if (!empty($m['doc']['summary'])) {
                            $md .= "> " . $m['doc']['summary'] . "\n\n";
                        }
                        if (!empty($m['doc']['body'])) {
                            $md .= $m['doc']['body'] . "\n\n";
                        }

                        if (!empty($m['doc']['params'])) {
                            $md .= "**Parameters:**\n";
                            foreach ($m['doc']['params'] as $pName => $pDesc) {
                                $md .= "- `\${$pName}`: {$pDesc}\n";
                            }
                            $md .= "\n";
                        }

                        if (!empty($m['doc']['return'])) {
                            $md .= "**Returns:** `{$m['doc']['return']}`\n\n";
                        }
                    }
                }

                file_put_contents($outPath, $md);

                $parts = explode('/', dirname($relPath));
                $rootTree->addFile($parts, [
                    'file' => basename($outPath),
                    'name' => $cls['name'],
                    'namespace' => $namespace,
                    'summary' => $cls['doc']['summary']
                ]);
            }
        }

        $this->generateIndexes($rootTree, $docsDir);
    }

    private function parseDocBlock(?string $docblock): array
    {
        $info = [
            'summary' => '',
            'body' => '',
            'internal' => false,
            'group' => 'General',
            'params' => [],
            'return' => '',
            'hooker' => false,
            'properties' => [],
            'methods' => [],
        ];

        if (!$docblock) return $info;

        $lines = explode("\n", $docblock);
        $inBody = false;

        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\/?\**\/?\s?/', '', trim($line)));
            if (empty($line)) continue;

            if (strpos($line, '#pw-internal') === 0 || $line === '#pw-internal') {
                $info['internal'] = true;
            }
            if (strpos($line, '#pw-hooker') !== false) {
                $info['hooker'] = true;
            }

            if (preg_match('/#pw-summary\s+(.*)/', $line, $matches) || preg_match('/#pw-summary=(.*)/', $line, $matches)) {
                $info['summary'] = trim($matches[1]);
            } elseif (preg_match('/#pw-group-([a-zA-Z0-9_-]+)/', $line, $matches)) {
                $info['group'] = trim($matches[1]);
            } elseif (strpos($line, '#pw-body') === 0 && strpos($line, '#pw-body =') === false) {
                if (trim($line) === '#pw-body') {
                    $inBody = !$inBody;
                }
            } elseif (preg_match('/#pw-body\s*=(.*)/', $line, $matches)) {
                $info['body'] .= trim($matches[1]) . "\n";
                $inBody = true;
            } elseif ($inBody && strpos($line, '#pw-') !== 0 && strpos($line, '@') !== 0) {
                $info['body'] .= $line . "\n";
            } elseif (preg_match('/@param\s+(.*?)\s+\$([a-zA-Z0-9_]+)(.*)/', $line, $matches)) {
                $info['params'][$matches[2]] = trim($matches[1]) . ' - ' . trim($matches[3]);
            } elseif (preg_match('/@return\s+(.*)/', $line, $matches)) {
                $info['return'] = trim($matches[1]);
            } elseif (preg_match('/@property\s+(.*?)\s+(.*)/', $line, $matches)) {
                if (strpos($line, '#pw-internal') === false && stripos($line, 'deprecated') === false) {
                    $info['properties'][] = trim($matches[1]) . ' ' . trim($matches[2]);
                }
            } elseif (preg_match('/@method\s+(.*?)\s+(.*)/', $line, $matches)) {
                if (strpos($line, '#pw-internal') === false && stripos($line, 'deprecated') === false) {
                    $info['methods'][] = trim($matches[1]) . ' ' . trim($matches[2]);
                }
            }
        }

        $info['body'] = trim($info['body']);
        return $info;
    }

    private function generateIndexes(DocTree $node, string $baseDir)
    {
        if ($node->path) {
            $outDir = $baseDir . '/' . $node->path;
        } else {
            $outDir = $baseDir;
        }

        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $indexPath = $outDir . '/index.md';

        $md = "---\n";
        $title = $node->name ?: 'ProcessWire API Reference';
        if ($node->name === '') $title = 'ProcessWire API Documentation';
        $md .= "title: $title\n";
        if ($node->name !== '') {
            $md .= "cso: processwire, index, {$node->name}\n";
        } else {
            $md .= "cso: processwire, index\n";
        }
        $md .= "---\n\n";

        $md .= "# $title\n\n";

        if ($node->name === '') {
            $md .= "Welcome to the ProcessWire Core API Documentation context. Select a directory below to explore the components.\n\n";
        } else {
            $md .= "Explore the classes and subdirectories within the **{$node->name}** directory.\n\n";
        }

        if (!empty($node->children)) {
            $md .= "## Directories\n\n";
            ksort($node->children);
            foreach ($node->children as $childName => $childNode) {
                $md .= "- [**{$childName}/**]({$childName}/index.md)\n";
            }
            $md .= "\n";
        }

        if (!empty($node->files)) {
            $md .= "## Classes\n\n";
            usort($node->files, function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            foreach ($node->files as $f) {
                $nsText = (!empty($f['namespace']) && rtrim($f['namespace'], '\\') !== 'ProcessWire') ? '`namespace ' . rtrim($f['namespace'], '\\') . '` &mdash; ' : "";
                $desc = $f['summary'] ? " - " . $f['summary'] : "";
                $md .= "- {$nsText}[`{$f['name']}`]({$f['file']}){$desc}\n";
            }
            $md .= "\n";
        }

        file_put_contents($indexPath, $md);

        foreach ($node->children as $childNode) {
            $this->generateIndexes($childNode, $baseDir);
        }
    }
}

class DocTree
{
    public $name;
    public $path;
    public $files = [];
    public $children = [];

    public function __construct($name = '', $path = '')
    {
        $this->name = $name;
        $this->path = $path;
    }

    public function addFile($parts, $fileInfo, $currentPath = '')
    {
        if (count($parts) === 0) {
            $this->files[] = $fileInfo;
            return;
        }
        $dir = array_shift($parts);
        if ($dir === '.') {
            $this->addFile($parts, $fileInfo, $currentPath);
            return;
        }

        $nextPath = $currentPath ? $currentPath . '/' . $dir : $dir;

        if (!isset($this->children[$dir])) {
            $this->children[$dir] = new DocTree($dir, $nextPath);
        }

        $this->children[$dir]->addFile($parts, $fileInfo, $nextPath);
    }
}
