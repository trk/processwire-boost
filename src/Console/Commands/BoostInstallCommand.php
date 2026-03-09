<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\BoostManager;
use Totoglu\ProcessWire\Boost\DocIndex;
use Totoglu\ProcessWire\Boost\GuideBuilder;
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
                'Blueprints' => 'Blueprints',
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
        $selectedAgents = multiselect(
            label: 'Which AI agents would you like to configure?',
            options: array_combine($agentChoices, $agentChoices),
            default: ['Cursor', 'Gemini CLI'],
            required: false
        );

        spin(
            function () use ($manager, $features, $selectedModules, $selectedAgents) {
                $manager->install($features, $selectedModules, $selectedAgents);
            },
            'Installing and Configuring Boost...'
        );

        info('ProcessWire Boost context generated successfully!');

        if (in_array('Boost MCP Server Configuration', $features)) {
            note("MCP Server is available via: ./vendor/bin/wire boost:mcp");
        }

        foreach ($selectedAgents as $agent) {
            $filename = strtoupper(str_replace(' ', '_', $agent)) . '.md';
            if ($agent === 'Cursor') $filename = 'CURSOR.md';
            if ($agent === 'Gemini CLI') $filename = 'GEMINI.md';
            if ($agent === 'Claude Code') $filename = 'CLAUDE.md';
            info("✓ Configured {$agent} ({$filename})");
        }

        $generateLocal = select(
            label: 'Generate Guides/Skills from local core?',
            options: [
                'Skip' => 'skip',
                'Guides only' => 'guide',
                'Skills only' => 'skill',
                'Guides + Skills' => 'both',
            ],
            default: 'both'
        );

        if ($generateLocal !== 'skip') {
            $doc = new DocIndex($projectRoot);
            $files = [
                'Pages.php','Page.php','Fields.php','Templates.php','Users.php','Roles.php','Permissions.php','Modules.php','WireInput.php','Sanitizer.php','Session.php','Config.php','Wire.php',
            ];
            $index = $doc->scan($files);
            if ($generateLocal === 'guide' || $generateLocal === 'both') {
                $gb = new GuideBuilder($projectRoot);
                spin(fn() => $gb->build($index, $projectRoot.'/.ai/guidelines/pw_core.md'), 'Generating guide...');
                info('pw_core.md generated');
            }
            if ($generateLocal === 'skill' || $generateLocal === 'both') {
                $sb = new SkillBuilder($projectRoot);
                spin(fn() => $sb->buildFromSources(null), 'Generating skills...');
                info('Skills generated');
            }
        }

        $runBuild = select(
            label: 'Run Build All now?',
            options: [
                'Skip' => 'skip',
                'Run (PHP)' => 'run_php',
            ],
            default: 'run_php'
        );
        if ($runBuild === 'run_php') {
            spin(function () use ($projectRoot) {
                $cmd = 'php vendor/bin/wire boost:build-all';
                $cwd = getcwd();
                chdir($projectRoot);
                exec($cmd);
                chdir($cwd);
            }, 'PHP Build All running...');
            info('PHP Build All done');
        }

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

        if (in_array('Trae', $selectedAgents)) {
            $sb = new SkillBuilder($projectRoot);
            spin(fn() => $sb->exportForTrae(null, $projectRoot.'/.trae/skills'), 'Exporting Trae skills...');
            info('Trae skills exported');
        }

        outro('Enjoy the boost 🚀 Check your AI agent\'s MD file in root.');
        return Command::SUCCESS;
    }

    private function displayBanner(): void
    {
        echo "\033[34m";
        echo "██████╗  ██████╗  ██████╗ ███████╗████████╗\n";
        echo "██╔══██╗██╔═══██╗██╔═══██╗██╔════╝╚══██╔══╝\n";
        echo "██████╔╝██║   ██║██║   ██║███████╗   ██║   \n";
        echo "██╔══██╗██║   ██║██║   ██║╚════██║   ██║   \n";
        echo "██████╔╝╚██████╔╝╚██████╔╝███████║   ██║   \n";
        echo "╚═════╝  ╚═════╝  ╚═════╝ ╚══════╝   ╚═╝   \n";
        echo "\033[0m";
        echo "\n \033[36m✦ ProcessWire Boost :: Install :: We Must Ship ✦\033[0m\n\n";
    }
}
