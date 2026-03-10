<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\DocIndex;
use Totoglu\ProcessWire\Boost\ConfigReader;
use Totoglu\ProcessWire\Boost\GuideBuilder;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class BoostBuildGuidesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:build:guides')
            ->setDescription('Generate ProcessWire core guides from local PHPDoc')
            ->addOption('dry', null, InputOption::VALUE_NONE, 'Preview without writing')
            ->addOption('assert', null, InputOption::VALUE_NONE, 'Run basic assertions after build');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Guide Builder');
        $projectRoot = getcwd();
        $cfg = (new ConfigReader($projectRoot))->read('.ai/docgen.yml');
        $includes = $cfg['includes'] ?? ['wire/core','wire/modules','site/modules'];
        $excludes = $cfg['excludes'] ?? [];
        $doc = new DocIndex($projectRoot);
        $index = $doc->scanPaths($includes, $excludes);
        $target = $projectRoot . '/.ai/guidelines/pw_core.md';
        $isDry = (bool)$input->getOption('dry');
        if ($isDry) {
            $count = count($index);
            $output->writeln("Classes: {$count}");
            foreach (array_slice(array_keys($index), 0, 5) as $k) {
                $output->writeln("- {$k}");
            }
            return Command::SUCCESS;
        }
        $builder = new GuideBuilder($projectRoot);
        spin(fn() => $builder->build($index, $target), 'Generating guide...');
        info('pw_core.md generated');
        if ($input->getOption('assert')) {
            $this->runAssertions($projectRoot, $output);
        }
        outro('Done');
        return Command::SUCCESS;
    }

    private function runAssertions(string $projectRoot, OutputInterface $output): void
    {
        $path = $projectRoot . '/.ai/guidelines/pw_core.md';
        if (!is_file($path)) {
            $output->writeln('<error>pw_core.md not found</error>');
            return;
        }
        $content = file_get_contents($path) ?: '';
        $checks = ['ProcessWire Core Guide','Principles','Common Tasks','Class Summaries'];
        $fail = [];
        foreach ($checks as $c) {
            if (strpos($content, $c) === false) {
                $fail[] = $c;
            }
        }
        if ($fail) {
            $output->writeln('<error>Assertion failed: ' . implode(', ', $fail) . '</error>');
        } else {
            note('Assertions passed');
        }
    }
}
