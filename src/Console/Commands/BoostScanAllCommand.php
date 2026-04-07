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

final class BoostScanAllCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:scan:all')
            ->setDescription('Scan wire/core and wire/modules classes and print a summary');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Scan All');
        $projectRoot = getcwd();
        $cfg = (new ConfigReader($projectRoot))->read('.llms/docgen.yml');
        $excludes = $cfg['excludes'] ?? [];
        $doc = new DocIndex($projectRoot);

        $indexCore = $doc->scanPath('wire/core', $excludes);
        $output->writeln('Core Classes: '.count($indexCore));

        $indexModules = $doc->scanPath('wire/modules', $excludes);
        $output->writeln('Modules Classes: '.count($indexModules));

        $output->writeln('Total: '. (count($indexCore) + count($indexModules)));

        $n = 0;
        foreach ($indexCore as $fqcn => $meta) {
            $output->writeln('[core] '.$fqcn.' (methods: '.count($meta['methods'] ?? []).')');
            $n++;
            if ($n >= 5) break;
        }
        foreach ($indexModules as $fqcn => $meta) {
            $output->writeln('[mod] '.$fqcn.' (methods: '.count($meta['methods'] ?? []).')');
            $n++;
            if ($n >= 10) break;
        }
        info('Scan complete');
        outro('Done');
        return Command::SUCCESS;
    }
}
