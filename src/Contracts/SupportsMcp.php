<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Contracts;

interface SupportsMcp
{
    public function getPhpPath(): string;

    public function getWirePath(string $projectRoot): string;

    public function installMcp(string $key, string $command, array $args = [], array $env = [], string $cwd = ''): bool;
}
