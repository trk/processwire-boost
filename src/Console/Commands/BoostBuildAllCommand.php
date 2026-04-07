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
use Totoglu\ProcessWire\Boost\SeedMerger;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

final class BoostBuildAllCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('boost:build:all')->setDescription('Build guidelines and skills for wire/core and wire/modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Build All');
        $projectRoot = getcwd();
        $cfg = (new ConfigReader($projectRoot))->read('.llms/docgen.yml');
        $excludes = $cfg['excludes'] ?? [];

        $docIndex = new DocIndex($projectRoot);

        $indexCore = [];
        spin(function () use (&$indexCore, $docIndex, $projectRoot, $excludes) {
            $indexCore = $docIndex->scanPath('wire/core', $excludes);
        }, 'Scanning wire/core...');
        info('Core scanned');

        $indexModules = [];
        spin(function () use (&$indexModules, $docIndex, $projectRoot, $excludes) {
            $indexModules = $docIndex->scanPath('wire/modules', $excludes);
        }, 'Scanning wire/modules...');
        info('Modules scanned');

        $indexAll = array_merge($indexCore, $indexModules);

        spin(function () use ($indexAll, $docIndex, $projectRoot) {
            $notes = new MasterArchitectNotes($projectRoot);
            $notes->setData(
                $indexAll,
                $docIndex->getDiscoveredTags(),
                $docIndex->getSynthesizedMethods(),
                $docIndex->getClassRelationships()
            );
            $content = $notes->generate();
            $dir = $projectRoot . '/.llms';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/master_architect_notes.md', $content);
        }, 'Generating Master Architect Notes...');
        info('Master Architect Notes generated');

        spin(function () use ($indexCore, $projectRoot) {
            $gb = new GuideBuilder($projectRoot);
            $gb->build($indexCore, $projectRoot.'/.llms/guidelines/pw_core.md');
        }, 'Building core guide...');
        info('Core guide built');

        spin(function () use ($indexModules, $projectRoot) {
            $gb = new GuideBuilder($projectRoot);
            $gb->build($indexModules, $projectRoot.'/.llms/guidelines/pw_modules.md');
        }, 'Building modules guide...');
        info('Modules guide built');

        spin(function () use ($indexCore, $projectRoot) {
            $glb = new GuidelineBuilder($projectRoot);
            $glb->build($indexCore, $projectRoot.'/.llms/guidelines/pw_core_guidelines.md');
        }, 'Building core guidelines...');
        info('Core guidelines built');

        spin(function () use ($indexModules, $projectRoot) {
            $glb = new GuidelineBuilder($projectRoot);
            $glb->build($indexModules, $projectRoot.'/.llms/guidelines/pw_modules_guidelines.md');
        }, 'Building modules guidelines...');
        info('Modules guidelines built');

        spin(function () use ($indexCore, $projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildSkillsFromGroups($indexCore, $projectRoot . '/.llms/skills/pw_core');
        }, 'Building core skills from groups...');
        info('Core group skills built');

        spin(function () use ($indexModules, $projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildSkillsFromGroups($indexModules, $projectRoot . '/.llms/skills/pw_modules');
        }, 'Building modules skills from groups...');
        info('Modules group skills built');

        spin(function () use ($projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildFromSources(null);
        }, 'Building skills from sources...');
        info('Source skills built');

        spin(function () use ($cfg, $projectRoot) {
            $sm = new SeedMerger($projectRoot);
            if (!empty($cfg['merge_seed_guides'])) {
                $sm->mergeGuides($cfg['seed_guides_dirs'] ?? []);
            }
            if (!empty($cfg['merge_seed_skills'])) {
                $sm->mergeSkillsFromTaxonomy($cfg['skills_taxonomy_path'] ?? null);
            }
        }, 'Merging seeds...');
        info('Seeds merged');

        outro('Done');
        return Command::SUCCESS;
    }
}
