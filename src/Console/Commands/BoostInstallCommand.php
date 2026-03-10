<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class BoostInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:install')
            ->setDescription('Initialize the AI helper setup (ProcessWire Boost) with a modern interface.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->displayBanner();

        intro('Starting ProcessWire Boost installation...');

        $projectRoot = getcwd();
        $manager = new BoostManager($projectRoot);

        $feature = select(
            label: 'Which Boost feature would you like to configure first?',
            options: [
                'AI Guidelines' => 'AI Guidelines',
                'Agent Skills' => 'Agent Skills',
                'Boost MCP Server Configuration' => 'Boost MCP Server Configuration'
            ],
            default: 'AI Guidelines'
        );

        $features = [$feature];

        $availableModules = $manager->getDiscoverableModules();
        $moduleChoices = array_keys($availableModules);

        $selectedModules = [];
        if (!empty($moduleChoices)) {
            $selectedModules = multiselect(
                label: 'Which third-party AI guidelines/skills would you like to install?',
                options: array_combine($moduleChoices, $moduleChoices),
                hint: 'Space to select, Enter to confirm',
                required: false
            );
        } else {
            note('No third-party modules with Boost resources detected.');
        }

        $agentChoices = ['Amp','Claude Code','Codex','Cursor','Gemini CLI','GitHub Copilot','Junie','OpenCode','Trae'];
        
        $configPath = $projectRoot . '/.ai/boost.json';
        $savedAgents = [];
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath) ?: '', true);
            $savedAgents = $config['agents'] ?? [];
        }
        
        $selectedAgents = multiselect(
            label: 'Which AI agents would you like to configure?',
            options: array_combine($agentChoices, $agentChoices),
            default: $savedAgents,
            required: false
        );

        spin(
            function () use ($manager, $features, $selectedModules, $selectedAgents) {
                $manager->install($features, $selectedModules, $selectedAgents);
            },
            'Installing and Configuring Boost...'
        );

        if (!empty($selectedAgents)) {
            foreach ($selectedAgents as $agent) {
                $filename = strtoupper(str_replace(' ', '_', $agent)) . '.md';
                if ($agent === 'Cursor') $filename = 'CURSOR.md';
                if ($agent === 'Gemini CLI') $filename = 'GEMINI.md';
                if ($agent === 'Claude Code') $filename = 'CLAUDE.md';
                info("✓ Configured {$agent} ({$filename})");
            }
        }

        if (in_array('Boost MCP Server Configuration', $features)) {
            note("MCP Server is available via: ./vendor/bin/wire boost:mcp");
        }

        if ($feature === 'Agent Skills') {
            if (in_array('Trae', $selectedAgents)) {
                $sb = new SkillBuilder($projectRoot);
                $trae = new TraeAgent();
                spin(fn() => $sb->exportForAgent($trae, null, $projectRoot . '/.trae/skills'), 'Exporting Trae skills...');
                info('Trae skills exported');
            }

            if (in_array('OpenCode', $selectedAgents)) {
                $sb = new SkillBuilder($projectRoot);
                $opencode = new OpenCodeAgent();
                spin(fn() => $sb->exportForAgent($opencode, null, $projectRoot . '/.ai/skills'), 'Exporting OpenCode skills...');
                info('OpenCode skills exported');
            }
        }

        if ($feature === 'Boost MCP Server Configuration' && !empty($selectedAgents)) {
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
                info('Agent MCP configurations completed');
            }
        }

        $configPath = $projectRoot . '/.ai/boost.json';
        $aiDir = $projectRoot . '/.ai';
        if (!is_dir($aiDir)) {
            mkdir($aiDir, 0755, true);
        }
        $config = [
            'version' => '1.0.0',
            'agents' => $selectedAgents,
            'modules' => $selectedModules,
            'features' => $features,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->displaySummary($output, $feature, $selectedAgents, $selectedModules);

        outro('Enjoy the boost 🚀 Check your AI agent\'s MD file in root.');
        $output->writeln("\n  📦 <fg=cyan>https://github.com/trk/processwire-boost/</>\n");
        return Command::SUCCESS;
    }

    private function displaySummary(OutputInterface $output, string $feature, array $selectedAgents, array $selectedModules): void
    {
        $output->writeln("\n  ┌─────────────────────────────────────────────────────────────┐");
        $output->writeln("  │                    Installation Summary                    │");
        $output->writeln("  └─────────────────────────────────────────────────────────────┘\n");

        $guidelineCount = count(glob(getcwd() . '/.ai/guidelines/*.md')) ?: 0;
        $skillCount = count(glob(getcwd() . '/.ai/skills/pw_core/*/SKILL.md')) ?: 0;
        $agentsWithSkills = [];
        if (in_array('Trae', $selectedAgents)) $agentsWithSkills[] = 'Trae';
        if (in_array('OpenCode', $selectedAgents)) $agentsWithSkills[] = 'OpenCode';

        $output->writeln("  📋 <fg=yellow>Feature:</> {$feature}\n");

        if ($feature === 'AI Guidelines') {
            $output->writeln("  ✅ Adding <fg=green>{$guidelineCount}</> guidelines to your selected agents:");
            if (!empty($selectedAgents)) {
                foreach ($selectedAgents as $agent) {
                    $output->writeln("     • {$agent}");
                }
            } else {
                $output->writeln("     <fg=gray>(none selected)</>");
            }
        }

        if ($feature === 'Agent Skills') {
            $output->writeln("  ✅ Syncing <fg=green>{$skillCount}</> skills for skills-capable agents:");
            if (!empty($agentsWithSkills)) {
                foreach ($agentsWithSkills as $agent) {
                    $output->writeln("     • {$agent}");
                }
            } else {
                $output->writeln("     <fg=gray>(none selected)</>");
            }
        }

        if ($feature === 'Boost MCP Server Configuration') {
            $output->writeln("  ✅ Installing MCP servers to your selected agents:");
            if (!empty($selectedAgents)) {
                foreach ($selectedAgents as $agent) {
                    $output->writeln("     • {$agent}");
                }
            } else {
                $output->writeln("     <fg=gray>(none selected)</>");
            }
        }

        if (!empty($selectedModules)) {
            $output->writeln("\n  📦 Third-party modules processed: " . count($selectedModules));
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