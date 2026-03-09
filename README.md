# ProcessWire Boost

AI‑focused context bridge for ProcessWire. Generates guidelines, skills and blueprints from the local core and exposes a JSON‑RPC (MCP) server to let AI agents access ProcessWire data safely.

## Features

- Guidelines: Build .ai/guidelines from core PHPDoc and best practices
- Skills: Task playbooks for agents (.ai/skills/pw_core/\*/SKILL.md)
- Blueprints: Class/method summaries with @since (.ai/blueprints/pw_core/\*.json)
- MCP Server: JSON‑RPC (stdio) for schema, pages, modules, access and system ops
- Agent Integration: Install/Agents for Cursor, Gemini CLI, Trae and others
- Composer Integration: Registers commands via processwire-console (Composer extra)

## Installation

```bash
composer require trk/processwire-boost
```

Notes:

- processwire-console is recommended to run commands (vendor/bin/wire).
- ProcessWire core (wire/) must exist; commands boot the core when possible.

## Quick Start

1. Run the interactive installer and generate project context:

```bash
php vendor/bin/wire boost:install
```

- Feature selection: AI Guidelines, Agent Skills, Blueprints, Boost MCP Server Configuration
- Third‑party discovery: site/modules and core modules with a boost folder
- Agent choices: Amp, Claude Code, Codex, Cursor, Gemini CLI, GitHub Copilot, Junie, OpenCode, Trae
- Optional local generation: Guides/Skills from local core
- Optional Build All (PHP): boost:build-all

2. Start the MCP server (for agents):

```bash
php vendor/bin/wire boost:mcp
```

The server listens on stdio for JSON‑RPC requests. The installer writes client configs (e.g., .cursor/mcp.json, .trae/mcp.json).

## Outputs and Sources

- .ai/guidelines: foundation, boost, php, pw_core
  - Sources: vendor/trk/processwire-boost/resources/boost/guidelines
- .ai/skills/pw_core: SKILL.md files
  - Sources: vendor/trk/processwire-boost/resources/builder/skills
- .ai/blueprints/pw_core: class/method summaries
  - Sources: vendor/trk/processwire-boost/resources/boost/blueprints

## Commands

- boost:install — Interactive setup, agent configs and local generation
- boost:mcp — Start the JSON‑RPC MCP server
- boost:guides:build — Generate guidelines from the local core
- boost:skills:build — Generate skills from builder/skills sources
- boost:blueprints:build — Generate blueprint JSON from core
- boost:api:scan — Scan core classes and print a summary
- boost:build-all — Build guidelines, skills and blueprints
- boost:assert — Validate generated assets for quality

Show available commands and help:

```bash
php vendor/bin/wire list
php vendor/bin/wire help boost:build-all
```

## Agent Integration

- Classes: vendor/trk/processwire-boost/src/Install/Agents/\*
- Each agent writes MCP command and environment settings to its client config (e.g., .cursor/mcp.json, .trae/mcp.json).
- Command path: configured by boost:install (Relative / Absolute) for vendor/bin/wire boost:mcp.

## Composer Integration (Console Command Registration)

processwire-console loads external commands from Composer metadata. Boost declares its commands under `extra.processwire-console.commands`. The console reads `vendor/composer/installed.json` and root `composer.json` path repositories to register these commands.

## Requirements

- PHP 8.3+
- processwire/processwire 3.x
- processwire-console (recommended for commands)

## Language Policy

All code, documentation, examples, README content, issues and pull‑request descriptions must be in English only.

## Status & Contributions

This package is under active development. Suggestions, bug reports and pull requests are welcome. Please open an issue or submit a PR.

## License

MIT
