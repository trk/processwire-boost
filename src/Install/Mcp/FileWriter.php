<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Mcp;

final class FileWriter
{
    private string $configKey = 'mcpServers';
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
            if ($v !== [] && $v !== null && $v !== '') {
                $filtered[$k] = $v;
            }
        }
        $this->serversToAdd[$key] = $filtered;
        return $this;
    }
    public function save(): bool
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if ($this->shouldWriteNew()) {
            return $this->createNewFile();
        }
        $content = @file_get_contents($this->filePath) ?: '';
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return false;
        }
        $this->addServersToConfig($decoded);
        return $this->writeJsonConfig($decoded);
    }
    private function shouldWriteNew(): bool
    {
        if (!file_exists($this->filePath)) return true;
        return filesize($this->filePath) < 3;
    }
    private function createNewFile(): bool
    {
        $config = $this->baseConfig;
        if (!isset($config[$this->configKey]) || !is_array($config[$this->configKey])) {
            $config[$this->configKey] = [];
        }
        $this->addServersToConfig($config);
        return $this->writeJsonConfig($config);
    }
    private function addServersToConfig(array &$config): void
    {
        $keys = explode('.', $this->configKey);
        $ref =& $config;
        foreach ($keys as $i => $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref =& $ref[$k];
        }
        foreach ($this->serversToAdd as $key => $serverConfig) {
            $ref[$key] = $serverConfig;
        }
    }
    private function writeJsonConfig(array $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;
        $json = str_replace("\r\n", "\n", $json);
        return file_put_contents($this->filePath, $json) !== false;
    }
}
