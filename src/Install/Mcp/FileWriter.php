<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Mcp;

final class FileWriter
{
	private string $configKey = 'mcpServers';

	/** @var array<string, array<string, mixed>> */
	private array $serversToAdd = [];

	/** @var list<string> */
	private array $serversToRemove = [];

	/**
	 * @param array<string, mixed> $baseConfig
	 */
	public function __construct(
		private readonly string $filePath,
		private readonly array $baseConfig = []
	) {
	}

	public function configKey(string $key): self
	{
		$this->configKey = $key;
		return $this;
	}

	/**
	 * @param array<string, mixed> $config
	 */
	public function addServerConfig(string $key, array $config): self
	{
		$filtered = [];
		foreach ($config as $k => $v) {
			if ($k === 'env' && is_array($v)) {
				$ev = [];
				foreach ($v as $ek => $evv) {
					if ($evv !== '' && $evv !== null) {
						$ev[$ek] = $evv;
					}
				}
				if ($ev !== []) {
					$filtered[$k] = $ev;
				}
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
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		if ($this->isToml()) {
			return $this->saveToml();
		}
		if ($this->shouldWriteNew()) {
			return $this->createNewJsonFile();
		}
		$content = @file_get_contents($this->filePath) ?: '';
		$decoded = json_decode($content, true);
		if (!is_array($decoded)) {
			@trigger_error(sprintf(
				'ProcessWire Boost: skipping MCP JSON merge for "%s"; existing file is not valid JSON.',
				$this->filePath
			), E_USER_WARNING);
			return false;
		}
		$this->addServersToConfig($decoded);
		return $this->writeJsonConfig($decoded);
	}

	public function remove(string $key): bool
	{
		$this->serversToRemove = [$key];
		if (!file_exists($this->filePath)) {
			return true;
		}
		if ($this->isToml()) {
			return $this->removeToml($key);
		}
		$content = @file_get_contents($this->filePath) ?: '';
		$decoded = json_decode($content, true);
		if (!is_array($decoded)) {
			return false;
		}
		$this->removeServersFromConfig($decoded);
		return $this->writeJsonConfig($decoded);
	}

	private function isToml(): bool
	{
		return str_ends_with($this->filePath, '.toml');
	}

	private function shouldWriteNew(): bool
	{
		if (!file_exists($this->filePath)) {
			return true;
		}
		return filesize($this->filePath) < 3;
	}

	private function createNewJsonFile(): bool
	{
		$config = $this->baseConfig;
		$this->addServersToConfig($config);
		return $this->writeJsonConfig($config);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function addServersToConfig(array &$config): void
	{
		if (!isset($config[$this->configKey]) || !is_array($config[$this->configKey])) {
			$config[$this->configKey] = [];
		}

		foreach ($this->serversToAdd as $key => $serverConfig) {
			$config[$this->configKey][$key] = $serverConfig;
		}
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function removeServersFromConfig(array &$config): void
	{
		if (isset($config[$this->configKey]) && is_array($config[$this->configKey])) {
			foreach ($this->serversToRemove as $key) {
				unset($config[$this->configKey][$key]);
			}
		}
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function writeJsonConfig(array $config): bool
	{
		$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return false;
		}
		$json = str_replace("\r\n", "\n", $json);
		return file_put_contents($this->filePath, $json) !== false;
	}

	private function saveToml(): bool
	{
		if ($this->shouldWriteNew()) {
			return $this->createNewTomlFile();
		}
		$content = @file_get_contents($this->filePath) ?: '';
		foreach ($this->serversToAdd as $key => $config) {
			if ($this->tomlServerExists($content, $key)) {
				$content = $this->removeExistingTomlServer($content, $key);
			}
			$trimmed = rtrim($content);
			$sep = $trimmed === '' ? '' : "\n\n";
			$content = $trimmed . $sep . $this->buildServerToml($key, $config) . "\n";
		}
		return file_put_contents($this->filePath, $content) !== false;
	}

	private function removeToml(string $key): bool
	{
		$content = @file_get_contents($this->filePath) ?: '';
		if (!$this->tomlServerExists($content, $key)) {
			return true;
		}
		$content = $this->removeExistingTomlServer($content, $key);
		return file_put_contents($this->filePath, ltrim($content)) !== false;
	}

	private function createNewTomlFile(): bool
	{
		$lines = [];
		foreach ($this->baseConfig as $k => $v) {
			if (!is_array($v)) {
				$lines[] = $k . ' = ' . $this->formatTomlValue($v);
			}
		}
		foreach ($this->serversToAdd as $key => $cfg) {
			if ($lines !== []) {
				$lines[] = '';
			}
			$lines[] = $this->buildServerToml($key, $cfg);
		}
		return file_put_contents($this->filePath, implode("\n", $lines) . "\n") !== false;
	}

	private function tomlServerExists(string $content, string $key): bool
	{
		return (bool) preg_match('/^\[' . preg_quote($this->configKey, '/') . '\.' . preg_quote($key, '/') . '\]/m', $content);
	}

	private function removeExistingTomlServer(string $content, string $key): string
	{
		$ck = preg_quote($this->configKey, '/');
		$ek = preg_quote($key, '/');
		$envPattern = '/(\r?\n)*\[' . $ck . '\.' . $ek . '\.env\].*?(?=\r?\n\[|$)/s';
		$content = preg_replace($envPattern, '', $content) ?? $content;
		$mainPattern = '/(\r?\n)*\[' . $ck . '\.' . $ek . '\].*?(?=\r?\n\[|$)/s';
		return preg_replace($mainPattern, '', $content) ?? $content;
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function buildServerToml(string $key, array $config): string
	{
		$lines = [];
		$lines[] = '[' . $this->configKey . '.' . $key . ']';
		foreach ($config as $field => $value) {
			if ($field === 'env' && is_array($value)) {
				continue;
			}
			$lines[] = $field . ' = ' . $this->formatTomlValue($value);
		}
		if (isset($config['env']) && is_array($config['env']) && $config['env'] !== []) {
			$lines[] = '';
			$lines[] = '[' . $this->configKey . '.' . $key . '.env]';
			foreach ($config['env'] as $ek => $ev) {
				$lines[] = $ek . ' = ' . $this->formatTomlValue($ev);
			}
		}
		return implode("\n", $lines);
	}

	private function formatTomlValue(mixed $value): string
	{
		if (is_string($value)) {
			return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']) . '"';
		}
		if (is_array($value)) {
			$items = array_map([$this, 'formatTomlValue'], $value);
			return '[' . implode(', ', $items) . ']';
		}
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		return (string) $value;
	}
}
