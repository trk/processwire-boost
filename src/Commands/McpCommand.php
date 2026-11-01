<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\Mcp\McpServer;

final class McpCommand extends Command
{
	protected function configure(): void
	{
		$this
			->setName('boost:mcp')
			->setDescription('Start the ProcessWire MCP server (JSON-RPC over stdio).');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$server = new McpServer();
		$buffer = '';

		while (!feof(STDIN)) {
			$chunk = fgets(STDIN);
			if ($chunk === false) {
				break;
			}
			$buffer .= $chunk;

			while (str_contains($buffer, "\n")) {
				[$line, $buffer] = explode("\n", $buffer, 2);
				$line = trim($line);
				if ($line === '') {
					continue;
				}

				$request = json_decode($line, true);
				if (!is_array($request)) {
					$this->writePayload($server->parseError());
					continue;
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
		}

		return Command::SUCCESS;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function writePayload(array $payload): void
	{
		$encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		if (is_string($encoded)) {
			fwrite(STDOUT, $encoded . PHP_EOL);
		}
	}
}
