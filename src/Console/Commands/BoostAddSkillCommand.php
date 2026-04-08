<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\Skills\Remote\GitHubRepository;
use Totoglu\Console\Boost\Skills\Remote\GitHubSkillProvider;
use Totoglu\Console\Boost\Skills\Remote\RemoteSkill;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class BoostAddSkillCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:add-skill')
            ->setDescription('Add skills from a remote GitHub repository')
            ->addArgument('repo', InputArgument::OPTIONAL, 'GitHub repository (owner/repo or full URL)')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available skills')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Install all skills')
            ->addOption('skill', 's', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Specific skills to install')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing skills');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->displayHeader();

        $projectRoot = getcwd();

        $repo = $this->parseRepository($input);
        if ($repo === null) {
            return Command::FAILURE;
        }

        $provider = new GitHubSkillProvider($repo);

        $skills = [];
        try {
            spin(function () use ($provider, &$skills) {
                $skills = $provider->discoverSkills();
            }, "Fetching skills from {$repo->fullName()}...");
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if (empty($skills)) {
            $output->writeln('<error>No valid skills are found in the repository.</error>');
            return Command::FAILURE;
        }

        if ($input->getOption('list')) {
            note("Found " . count($skills) . " available skills:");
            foreach (array_keys($skills) as $skillName) {
                $output->writeln("  • {$skillName}");
            }
            return Command::SUCCESS;
        }

        $selectedSkills = $this->selectSkills($skills, $input);

        if (empty($selectedSkills)) {
            $output->writeln('<comment>No skills are selected.</comment>');
            return Command::SUCCESS;
        }

        $results = $this->downloadSkills($selectedSkills, $projectRoot, $input->getOption('force'));

        if (!empty($results['installed'])) {
            $output->writeln("\n  <info>Skills installed:</info>");
            foreach ($results['installed'] as $skillName) {
                $output->writeln("  • {$skillName}");
            }
        }

        if (!empty($results['failed'])) {
            $output->writeln("\n  <error>Some skills failed to install:</error>");
            foreach ($results['failed'] as $skillName => $reason) {
                $output->writeln("  • {$skillName}: {$reason}");
            }
        }

        outro('Done');
        return Command::SUCCESS;
    }

    private function displayHeader(): void
    {
        echo "\033[36m";
        echo "➕ ProcessWire Boost :: Add Skill\n";
        echo "\033[0m\n";
    }

    private function parseRepository(InputInterface $input): ?GitHubRepository
    {
        $repoArg = $input->getArgument('repo');

        if (!$repoArg) {
            $repoArg = text(
                label: 'Which GitHub repository would you like to fetch skills from?',
                placeholder: 'owner/repo or GitHub URL',
                required: true
            );
        }

        try {
            return GitHubRepository::fromInput($repoArg);
        } catch (\InvalidArgumentException $e) {
            echo "<error>{$e->getMessage()}</error>\n";
            return null;
        }
    }

    private function selectSkills(array $skills, InputInterface $input): array
    {
        $skillOptions = $input->getOption('skill');

        if ($input->getOption('all')) {
            return $skills;
        }

        if (!empty($skillOptions)) {
            return array_filter($skills, fn(RemoteSkill $skill): bool => 
                in_array($skill->name, $skillOptions, true)
            );
        }

        $selected = multiselect(
            label: 'Which skills would you like to install?',
            options: array_combine(
                array_keys($skills),
                array_keys($skills)
            ),
            required: true
        );

        return array_filter($skills, fn(RemoteSkill $skill): bool => 
            in_array($skill->name, $selected, true)
        );
    }

    private function downloadSkills(array $skills, string $projectRoot, bool $force): array
    {
        $skillsPath = $projectRoot . '/.agents/skills/pw_core';
        if (!is_dir($skillsPath)) {
            @mkdir($skillsPath, 0755, true);
        }

        $results = ['installed' => [], 'failed' => []];

        foreach ($skills as $skill) {
            $targetPath = $skillsPath . '/' . $skill->name;
            $exists = is_dir($targetPath);

            if ($exists && !$force) {
                continue;
            }

            if ($exists) {
                $this->removeDirectory($targetPath);
            }

            try {
                $provider = new GitHubSkillProvider(new GitHubRepository(
                    $skill->repo,
                    explode('/', $skill->repo)[1] ?? ''
                ));

                if ($provider->downloadSkill($skill, $targetPath)) {
                    $results['installed'][] = $skill->name;
                } else {
                    $results['failed'][$skill->name] = 'Download failed';
                }
            } catch (\Exception $e) {
                $results['failed'][$skill->name] = $e->getMessage();
            }
        }

        return $results;
    }

    private function removeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            $itemPath = $path . '/' . $item;
            is_dir($itemPath) ? $this->removeDirectory($itemPath) : @unlink($itemPath);
        }

        return @rmdir($path);
    }
}