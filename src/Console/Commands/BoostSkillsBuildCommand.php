<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\SkillBuilder;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class BoostSkillsBuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:skills:build')
            ->setDescription('Generate skill files from local core')
            ->addOption('select', null, InputOption::VALUE_OPTIONAL, 'Comma-separated skill keys')
            ->addOption('assert', null, InputOption::VALUE_NONE, 'Run basic assertions after build');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Skill Builder');
        $projectRoot = getcwd();
        $builder = new SkillBuilder($projectRoot);
        $selectOpt = (string)($input->getOption('select') ?? '');
        $select = null;
        if ($selectOpt !== '') {
            $select = array_filter(array_map('trim', explode(',', $selectOpt)));
        }
        spin(fn() => $builder->buildFromSources($select), 'Generating skills...');
        info('Skills generated');
        if ($input->getOption('assert')) {
            $this->runAssertions($projectRoot, $select, $output);
        }
        outro('Done');
        return Command::SUCCESS;
    }

    private function runAssertions(string $root, ?array $select, OutputInterface $output): void
    {
        $targets = $select ?: null;
        $missing = [];
        if ($targets) {
            foreach ($targets as $k) {
                $path = $root . '/.ai/skills/pw_core/' . $k . '/SKILL.md';
                if (!is_file($path)) {
                    $missing[] = $k;
                }
            }
        }
        if ($missing) {
            $output->writeln('<error>Missing skills: ' . implode(', ', $missing) . '</error>');
        } else {
            note('Assertions passed');
        }
    }
}
