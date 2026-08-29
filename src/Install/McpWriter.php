<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install;

use RuntimeException;
use Totoglu\Console\Boost\Contracts\SupportsMcp;

final class McpWriter
{
    public function __construct(
        private readonly SupportsMcp $agent,
        private readonly string $projectRoot
    ) {
    }

    public function write(string $key = 'processwire'): void
    {
        $command = $this->agent->getPhpPath();
        $wire = $this->agent->getWirePath($this->projectRoot);
        $args = [$wire, 'boost:mcp'];

        if (!$this->agent->installMcp($key, $command, $args, [], $this->projectRoot)) {
            throw new RuntimeException(sprintf('Failed to install MCP configuration for %s.', $this->agent::class));
        }
    }

    public function remove(string $key = 'processwire'): void
    {
        $this->agent->uninstallMcp($key);
    }
}
