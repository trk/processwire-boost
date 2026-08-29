<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\Skills\Remote\AuditResult;
use Totoglu\Console\Boost\Skills\Remote\GitHubRepository;
use Totoglu\Console\Boost\Skills\Remote\GitHubSkillProvider;
use Totoglu\Console\Boost\Skills\Remote\RemoteSkill;
use Totoglu\Console\Boost\Skills\Remote\SkillAuditor;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class BoostAddSkillCommand extends Command
{
    private InputInterface $currentInput;

    /** @var list<string> */
    private array $skippedSkills = [];

    protected function configure(): void
    {
        $this
            ->setName('boost:add-skill')
            ->setDescription('Add skills from a remote GitHub repository')
            ->addArgument('repo', InputArgument::OPTIONAL, 'GitHub repository (owner/repo or full URL)')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available skills')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Install all skills')
            ->addOption('skill', 's', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Specific skills to install')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing skills')
            ->addOption('skip-audit', null, InputOption::VALUE_NONE, 'Skip heuristic security audit')
            ->addOption('skip-update', null, InputOption::VALUE_NONE, 'Skip running boost:update after installation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->currentInput = $input;
        $this->skippedSkills = [];

        $this->displayHeader();

        $projectRoot = getcwd() ?: '.';

        $repo = $this->parseRepository($input);
        if ($repo === null) {
            return Command::FAILURE;
        }

        $provider = new GitHubSkillProvider($repo);

        $skills = [];
        try {
            info("Fetching skills from {$repo->fullName()}...");
            $skills = $provider->discoverSkills();
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

        $skillsToInstall = $this->skillsToInstall($selectedSkills, $projectRoot, (bool) $input->getOption('force'));
        if ($skillsToInstall === []) {
            $output->writeln('<comment>No new skills were selected for installation.</comment>');
            return Command::SUCCESS;
        }

        if (!$this->runAuditBeforeInstall($provider, $skillsToInstall)) {
            $output->writeln('<comment>Installation canceled.</comment>');
            return Command::SUCCESS;
        }

        $results = $this->downloadSkills($skillsToInstall, $projectRoot);

        if (!empty($results['installed'])) {
            $output->writeln("\n  <info>Skills installed:</info>");
            foreach ($results['installed'] as $skillName) {
                $output->writeln("  • {$skillName}");
            }

            if (!$input->getOption('skip-update')) {
                $this->runBoostUpdate();
            }
        }

        if (!empty($this->skippedSkills)) {
            $output->writeln("\n  <comment>Skipped existing skills:</comment>");
            foreach ($this->skippedSkills as $skillName) {
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
            return array_filter(
                $skills,
                fn(RemoteSkill $skill): bool =>
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

        return array_filter(
            $skills,
            fn(RemoteSkill $skill): bool =>
            in_array($skill->name, $selected, true)
        );
    }

    private function downloadSkills(array $skills, string $projectRoot): array
    {
        $skillsPath = $projectRoot . '/.agents/skills';
        if (!is_dir($skillsPath)) {
            @mkdir($skillsPath, 0755, true);
        }

        $results = ['installed' => [], 'failed' => []];

        foreach ($skills as $skill) {
            $safeSkillName = $this->sanitizeSkillName($skill->name);
            if ($safeSkillName === '') {
                $results['failed'][$skill->name] = 'Invalid skill name';
                continue;
            }

            $targetPath = $skillsPath . '/' . $safeSkillName;
            if (is_dir($targetPath)) {
                $this->removeDirectory($targetPath);
            }

            try {
                $provider = new GitHubSkillProvider($this->repositoryFromSkill($skill));

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

    /**
     * @param array<string, RemoteSkill> $skills
     * @return array<string, RemoteSkill>
     */
    private function skillsToInstall(array $skills, string $projectRoot, bool $force): array
    {
        $existing = array_filter(
            $skills,
            fn(RemoteSkill $skill): bool => is_dir($projectRoot . '/.agents/skills/' . $this->sanitizeSkillName($skill->name))
        );

        if ($existing === []) {
            return $skills;
        }

        if ($force) {
            return $skills;
        }

        if (!$this->currentInput->isInteractive()) {
            $this->skippedSkills = array_values(array_map(
                static fn(RemoteSkill $skill): string => $skill->name,
                $existing
            ));
            return array_diff_key($skills, $existing);
        }

        $shouldUpdate = confirm(
            label: sprintf('Update %d existing skill(s)?', count($existing)),
            default: false
        );

        if ($shouldUpdate) {
            return $skills;
        }

        $this->skippedSkills = array_values(array_map(
            static fn(RemoteSkill $skill): string => $skill->name,
            $existing
        ));

        return array_diff_key($skills, $existing);
    }

    private function repositoryFromSkill(RemoteSkill $skill): GitHubRepository
    {
        return GitHubRepository::fromInput($skill->repo . ($skill->path !== '' ? '/' . $skill->path : ''));
    }

    /**
     * @param array<string, RemoteSkill> $skills
     */
    private function runAuditBeforeInstall(GitHubSkillProvider $provider, array $skills): bool
    {
        if ((bool) $this->currentInput->getOption('skip-audit')) {
            return true;
        }

        $auditResults = spin(
            callback: fn(): array => (new SkillAuditor())->audit($provider, $skills),
            message: 'Running heuristic security audit...'
        );

        if (!$this->hasRiskySkills($auditResults)) {
            return true;
        }

        $this->displayAuditResults($auditResults);

        if (!$this->currentInput->isInteractive()) {
            return true;
        }

        return confirm(
            label: 'Risk signals were found. Continue installing these skills?',
            default: false
        );
    }

    /**
     * @param array<string, list<AuditResult>> $auditResults
     */
    private function hasRiskySkills(array $auditResults): bool
    {
        foreach ($auditResults as $results) {
            foreach ($results as $result) {
                if ($result->risk->weight() >= 3) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, list<AuditResult>> $auditResults
     */
    private function displayAuditResults(array $auditResults): void
    {
        note('Security Audit');

        foreach ($auditResults as $skillName => $results) {
            foreach ($results as $result) {
                $signals = $result->signals === []
                    ? 'No suspicious patterns detected'
                    : implode('; ', $result->signals);

                echo "  • {$skillName} [{$result->risk->label()} via {$result->partner}] {$signals}\n";
            }
        }
    }

    private function runBoostUpdate(): void
    {
        $application = $this->getApplication();
        if ($application === null) {
            return;
        }

        $command = $application->find('boost:update');
        $command->run(new ArrayInput([]), new NullOutput());
    }

    private function sanitizeSkillName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^a-z0-9_-]/i', '', $name) ?: '';

        return $name;
    }

    private function removeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $itemPath = $path . '/' . $item;
            is_dir($itemPath) ? $this->removeDirectory($itemPath) : @unlink($itemPath);
        }

        return @rmdir($path);
    }
}
