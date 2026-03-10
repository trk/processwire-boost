<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\DocIndex;
use Totoglu\ProcessWire\Boost\ConfigReader;
use Totoglu\ProcessWire\Boost\BlueprintBuilder;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class BoostBuildBlueprintsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:build:blueprints')
            ->setDescription('Generate blueprint JSON files from core classes')
            ->addOption('assert', null, InputOption::VALUE_NONE, 'Run basic assertions after build');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Blueprints Builder');
        $root = getcwd();
        $cfg = (new ConfigReader($root))->read('.ai/docgen.yml');
        $includes = $cfg['includes'] ?? ['wire/core','wire/modules','site/modules'];
        $excludes = $cfg['excludes'] ?? [];
        $doc = new DocIndex($root);
        $index = $doc->scanPaths($includes, $excludes);
        $builder = new BlueprintBuilder($root);
        $target = $root.'/.ai/blueprints/pw_core';
        spin(fn() => $builder->build($index, $target), 'Generating blueprints...');
        info('Blueprints generated');
        if ($input->getOption('assert')) {
            $this->runAssertions($target, $output);
        }
        outro('Done');
        return Command::SUCCESS;
    }

    private function runAssertions(string $target, OutputInterface $output): void
    {
        if (!is_dir($target)) {
            $output->writeln('<error>Blueprint directory missing</error>');
            return;
        }
        $files = glob($target.'/*.json') ?: [];
        if (count($files) < 10) {
            $output->writeln('<error>Insufficient blueprint count</error>');
        } else {
            note('Assertions passed');
        }
    }
}
