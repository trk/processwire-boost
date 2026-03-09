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
        $this->setName('boost:assert')->setDescription('Validate quality of guides, blueprints and skills for AI agents.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Assertions');
        $root = getcwd();
        $errors = [];

        $gPath = $root.'/.ai/guidelines/pw_core.md';
        if (!is_file($gPath)) {
            $errors[] = 'Missing .ai/guidelines/pw_core.md';
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

        $bpDir = $root.'/.ai/blueprints/pw_core';
        if (!is_dir($bpDir)) {
            $errors[] = 'Missing .ai/blueprints/pw_core directory';
        } else {
            $files = glob($bpDir.'/*.json') ?: [];
            if (count($files) < 20) $errors[] = 'Blueprint coverage too low (<20 files)';
            $checkSome = array_slice($files, 0, 10);
            foreach ($checkSome as $f) {
                $json = json_decode(file_get_contents($f) ?: '', true);
                if (!is_array($json)) { $errors[] = "Invalid JSON: ".basename($f); continue; }
                foreach (['id','name','kind','summary','className','sourcePath','methods'] as $k) {
                    if (!array_key_exists($k, $json)) $errors[] = basename($f).": missing key {$k}";
                }
                if (!is_array($json['methods'] ?? null)) $errors[] = basename($f).": methods not array";
                if (!array_key_exists('since',$json)) $errors[] = basename($f).": missing top-level since";
                if (isset($json['methods'][0])) {
                    $m = $json['methods'][0];
                    if (!array_key_exists('since',$m)) $errors[] = basename($f).": first method missing since";
                }
            }
        }

        $skillsDir = $root.'/.ai/skills/pw_core';
        if (!is_dir($skillsDir)) {
            $errors[] = 'Missing .ai/skills/pw_core directory';
        } else {
            $skillFiles = glob($skillsDir.'/*/SKILL.md') ?: [];
            if (!$skillFiles) $errors[] = 'No SKILL.md files found';
            foreach (array_slice($skillFiles, 0, 6) as $sf) {
                $s = file_get_contents($sf) ?: '';
                $sections = ['# ','## Blueprints','## Steps','## Request','## Response','## Example','## Compatibility'];
                foreach ($sections as $sec) {
                    if (strpos($s, $sec) === false) $errors[] = basename(dirname($sf)).": missing section {$sec}";
                }
                $bad = ['Ç','Ğ','Ş','İ','Ü','Ö','Örnek','Adımlar','İstek','Yanıt'];
                foreach ($bad as $b) { if (strpos($s, $b) !== false) { $errors[] = basename(dirname($sf)).": non-English token {$b}"; break; } }
                if (preg_match_all('#- \.ai/blueprints/pw_core/([^/\s]+)\.json#', $s, $m)) {
                    foreach ($m[1] as $bp) {
                        if (!is_file($bpDir.'/'.$bp.'.json')) $errors[] = basename(dirname($sf)).": missing referenced blueprint {$bp}.json";
                    }
                }
            }
        }

        if ($errors) {
            foreach ($errors as $e) { $output->writeln("<error>{$e}</error>"); }
            return Command::FAILURE;
        }

        note('All assertions passed for guide, blueprints, and skills.');
        info('Quality looks good for AI agents.');
        outro('Done');
        return Command::SUCCESS;
    }
}

