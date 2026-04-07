<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\info;

final class BoostAssertCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('boost:assert')->setDescription('Validate quality of guides and skills for AI agents.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Assertions');
        $root = getcwd();
        $errors = [];

        $gPath = $root.'/.llms/guidelines/pw_core.md';
        if (!is_file($gPath)) {
            $errors[] = 'Missing .llms/guidelines/pw_core.md';
        } else {
            $g = file_get_contents($gPath) ?: '';
            $must = ['# ProcessWire Core Guide','## Principles','## Common Tasks','## Class Summaries'];
            foreach ($must as $m) {
                if (strpos($g, $m) === false) $errors[] = "Guide missing section: {$m}";
            }
            $bad = ['Ç','Ğ','Ş','İ','Ü','Ö','Kılavuz','Sık Görevler','Örnek','Adımlar','İstek','Yanıt'];
            foreach ($bad as $b) {
                if (strpos($g, $b) !== false) { $errors[] = "Guide contains non-English token: {$b}"; break; }
            }
        }

        $skillsDir = $root.'/.llms/skills/pw_core';
        if (!is_dir($skillsDir)) {
            $errors[] = 'Missing .llms/skills/pw_core directory';
        } else {
            $skillFiles = glob($skillsDir.'/*/SKILL.md') ?: [];
            if (!$skillFiles) $errors[] = 'No SKILL.md files found';
            foreach (array_slice($skillFiles, 0, 6) as $sf) {
                $s = file_get_contents($sf) ?: '';
                $sections = ['# ','## Overview','## Methods'];
                foreach ($sections as $sec) {
                    if (strpos($s, $sec) === false) $errors[] = basename(dirname($sf)).": missing section {$sec}";
                }
                $bad = ['Ç','Ğ','Ş','İ','Ü','Ö','Örnek','Adımlar','İstek','Yanıt'];
                foreach ($bad as $b) { if (strpos($s, $b) !== false) { $errors[] = basename(dirname($sf)).": non-English token {$b}"; break; } }
            }
        }

        if ($errors) {
            foreach ($errors as $e) { $output->writeln("<error>{$e}</error>"); }
            return Command::FAILURE;
        }

        note('All assertions passed for guide and skills.');
        info('Quality looks good for AI agents.');
        outro('Done');
        return Command::SUCCESS;
    }
}

