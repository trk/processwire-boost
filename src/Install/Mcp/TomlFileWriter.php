<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Mcp;

final class TomlFileWriter
{
    private string $configKey = 'mcp_servers';
    private array $serversToAdd = [];
    public function __construct(private readonly string $filePath, private readonly array $baseConfig = [])
    {
    }
    public function configKey(string $key): self
    {
        $this->configKey = $key;
        return $this;
    }
    public function addServerConfig(string $key, array $config): self
    {
        $filtered = [];
        foreach ($config as $k => $v) {
            if ($k === 'env' && is_array($v)) {
                $ev = [];
                foreach ($v as $ek => $evv) {
                    if ($evv !== '' && $evv !== null) $ev[$ek] = $evv;
                }
                if ($ev !== []) $filtered[$k] = $ev;
            } elseif ($v !== [] && $v !== null && $v !== '') {
                $filtered[$k] = $v;
            }
        }
        $this->serversToAdd[$key] = $filtered;
        return $this;
    }
    public function save(): bool
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if ($this->shouldWriteNew()) {
            return $this->createNewFile();
        }
        $content = @file_get_contents($this->filePath) ?: '';
        foreach ($this->serversToAdd as $key => $config) {
            if ($this->serverExists($content, $key)) {
                $content = $this->removeExistingServer($content, $key);
            }
            $trimmed = rtrim($content);
            $sep = $trimmed === '' ? '' : "\n\n";
            $content = $trimmed . $sep . $this->buildServerToml($key, $config) . "\n";
        }
        return file_put_contents($this->filePath, $content) !== false;
    }
    private function shouldWriteNew(): bool
    {
        if (!file_exists($this->filePath)) return true;
        return filesize($this->filePath) < 3;
    }
    private function createNewFile(): bool
    {
        $lines = [];
        foreach ($this->baseConfig as $k => $v) {
            if (!is_array($v)) {
                $lines[] = $k . ' = ' . $this->formatValue($v);
            }
        }
        foreach ($this->serversToAdd as $key => $cfg) {
            if ($lines !== []) $lines[] = '';
            $lines[] = $this->buildServerToml($key, $cfg);
        }
        return file_put_contents($this->filePath, implode("\n", $lines) . "\n") !== false;
    }
    private function serverExists(string $content, string $key): bool
    {
        return (bool) preg_match('/^\[' . preg_quote($this->configKey, '/') . '\.' . preg_quote($key, '/') . '\]/m', $content);
    }
    private function removeExistingServer(string $content, string $key): string
    {
        $ck = preg_quote($this->configKey, '/');
        $ek = preg_quote($key, '/');
        $envPattern = '/(\r?\n)*\['.$ck.'\.'.$ek.'\.env\].*?(?=\r?\n\[|$)/s';
        $content = preg_replace($envPattern, '', $content) ?? $content;
        $mainPattern = '/(\r?\n)*\['.$ck.'\.'.$ek.'\].*?(?=\r?\n\[|$)/s';
        return preg_replace($mainPattern, '', $content) ?? $content;
    }
    private function buildServerToml(string $key, array $config): string
    {
        $lines = [];
        $lines[] = '['.$this->configKey.'.'.$key.']';
        foreach ($config as $field => $value) {
            if ($field === 'env' && is_array($value)) continue;
            $lines[] = $field.' = '.$this->formatValue($value);
        }
        if (isset($config['env']) && is_array($config['env']) && $config['env'] !== []) {
            $lines[] = '';
            $lines[] = '['.$this->configKey.'.'.$key.'.env]';
            foreach ($config['env'] as $ek => $ev) {
                $lines[] = $ek.' = '.$this->formatValue($ev);
            }
        }
        return implode("\n", $lines);
    }
    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']).'"';
        }
        if (is_array($value)) {
            $items = array_map([$this,'formatValue'], $value);
            return '['.implode(', ', $items).']';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }
}

