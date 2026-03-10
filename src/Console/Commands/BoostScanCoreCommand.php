<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\DocIndex;
use Totoglu\ProcessWire\Boost\ConfigReader;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\info;

final class BoostScanCoreCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:scan:core')
            ->setDescription('Scan wire/core classes and print a summary');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Scan Core');
        $projectRoot = getcwd();
        $cfg = (new ConfigReader($projectRoot))->read('.ai/docgen.yml');
        $excludes = $cfg['excludes'] ?? [];
        $doc = new DocIndex($projectRoot);
        $index = $doc->scanPath('wire/core', $excludes);
        $output->writeln('Classes: '.count($index));
        $n = 0;
        foreach ($index as $fqcn => $meta) {
            $output->writeln('- '.$fqcn.' (methods: '.count($meta['methods'] ?? []).')');
            $n++;
            if ($n >= 10) break;
        }
        info('Scan complete');
        outro('Done');
        return Command::SUCCESS;
    }
}
