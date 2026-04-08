<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class BoostVersionCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:version')
            ->setDescription('Show processwire-boost version and related packages.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        $pkgs = [
            'trk/processwire-boost' => 'processwire-boost',
            'trk/processwire-console' => 'processwire-console',
            'processwire/processwire' => 'processwire/core',
        ];
        foreach ($pkgs as $name => $label) {
            $rows[] = [$label, $this->getVersion($name)];
        }
        $io->table(['Package', 'Version'], $rows);
        return Command::SUCCESS;
    }

    private function getVersion(string $package): string
    {
        try {
            if (class_exists(\Composer\InstalledVersions::class)) {
                /** @var class-string $iv */
                $iv = \Composer\InstalledVersions::class;
                if (method_exists($iv, 'isInstalled') && $iv::isInstalled($package)) {
                    return (string)($iv::getPrettyVersion($package) ?? $iv::getVersion($package) ?? 'unknown');
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $root = \ProcessWire\wire('config')->paths->root;
        $path = $root . 'vendor/composer/installed.json';
        if (is_file($path)) {
            try {
                $json = json_decode((string)file_get_contents($path), true);
                $packages = $json['packages'] ?? $json ?? [];
                if (is_array($packages)) {
                    foreach ($packages as $p) {
                        if (($p['name'] ?? '') === $package) {
                            return (string)($p['pretty_version'] ?? $p['version'] ?? 'unknown');
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return 'unknown';
    }
}
