<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\Mcp\McpServer;

final class McpCommand extends Command
{
	private const MAX_LINE_BYTES = 1048576;
	private const MAX_JSON_DEPTH = 64;

	protected function configure(): void
	{
		$this
			->setName('boost:mcp')
			->setDescription('Start the ProcessWire MCP server (JSON-RPC over stdio).');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		@ini_set('display_errors', '0');

		$server = new McpServer();
		$buffer = '';
		$discardingOversizedLine = false;

		while (!feof(STDIN)) {
			$chunk = fgets(STDIN, 65536);
			if ($chunk === false) {
				break;
			}

			if ($discardingOversizedLine) {
				$newlinePos = strpos($chunk, "\n");
				if ($newlinePos === false) {
					continue;
				}
				$discardingOversizedLine = false;
				$chunk = substr($chunk, $newlinePos + 1);
			}

			$buffer .= $chunk;

			if (strlen($buffer) > self::MAX_LINE_BYTES && !str_contains($buffer, "\n")) {
				$buffer = '';
				$discardingOversizedLine = true;
				$this->writePayload($server->parseError());
				continue;
			}

			while (str_contains($buffer, "\n")) {
				[$line, $buffer] = explode("\n", $buffer, 2);
				$this->processLine($server, $line);
			}
		}

		if (!$discardingOversizedLine && trim($buffer) !== '') {
			$this->processLine($server, $buffer);
		}

		return Command::SUCCESS;
	}

	private function processLine(McpServer $server, string $rawLine): void
	{
		if (strlen($rawLine) > self::MAX_LINE_BYTES) {
			$this->writePayload($server->parseError());
			return;
		}

		$line = trim($rawLine);
		if ($line === '') {
			return;
		}

		$request = json_decode($line, true, self::MAX_JSON_DEPTH);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->writePayload($server->parseError());
			return;
		}
		if (!is_array($request) || $request === [] || array_is_list($request)) {
			$this->writePayload($server->invalidRequest());
			return;
		}

		$response = null;
		ob_start();
		try {
			$response = $server->handleRequest($request);
		} finally {
			ob_end_clean();
		}

		if ($response !== null) {
			$this->writePayload($response);
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function writePayload(array $payload): void
	{
		$encoded = json_encode(
			$payload,
			JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		if (is_string($encoded)) {
			fwrite(STDOUT, $encoded . PHP_EOL);
			return;
		}

		$rawId = $payload['id'] ?? null;
		$safeId = is_string($rawId) || is_int($rawId) || (is_float($rawId) && is_finite($rawId)) ? $rawId : null;
		$fallback = json_encode([
			'jsonrpc' => '2.0',
			'id' => $safeId,
			'error' => ['code' => -32603, 'message' => 'Internal response serialization error'],
		], JSON_UNESCAPED_SLASHES);
		if (is_string($fallback)) {
			fwrite(STDOUT, $fallback . PHP_EOL);
		}
	}
}
