<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\ConfigReader;
use Totoglu\ProcessWire\Boost\DocIndex;
use Totoglu\ProcessWire\Boost\GuideBuilder;
use Totoglu\ProcessWire\Boost\GuidelineBuilder;
use Totoglu\ProcessWire\Boost\SkillBuilder;
use Totoglu\ProcessWire\Boost\MasterArchitectNotes;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;

final class BoostBuildCoreCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('boost:build:core')->setDescription('Build guidelines and skills for wire/core');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Build Core');
        $projectRoot = getcwd();
        $cfg = (new ConfigReader($projectRoot))->read('.llms/docgen.yml');
        $excludes = $cfg['excludes'] ?? [];

        $docIndex = new DocIndex($projectRoot);
        spin(function () use (&$index, $docIndex, $projectRoot, $excludes) {
            $index = $docIndex->scanPath('wire/core', $excludes);
        }, 'Scanning wire/core...');
        info('Core scanned');

        spin(function () use ($index, $docIndex, $projectRoot) {
            $notes = new MasterArchitectNotes($projectRoot);
            $notes->setData(
                $index,
                $docIndex->getDiscoveredTags(),
                $docIndex->getSynthesizedMethods(),
                $docIndex->getClassRelationships()
            );
            $content = $notes->generate();
            $dir = $projectRoot . '/.llms';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/master_architect_notes_core.md', $content);
        }, 'Generating Master Architect Notes...');
        info('Master Architect Notes generated');

        spin(function () use ($index, $projectRoot) {
            $gb = new GuideBuilder($projectRoot);
            $gb->build($index, $projectRoot.'/.llms/guidelines/pw_core.md');
        }, 'Building core guide...');
        info('Core guide built');

        spin(function () use ($index, $projectRoot) {
            $glb = new GuidelineBuilder($projectRoot);
            $glb->build($index, $projectRoot.'/.llms/guidelines/pw_core_guidelines.md');
        }, 'Building core guidelines...');
        info('Core guidelines built');

        spin(function () use ($index, $projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildSkillsFromGroups($index, $projectRoot . '/.llms/skills/pw_core');
        }, 'Building core skills from groups...');
        info('Core group skills built');

        spin(function () use ($projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildFromSources(null);
        }, 'Building core skills from sources...');
        info('Core source skills built');

        outro('Done');
        return Command::SUCCESS;
    }
}
