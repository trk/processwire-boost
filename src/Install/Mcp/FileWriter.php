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
			if (in_array($k, ['env', 'environment'], true) && is_array($v)) {
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
		if (is_link($this->filePath) || is_link($dir)) {
			return false;
		}
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
		$decoded = json_decode($content, false);
		if (!($decoded instanceof \stdClass)) {
			@trigger_error(sprintf(
				'ProcessWire Boost: skipping MCP JSON merge for "%s"; existing file is not valid JSON.',
				$this->filePath
			), E_USER_WARNING);
			return false;
		}
		$this->addServersToObject($decoded);
		return $this->writeJsonObject($decoded);
	}

	public function remove(string $key): bool
	{
		$this->serversToRemove = [$key];
		if (is_link($this->filePath) || is_link(dirname($this->filePath))) {
			return false;
		}
		if (!file_exists($this->filePath)) {
			return true;
		}
		if ($this->isToml()) {
			return $this->removeToml($key);
		}
		$content = @file_get_contents($this->filePath) ?: '';
		$decoded = json_decode($content, false);
		if (!($decoded instanceof \stdClass)) {
			return false;
		}
		$this->removeServersFromObject($decoded);
		return $this->writeJsonObject($decoded);
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
		$root = new \stdClass();
		foreach ($this->baseConfig as $k => $v) {
			$root->{$k} = $k === $this->configKey ? new \stdClass() : self::toJsonValue($v);
		}
		$this->addServersToObject($root);
		return $this->writeJsonObject($root);
	}

	private function addServersToObject(\stdClass $config): void
	{
		$key = $this->configKey;
		if (!isset($config->{$key}) || !($config->{$key} instanceof \stdClass)) {
			$config->{$key} = new \stdClass();
		}

		/** @var \stdClass $container */
		$container = $config->{$key};
		foreach ($this->serversToAdd as $serverKey => $serverConfig) {
			$container->{$serverKey} = self::toJsonValue($serverConfig);
		}
	}

	private function removeServersFromObject(\stdClass $config): void
	{
		$key = $this->configKey;
		if (!isset($config->{$key})) {
			return;
		}
		if (is_array($config->{$key})) {
			$config->{$key} = new \stdClass();
			return;
		}
		if ($config->{$key} instanceof \stdClass) {
			foreach ($this->serversToRemove as $serverKey) {
				unset($config->{$key}->{$serverKey});
			}
		}
	}

	private static function toJsonValue(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}
		if ($value === []) {
			return [];
		}
		if (array_is_list($value)) {
			return array_map(static fn(mixed $item): mixed => self::toJsonValue($item), $value);
		}

		$obj = new \stdClass();
		foreach ($value as $k => $v) {
			$obj->{(string) $k} = self::toJsonValue($v);
		}

		return $obj;
	}

	private function writeJsonObject(\stdClass $config): bool
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
		return (bool) preg_match('/^\h*\[\h*' . preg_quote($this->configKey, '/') . '\h*\.\h*' . preg_quote($key, '/') . '\h*\]/m', $content);
	}

	private function removeExistingTomlServer(string $content, string $key): string
	{
		$ck = preg_quote($this->configKey, '/');
		$ek = preg_quote($key, '/');
		$tomlKeySeg = '(?:[A-Za-z0-9_-]+|"(?:\\\\.|[^"\\\\\r\n])*"|\'[^\'\r\n]*\')';
		$nextHeader = '\r?\n\h*\[\[?\h*' . $tomlKeySeg . '(?:\h*\.\h*' . $tomlKeySeg . ')*\h*\]\]?\h*(?:#[^\r\n]*)?(?:\r?\n|\z)';
		$tablePattern = '/(?:\A|(?:\r?\n)+)\h*\[\h*' . $ck . '\h*\.\h*' . $ek . '(?:\h*\.\h*' . $tomlKeySeg . ')*\h*\]\h*(?:#[^\r\n]*)?(?:\r?\n.*?)?(?=' . $nextHeader . '|\z)/s';
		return preg_replace($tablePattern, '', $content) ?? $content;
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
