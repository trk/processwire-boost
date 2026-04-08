<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\BoostManager;
use Totoglu\ProcessWire\Boost\Install\Agents\Agent;
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
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class BoostInstallCommand extends Command
{
    private const AGENT_MAP = [
        'Amp' => AmpAgent::class,
        'Claude Code' => ClaudeAgent::class,
        'Codex' => CodexAgent::class,
        'Cursor' => CursorAgent::class,
        'Gemini CLI' => GeminiAgent::class,
        'GitHub Copilot' => CopilotAgent::class,
        'Junie' => JunieAgent::class,
        'OpenCode' => OpenCodeAgent::class,
        'Trae' => TraeAgent::class,
    ];
    private const FEATURES = [
        'guidelines' => 'AI Guidelines',
        'skills' => 'Agent Skills',
        'mcp' => 'Boost MCP Server Configuration',
    ];

    protected function configure(): void
    {
        $this
            ->setName('boost:install')
            ->setDescription('Manage AI helper setup (ProcessWire Boost). Select features to install/update, deselect to remove.')
            ->addOption('guidelines', null, InputOption::VALUE_NONE, 'Install AI Guidelines')
            ->addOption('skills', null, InputOption::VALUE_NONE, 'Install Agent Skills')
            ->addOption('mcp', null, InputOption::VALUE_NONE, 'Install MCP Server Configuration')
            ->addOption('modules', 'm', InputOption::VALUE_OPTIONAL, 'Comma-separated modules to install')
            ->addOption('agents', 'a', InputOption::VALUE_OPTIONAL, 'Comma-separated agents to configure');
    }

    private function isExplicitFlagMode(InputInterface $input): bool
    {
        return $input->getOption('guidelines') || $input->getOption('skills') || $input->getOption('mcp');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->displayBanner();

        intro('Managing ProcessWire Boost installation...');

        $projectRoot = getcwd();
        $manager = new BoostManager($projectRoot);
        $configPath = $projectRoot . '/.llms/boost.json';

        $config = [
            'version' => '1.0.0',
            'guidelines' => false,
            'skills' => false,
            'mcp' => false,
            'modules' => [],
            'agents' => [],
            'generated_at' => null,
        ];
        if (file_exists($configPath)) {
            $config = array_merge($config, json_decode(file_get_contents($configPath) ?: '{}', true) ?: []);
        }
        $installedFeatures = [
            'guidelines' => $config['guidelines'] ?? false,
            'skills' => $config['skills'] ?? false,
            'mcp' => $config['mcp'] ?? false,
        ];
        $installedModules = $config['modules'] ?? [];
        $installedAgents = $config['agents'] ?? [];

        $explicitMode = $this->isExplicitFlagMode($input);

        if ($explicitMode) {
            $selectedFeatures = [];
            if ($input->getOption('guidelines')) $selectedFeatures[] = 'guidelines';
            if ($input->getOption('skills')) $selectedFeatures[] = 'skills';
            if ($input->getOption('mcp')) $selectedFeatures[] = 'mcp';
        } else {
            $featureLabels = [];
            foreach (self::FEATURES as $key => $label) {
                $featureLabels[$key] = $label;
            }
            $defaults = [];
            foreach (self::FEATURES as $key => $label) {
                if ($installedFeatures[$key]) $defaults[] = $key;
            }
            if (empty($defaults)) $defaults = array_keys(self::FEATURES);
            $selectedFeatures = multiselect(
                label: 'Which Boost features would you like to configure?',
                options: $featureLabels,
                default: $defaults,
                hint: 'This will configure the selected features',
            );
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

        $agentChoices = array_keys(self::AGENT_MAP);
        
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

        $toInstall = [];
        $toRemove = [];
        foreach (self::FEATURES as $key => $label) {
            $isSelected = in_array($key, $selectedFeatures);
            $isInstalled = $installedFeatures[$key] ?? false;
            if ($isSelected && !$isInstalled) {
                $toInstall[] = $key;
            } elseif (!$isSelected && $isInstalled) {
                $toRemove[] = $key;
            }
        }

        $modulesToInstall = array_diff($selectedModules, $installedModules);
        $modulesToRemove = array_diff($installedModules, $selectedModules);
        $agentsToInstall = array_diff($selectedAgents, $installedAgents);
        $agentsToRemove = array_diff($installedAgents, $selectedAgents);

        foreach ($toRemove as $featureKey) {
            $output->writeln("  <fg=red>✗ Removing " . self::FEATURES[$featureKey] . "...</>");
            $manager->uninstallFeature(self::FEATURES[$featureKey]);
        }

        foreach ($toInstall as $featureKey) {
            $output->writeln("  <fg=green>✓ Installing " . self::FEATURES[$featureKey] . "...</>");
        }

        $shouldSync = !empty($toInstall) || !empty($toRemove) || !empty($modulesToInstall) || !empty($modulesToRemove) || !empty($selectedFeatures);

        if ($shouldSync) {
            $featureLabels = [];
            foreach ($selectedFeatures as $key) {
                $featureLabels[] = self::FEATURES[$key];
            }
            spin(function () use ($manager, $featureLabels, $selectedModules, $selectedAgents) {
                $agents = $this->resolveAgents($selectedAgents);
                $manager->sync($featureLabels, $selectedModules, $agents);
            }, 'Syncing Boost configuration...');
            $output->writeln("  <fg=green>✓ Sync complete</>\n");
        }

        foreach ($agentsToInstall as $agentName) {
            $agentClass = self::AGENT_MAP[$agentName] ?? null;
            if ($agentClass) {
                $agent = new $agentClass();
                $output->writeln("  <fg=green>✓ Configured {$agentName} ({$agent->guidelinesPath()})</>");
            }
        }

        foreach ($agentsToRemove as $agentName) {
            $agentClass = self::AGENT_MAP[$agentName] ?? null;
            if ($agentClass) {
                $agent = new $agentClass();
                $agentFile = getcwd() . '/' . $agent->guidelinesPath();
                if (file_exists($agentFile)) {
                    unlink($agentFile);
                }
                $output->writeln("  <fg=red>✗ Removed {$agentName} ({$agent->guidelinesPath()})</>");
            }
        }

        if (in_array('mcp', $selectedFeatures) && !empty($selectedAgents)) {
            $agentsForMcp = $this->resolveAgents($selectedAgents);
            if (!empty($agentsForMcp)) {
                spin(function () use ($agentsForMcp, $projectRoot) {
                    $key = 'processwire';
                    foreach ($agentsForMcp as $agent) {
                        $command = $agent->getPhpPath();
                        $wire = $agent->getWirePath($projectRoot);
                        $args = [$wire, 'boost:mcp'];
                        $agent->installMcp($key, $command, $args, []);
                    }
                }, 'Writing agent MCP configurations...');
                $output->writeln("  <fg=green>✓ MCP configurations written</>");
            }
        }

        $aiDir = $projectRoot . '/.llms';
        if (!is_dir($aiDir)) {
            mkdir($aiDir, 0755, true);
        }

        $newConfig = [
            'version' => '1.0.0',
            'guidelines' => in_array('guidelines', $selectedFeatures),
            'skills' => in_array('skills', $selectedFeatures),
            'mcp' => in_array('mcp', $selectedFeatures),
            'modules' => array_values($selectedModules),
            'agents' => array_values($selectedAgents),
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        if ($explicitMode) {
            $config = array_merge($config, $newConfig);
        } else {
            $config = $newConfig;
        }

        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->displaySummary($output, $selectedFeatures, $selectedAgents, $selectedModules);

        $output->writeln("\n  ┌─────────────────────────────────────────────────────────────────┐");
        $output->writeln("  │  Enjoy the boost 🚀 Check your AI agent's MD file in root.      │");
        $output->writeln("  │  📦 https://github.com/trk/processwire-boost/                  │");
        $output->writeln("  └─────────────────────────────────────────────────────────────────┘\n");
        return Command::SUCCESS;
    }

    /**
     * Resolve display names to Agent instances.
     *
     * @param string[] $agentNames
     * @return Agent[]
     */
    private function resolveAgents(array $agentNames): array
    {
        $agents = [];
        foreach ($agentNames as $name) {
            $class = self::AGENT_MAP[$name] ?? null;
            if ($class) {
                $agents[] = new $class();
            }
        }

        return $agents;
    }

    private function displaySummary(OutputInterface $output, array $selectedFeatures, array $selectedAgents, array $selectedModules): void
    {
        $output->writeln("\n  ┌─────────────────────────────────────────────────────────────┐");
        $output->writeln("  │                    Installation Summary                    │");
        $output->writeln("  └─────────────────────────────────────────────────────────────┘\n");

        $projectRoot = getcwd();
        $guidelineCount = count(glob($projectRoot . '/.llms/guidelines/*.md')) ?: 0;
        $skillCount = count(glob($projectRoot . '/.llms/skills/*/SKILL.md')) ?: 0;

        $featureLabels = [];
        foreach ($selectedFeatures as $key) {
            $featureLabels[] = self::FEATURES[$key];
        }
        $output->writeln("  📋 <fg=yellow>Installed Features:</> " . implode(', ', $featureLabels) . "\n");

        if (in_array('guidelines', $selectedFeatures)) {
            $output->writeln("  ✅ <fg=green>{$guidelineCount}</> guidelines installed");
        }

        if (in_array('skills', $selectedFeatures)) {
            $output->writeln("  ✅ <fg=green>{$skillCount}</> skills synced");
        }

        if (in_array('mcp', $selectedFeatures)) {
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