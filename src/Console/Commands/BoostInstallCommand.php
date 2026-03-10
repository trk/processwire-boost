<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\BoostManager;
use Totoglu\ProcessWire\Boost\SkillBuilder;
use Totoglu\ProcessWire\Boost\Install\Agents\Gemini as GeminiAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Codex as CodexAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Cursor as CursorAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Copilot as CopilotAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\ClaudeCode as ClaudeAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Amp as AmpAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Junie as JunieAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\OpenCode as OpenCodeAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Trae as TraeAgent;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class BoostInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:install')
            ->setDescription('Manage AI helper setup (ProcessWire Boost). Select features to install/update, deselect to remove.')
            ->addOption('feature', 'f', InputOption::VALUE_OPTIONAL, 'Feature to install (AI Guidelines, Agent Skills, Boost MCP Server Configuration)')
            ->addOption('modules', 'm', InputOption::VALUE_OPTIONAL, 'Comma-separated modules to install')
            ->addOption('agents', 'a', InputOption::VALUE_OPTIONAL, 'Comma-separated agents to configure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->displayBanner();

        intro('Managing ProcessWire Boost installation...');

        $projectRoot = getcwd();
        $manager = new BoostManager($projectRoot);
        $configPath = $projectRoot . '/.ai/boost.json';

        $config = ['version' => '1.0.0', 'features' => [], 'modules' => [], 'agents' => [], 'generated_at' => null];
        if (file_exists($configPath)) {
            $config = array_merge($config, json_decode(file_get_contents($configPath) ?: '{}', true) ?: []);
        }
        $installedFeatures = $config['features'] ?? [];
        $installedModules = $config['modules'] ?? [];
        $installedAgents = $config['agents'] ?? [];

        $allFeatures = ['AI Guidelines', 'Agent Skills', 'Boost MCP Server Configuration'];

        $featureOpt = $input->getOption('feature');
        if ($featureOpt) {
            $selectedFeatures = [in_array($featureOpt, $allFeatures) ? $featureOpt : 'AI Guidelines'];
        } else {
            $featureOptions = array_combine($allFeatures, $allFeatures);
            $selectedFeatures = [select(
                label: 'Which Boost feature would you like to configure first?',
                options: $featureOptions,
                default: $installedFeatures[0] ?? 'AI Guidelines'
            )];
        }

        $availableModules = $manager->getDiscoverableModules();
        $moduleChoices = array_keys($availableModules);

        $selectedModules = [];
        $modulesOpt = $input->getOption('modules');
        if ($modulesOpt) {
            $selectedModules = array_filter(array_map('trim', explode(',', $modulesOpt)));
        } elseif (!empty($moduleChoices)) {
            $moduleOptions = [];
            foreach ($moduleChoices as $m) {
                $moduleOptions[$m] = $m;
            }
            $selectedModules = multiselect(
                label: 'Which third-party AI guidelines/skills would you like to install?',
                options: $moduleOptions,
                default: $installedModules,
                hint: 'Select to install, deselect to remove.',
                required: false
            );
        } elseif (!empty($moduleChoices)) {
            note('No third-party modules with Boost resources detected.');
        }

        $agentChoices = ['Amp','Claude Code','Codex','Cursor','Gemini CLI','GitHub Copilot','Junie','OpenCode','Trae'];
        
        $selectedAgents = [];
        $agentsOpt = $input->getOption('agents');
        if ($agentsOpt) {
            $selectedAgents = array_filter(array_map('trim', explode(',', $agentsOpt)));
        } else {
            $agentOptions = [];
            foreach ($agentChoices as $a) {
                $agentOptions[$a] = $a;
            }
            $selectedAgents = multiselect(
                label: 'Which AI agents would you like to configure?',
                options: $agentOptions,
                default: $installedAgents,
                required: false
            );
        }

        $output->writeln("\n  <fg=yellow>Processing changes...</>\n");

        $toInstall = array_diff($selectedFeatures, $installedFeatures);
        $toRemove = array_diff($installedFeatures, $selectedFeatures);
        $toUpdate = array_intersect($selectedFeatures, $installedFeatures);
        $modulesToInstall = array_diff($selectedModules, $installedModules);
        $modulesToRemove = array_diff($installedModules, $selectedModules);
        $agentsToInstall = array_diff($selectedAgents, $installedAgents);
        $agentsToRemove = array_diff($installedAgents, $selectedAgents);

        foreach ($toRemove as $featureToRemove) {
            $output->writeln("  <fg=red>✗ Removing {$featureToRemove}...</>");
            $manager->uninstallFeature($featureToRemove);
        }

        foreach ($toInstall as $featureToInstall) {
            $output->writeln("  <fg=green>✓ Installing {$featureToInstall}...</>");
        }

        if (!empty($toInstall) || !empty($toUpdate) || !empty($modulesToInstall) || !empty($modulesToRemove)) {
            spin(function () use ($manager, $selectedFeatures, $selectedModules, $selectedAgents) {
                $manager->sync($selectedFeatures, $selectedModules, $selectedAgents);
            }, 'Syncing Boost configuration...');
            $output->writeln("  <fg=green>✓ Sync complete</>\n");
        }

        foreach ($agentsToInstall as $agent) {
            $filename = strtoupper(str_replace(' ', '_', $agent)) . '.md';
            if ($agent === 'Cursor') $filename = 'CURSOR.md';
            if ($agent === 'Gemini CLI') $filename = 'GEMINI.md';
            if ($agent === 'Claude Code') $filename = 'CLAUDE.md';
            $output->writeln("  <fg=green>✓ Configured {$agent} ({$filename})</>");
        }

        foreach ($agentsToRemove as $agent) {
            $filename = strtoupper(str_replace(' ', '_', $agent)) . '.md';
            if ($agent === 'Cursor') $filename = 'CURSOR.md';
            if ($agent === 'Gemini CLI') $filename = 'GEMINI.md';
            if ($agent === 'Claude Code') $filename = 'CLAUDE.md';
            $agentFile = getcwd() . '/' . $filename;
            if (file_exists($agentFile)) {
                unlink($agentFile);
            }
            $output->writeln("  <fg=red>✗ Removed {$agent} ({$filename})</>");
        }

        if (in_array('Agent Skills', $selectedFeatures)) {
            if (in_array('Trae', $selectedAgents)) {
                $sb = new SkillBuilder($projectRoot);
                $trae = new TraeAgent();
                spin(fn() => $sb->exportForAgent($trae, null, $projectRoot . '/.trae/skills'), 'Exporting Trae skills...');
                $output->writeln("  <fg=green>✓ Trae skills exported</>");
            }
            if (in_array('OpenCode', $selectedAgents)) {
                $sb = new SkillBuilder($projectRoot);
                $opencode = new OpenCodeAgent();
                spin(fn() => $sb->exportForAgent($opencode, null, $projectRoot . '/.ai/skills'), 'Exporting OpenCode skills...');
                $output->writeln("  <fg=green>✓ OpenCode skills exported</>");
            }
        }

        if (in_array('Boost MCP Server Configuration', $selectedFeatures) && !empty($selectedAgents)) {
            $pathType = select(
                label: 'Path type for MCP command?',
                options: [
                    'Relative (vendor/bin/wire)' => 'rel',
                    'Absolute (/.../vendor/bin/wire)' => 'abs',
                ],
                default: 'rel'
            );

            $agentsForMcp = [];
            foreach ($selectedAgents as $a) {
                if ($a === 'Gemini CLI') $agentsForMcp[] = new GeminiAgent();
                if ($a === 'Codex') $agentsForMcp[] = new CodexAgent();
                if ($a === 'Cursor') $agentsForMcp[] = new CursorAgent();
                if ($a === 'GitHub Copilot') $agentsForMcp[] = new CopilotAgent();
                if ($a === 'Claude Code') $agentsForMcp[] = new ClaudeAgent();
                if ($a === 'Amp') $agentsForMcp[] = new AmpAgent();
                if ($a === 'Junie') $agentsForMcp[] = new JunieAgent();
                if ($a === 'OpenCode') $agentsForMcp[] = new OpenCodeAgent();
                if ($a === 'Trae') $agentsForMcp[] = new TraeAgent();
            }
            if (!empty($agentsForMcp)) {
                spin(function () use ($agentsForMcp, $pathType, $projectRoot) {
                    $key = 'processwire';
                    $command = 'php';
                    $wire = $pathType === 'abs' ? ($projectRoot . '/vendor/bin/wire') : 'vendor/bin/wire';
                    $args = [$wire, 'boost:mcp'];
                    foreach ($agentsForMcp as $agent) {
                        $agent->installMcp($key, $command, $args, []);
                    }
                }, 'Writing agent MCP configurations...');
                $output->writeln("  <fg=green>✓ MCP configurations written</>");
            }
        }

        $aiDir = $projectRoot . '/.ai';
        if (!is_dir($aiDir)) {
            mkdir($aiDir, 0755, true);
        }
        $config = [
            'version' => '1.0.0',
            'features' => array_values($selectedFeatures),
            'modules' => array_values($selectedModules),
            'agents' => array_values($selectedAgents),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->displaySummary($output, $selectedFeatures, $selectedAgents, $selectedModules);

        $output->writeln("\n  ┌─────────────────────────────────────────────────────────────────┐");
        $output->writeln("  │  Enjoy the boost 🚀 Check your AI agent's MD file in root.      │");
        $output->writeln("  │  📦 https://github.com/trk/processwire-boost/                  │");
        $output->writeln("  └─────────────────────────────────────────────────────────────────┘\n");
        return Command::SUCCESS;
    }

    private function displaySummary(OutputInterface $output, array $selectedFeatures, array $selectedAgents, array $selectedModules): void
    {
        $output->writeln("\n  ┌─────────────────────────────────────────────────────────────┐");
        $output->writeln("  │                    Installation Summary                    │");
        $output->writeln("  └─────────────────────────────────────────────────────────────┘\n");

        $projectRoot = getcwd();
        $guidelineCount = count(glob($projectRoot . '/.ai/guidelines/*.md')) ?: 0;
        $skillCount = count(glob($projectRoot . '/.ai/skills/pw_core/*/SKILL.md')) ?: 0;
        $agentsWithSkills = [];
        if (in_array('Trae', $selectedAgents)) $agentsWithSkills[] = 'Trae';
        if (in_array('OpenCode', $selectedAgents)) $agentsWithSkills[] = 'OpenCode';

        $output->writeln("  📋 <fg=yellow>Installed Features:</> " . implode(', ', $selectedFeatures) . "\n");

        if (in_array('AI Guidelines', $selectedFeatures)) {
            $output->writeln("  ✅ <fg=green>{$guidelineCount}</> guidelines installed");
        }

        if (in_array('Agent Skills', $selectedFeatures)) {
            $output->writeln("  ✅ <fg=green>{$skillCount}</> skills synced");
        }

        if (in_array('Boost MCP Server Configuration', $selectedFeatures)) {
            $output->writeln("  ✅ MCP servers configured");
        }

        if (!empty($selectedModules)) {
            $output->writeln("\n  📦 <fg=yellow>Third-party modules:</> " . implode(', ', $selectedModules));
        }

        if (!empty($selectedAgents)) {
            $output->writeln("\n  🤖 <fg=yellow>AI Agents:</> " . implode(', ', $selectedAgents));
        }

        $output->writeln("");
    }

    private function displayBanner(): void
    {
        $gradient = [
            "\033[38;5;90m",
            "\033[38;5;90m",
            "\033[38;5;96m",
            "\033[38;5;102m",
            "\033[38;5;109m",
            "\033[38;5;109m",
        ];
        echo $gradient[0] . "██████╗  ██████╗  ██████╗ ███████╗████████╗\n";
        echo $gradient[1] . "██╔══██╗██╔═══██╗██╔═══██╗██╔════╝╚══██╔══╝\n";
        echo $gradient[2] . "██████╔╝██║   ██║██║   ██║███████╗   ██║   \n";
        echo $gradient[3] . "██╔══██╗██║   ██║██║   ██║╚════██║   ██║   \n";
        echo $gradient[4] . "██████╔╝╚██████╔╝╚██████╔╝███████║   ██║   \n";
        echo $gradient[5] . "╚═════╝  ╚═════╝  ╚═════╝ ╚══════╝   ╚═╝   \n";
        echo "\033[0m";
        echo "\n \033[38;5;109m✦ ProcessWire Boost :: Install :: We Must Ship ✦\033[0m\n\n";
    }
}