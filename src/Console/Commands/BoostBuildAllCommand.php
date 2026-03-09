<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\ConfigReader;
use Totoglu\ProcessWire\Boost\DocIndex;
use Totoglu\ProcessWire\Boost\BlueprintBuilder;
use Totoglu\ProcessWire\Boost\GuideBuilder;
use Totoglu\ProcessWire\Boost\SkillBuilder;
use Totoglu\ProcessWire\Boost\SeedMerger;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

final class BoostBuildAllCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('boost:build-all')->setDescription('Build blueprints, guides and skills using PHP builders and config.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Build All');
        $projectRoot = getcwd();
        $cfg = (new ConfigReader($projectRoot))->read('.ai/docgen.yml');
        $includes = $cfg['includes'] ?? ['wire/core','wire/modules','site/modules'];
        $excludes = $cfg['excludes'] ?? [];

        $index = [];
        spin(function () use (&$index, $projectRoot, $includes, $excludes) {
            $doc = new DocIndex($projectRoot);
            $index = $doc->scanPaths($includes, $excludes);
        }, 'Scanning sources...');

        spin(function () use ($index, $projectRoot) {
            $bb = new BlueprintBuilder($projectRoot);
            $bb->build($index, $projectRoot.'/.ai/blueprints/pw_core');
        }, 'Building blueprints...');
        info('Blueprints built');

        spin(function () use ($index, $projectRoot) {
            $gb = new GuideBuilder($projectRoot);
            $gb->build($index, $projectRoot.'/.ai/guidelines/pw_core.md');
        }, 'Building guide...');
        info('Guide built');

        spin(function () use ($projectRoot) {
            $sb = new SkillBuilder($projectRoot);
            $sb->buildFromSources(null);
        }, 'Building skills...');
        info('Skills built');

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
