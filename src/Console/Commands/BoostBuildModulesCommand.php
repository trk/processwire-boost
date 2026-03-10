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

final class BoostBuildModulesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('boost:build:modules')->setDescription('Build guidelines and skills for wire/modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Build Modules');
        $projectRoot = getcwd();
        $cfg = (new ConfigReader($projectRoot))->read('.ai/docgen.yml');
        $excludes = $cfg['excludes'] ?? [];

        $docIndex = new DocIndex($projectRoot);
        spin(function () use (&$index, $docIndex, $projectRoot, $excludes) {
            $index = $docIndex->scanPath('wire/modules', $excludes);
        }, 'Scanning wire/modules...');
        info('Modules scanned');

        spin(function () use ($index, $docIndex, $projectRoot) {
            $notes = new MasterArchitectNotes($projectRoot);
            $notes->setData(
                $index,
                $docIndex->getDiscoveredTags(),
                $docIndex->getSynthesizedMethods(),
                $docIndex->getClassRelationships()
            );
            $content = $notes->generate();
            $dir = $projectRoot . '/.ai';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/master_architect_notes_modules.md', $content);
        }, 'Generating Master Architect Notes...');
        info('Master Architect Notes generated');

        spin(function () use ($index, $projectRoot) {
            $gb = new GuideBuilder($projectRoot);
            $gb->build($index, $projectRoot.'/.ai/guidelines/pw_modules.md');
        }, 'Building modules guide...');
        info('Modules guide built');

        spin(function () use ($index, $projectRoot) {
            $glb = new GuidelineBuilder($projectRoot);
            $glb->build($index, $projectRoot.'/.ai/guidelines/pw_modules_guidelines.md');
        }, 'Building modules guidelines...');
        info('Modules guidelines built');

        spin(function () use ($index, $projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildSkillsFromGroups($index, $projectRoot . '/.ai/skills/pw_modules');
        }, 'Building modules skills from groups...');
        info('Modules group skills built');

        spin(function () use ($projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildFromSources(null);
        }, 'Building modules skills from sources...');
        info('Modules source skills built');

        outro('Done');
        return Command::SUCCESS;
    }
}
