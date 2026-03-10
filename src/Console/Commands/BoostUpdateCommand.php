<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

final class BoostUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('boost:update')->setDescription('Update the ProcessWire Boost guidelines & skills to the latest guidance');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Update');

        $projectRoot = getcwd();
        $configPath = $projectRoot . '/.ai/boost.json';

        if (!file_exists($configPath)) {
            $output->writeln('<error>Please run boost:install first.</error>');
            return Command::FAILURE;
        }

        $config = json_decode(file_get_contents($configPath) ?: '', true);
        if (empty($config['agents'])) {
            $output->writeln('<error>No agents configured. Please run boost:install first.</error>');
            return Command::FAILURE;
        }

        $output->writeln('  Updating guidelines and skills to latest...');

        spin(function () use ($projectRoot) {
            $cmd = 'php vendor/bin/wire boost:build:all';
            $cwd = getcwd();
            chdir($projectRoot);
            exec($cmd);
            chdir($cwd);
        }, 'Building latest guidelines and skills...');

        info('Boost guidelines and skills updated successfully.');

        outro('Done');
        return Command::SUCCESS;
    }
}