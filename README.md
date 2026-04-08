<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3+-8892BF?style=flat-square&logo=php" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/ProcessWire-3.x-ea6a1a?style=flat-square" alt="ProcessWire 3.x">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License">
</p>

# ProcessWire Boost

**AI-powered context bridge for ProcessWire CMS/CMF.**

Boost generates contextual guidelines, deploys task-oriented skills, and exposes a JSON-RPC (MCP) server — giving AI coding agents deep, safe access to your ProcessWire project.

Built with an **agent-aware architecture** inspired by [Laravel Boost](https://github.com/laravel/boost): each AI agent receives resources in the exact format and directory structure it expects.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
  - [Agent System](#agent-system)
  - [Guidelines](#guidelines)
  - [Skills](#skills)
  - [Blueprints](#blueprints)
  - [MCP Server](#mcp-server)
- [Commands Reference](#commands-reference)
- [Module Integration](#module-integration)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Features

| Feature | Description |
|---------|-------------|
| **Agent-Aware Deployment** | Each agent receives guidelines, skills, and MCP configs in its native format and directory |
| **AI Guidelines** | Compiles ProcessWire best practices into a single `AGENTS.md` (or agent-specific file) |
| **Task Playbooks (Skills)** | 11 built-in skills covering selectors, page manipulation, module development, HTMX, and more |
| **MCP Server** | JSON-RPC stdio server for live schema inspection, page queries, module management, and system operations |
| **Module Discovery** | Third-party modules expose their own guidelines and skills via `{module}/llms/` directories |
| **Auto Path Resolution** | MCP configurations automatically use relative or absolute paths based on each agent's requirements |

---

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | `>= 8.3` |
| ProcessWire | `3.x` |
| [processwire-console](https://github.com/trk/processwire-console) | `dev-main` (peer dependency) |

---

## Installation

```bash
composer require trk/processwire-boost
```

> **Note:** `processwire-console` provides the `vendor/bin/wire` CLI runner that boots ProcessWire and registers Boost commands automatically via Composer `extra` metadata.

---

## Quick Start

### 1. Run the Installer

**Interactive mode** — prompts for features, agents, and modules:

```bash
php vendor/bin/wire boost:install
```

**Flag mode** — skip prompts entirely:

```bash
php vendor/bin/wire boost:install \
  --guidelines --skills --mcp \
  --agents="Cursor,Claude Code,Gemini CLI" \
  --modules="Htmx,FieldtypeAiAssistant"
```

### 2. What Gets Generated

After installation, your project root will contain:

```
project-root/
├── AGENTS.md                    # Universal guidelines (Cursor, Codex, Amp, etc.)
├── CLAUDE.md                    # Claude Code specific guidelines
├── GEMINI.md                    # Gemini CLI specific guidelines
├── .mcp.json                    # Claude Code MCP config
├── .cursor/
│   ├── mcp.json                 # Cursor MCP config
│   └── skills/                  # Cursor skill playbooks
│       ├── pw-selectors/SKILL.md
│       ├── pw-module-development/SKILL.md
│       └── ...
├── .claude/skills/              # Claude Code skill playbooks
├── .junie/
│   ├── mcp/mcp.json             # Junie MCP config (absolute paths)
│   └── skills/
├── .trae/
│   ├── mcp.json                 # Trae MCP config (${workspaceFolder} paths)
│   └── rules/                   # Trae skill playbooks (YAML frontmatter)
├── .vscode/mcp.json             # GitHub Copilot MCP config
├── .github/skills/              # GitHub Copilot skill playbooks
└── .llms/
    ├── boost.json               # Installation state
    ├── map.json                  # Schema snapshot (templates, fields, roles)
    ├── skills/                   # Central skill staging area
    └── blueprints/               # JSON blueprints (page.json, etc.)
```

### 3. Verify

```bash
# Check installation state
cat .llms/boost.json

# Verify skills were deployed
ls .cursor/skills/

# Test MCP server manually
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php vendor/bin/wire boost:mcp
```

---

## Architecture

### Agent System

Boost uses a polymorphic `Agent` class hierarchy where each AI agent declares:

| Method | Purpose |
|--------|---------|
| `guidelinesPath()` | Where the agent reads its instruction file |
| `skillsPath()` | Where task playbooks are deployed |
| `mcpPathStrategy()` | How MCP paths are resolved (`Relative`, `Absolute`, `WorkspaceFolder`) |
| `mcpConfigPath()` | Where MCP server configuration is written |
| `exportSkill()` | How to format skills (override for YAML frontmatter, etc.) |

#### Supported Agents

| Agent | Guidelines | Skills | MCP Config | Path Mode |
|-------|-----------|--------|------------|-----------|
| **Amp** | `AGENTS.md` | `.agents/skills` | `.amp/settings.json` | `Relative` |
| **Claude Code** | `CLAUDE.md` | `.claude/skills` | `.mcp.json` | `Relative` |
| **Codex** | `AGENTS.md` | `.agents/skills` | `.codex/config.toml` | `Relative` |
| **Cursor** | `AGENTS.md` | `.cursor/skills` | `.cursor/mcp.json` | `Relative` |
| **Gemini CLI** | `GEMINI.md` | `.agents/skills` | `.gemini/settings.json` | `Absolute` |
| **GitHub Copilot** | `AGENTS.md` | `.github/skills` | `.vscode/mcp.json` | `Relative` |
| **Junie** | `AGENTS.md` | `.junie/skills` | `.junie/mcp/mcp.json` | `Absolute` |
| **OpenCode** | `AGENTS.md` | `.agents/skills` | `opencode.json` | `Relative` |
| **Trae** | `AGENTS.md` | `.trae/rules` | `.trae/mcp.json` | `WorkspaceFolder` |

> **Auto Path Resolution:** Each agent declares its `mcpPathStrategy()` via the `McpPathStrategy` enum:
> - `Relative` — `vendor/bin/wire` (default for most agents)
> - `Absolute` — `/full/path/vendor/bin/wire` (Junie, Gemini CLI)
> - `WorkspaceFolder` — `${workspaceFolder}/vendor/bin/wire` (Trae IDE)
>
> No manual configuration needed — paths are resolved automatically during `boost:install`.

#### Adding a Custom Agent

Extend `Agent` and implement the required methods:

```php
<?php

declare(strict_types=1);

namespace Your\Namespace;

use Totoglu\ProcessWire\Boost\Install\Agents\Agent;

final class MyAgent extends Agent
{
    public function name(): string { return 'my_agent'; }
    public function displayName(): string { return 'My Agent'; }
    public function mcpConfigPath(): ?string { return '.my-agent/mcp.json'; }
    public function guidelinesPath(): string { return 'AGENTS.md'; }

    // Override if agent needs absolute paths
    public function useAbsolutePathForMcp(): bool { return false; }

    // Override if agent needs special skill format (YAML frontmatter, etc.)
    public function exportSkill(string $skillName, string $skillPath, string $targetDir): string
    {
        // Custom export logic
    }
}
```

---

### Guidelines

Guidelines are compiled from multiple sources into a single instruction block wrapped in `<processwire-boost-guidelines>` tags.

**Source priority chain:**

1. **Foundation** — `resources/boost/guidelines/foundation.md` (dynamic: PHP version, PW version, skills menu)
2. **Core rules** — `resources/boost/guidelines/*.md` (PHP, selectors, security, development)
3. **Module rules** — `{module}/llms/guidelines/*.md` (third-party modules)
4. **Fallback** — `{module}/llms.txt` (if no guidelines directory exists)

**Merge strategy** for existing files:

| File State | Behavior |
|------------|----------|
| File has `<processwire-boost-guidelines>` tags | Replace content between tags only |
| File exists, no tags | Append tags after existing content |
| File doesn't exist | Create from scratch with header |

This means your custom instructions in `CLAUDE.md` or `AGENTS.md` are preserved across reinstalls.

**Guideline sections generated:**

| Section | Content |
|---------|---------|
| Foundation | System identity, PHP/PW versions, skill activation menu, doc resources |
| Boost | CLI commands, MCP tools, JSON-RPC integration |
| PHP | Strict typing, constructor promotion, PHPDoc, enums |
| ProcessWire Core | API variables (`$pages`, `$fields`, `$users`, etc.) |
| ProcessWire Development | Hooks, module types, namespaces |
| ProcessWire Security | Input sanitization, RBAC, CSRF protection |
| ProcessWire Selectors | Query syntax, operators, performance rules |

---

### Skills

Skills are task playbooks that teach AI agents specific ProcessWire patterns. Each skill is a `SKILL.md` file deployed to the agent's native skill directory.

#### Built-in Skills (11)

| Skill | Description |
|-------|-------------|
| `pw-api-variables` | Context-safe access to ProcessWire API variables |
| `pw-custom-page-classes` | Extending Page class with strongly-typed custom classes |
| `pw-htmx` | HTMX components, swaps, OOB fragments, state management |
| `pw-manipulate-pages` | Discovering, filtering, creating, and editing Page objects |
| `pw-module-development` | Building native backend modules with PHP 8.4 |
| `pw-module-fieldtype-inputfield` | Custom database fieldtypes and their inputfields |
| `pw-module-filevalidator` | File validation and security workflows |
| `pw-module-markup` | Frontend rendering with Markup module ecosystem |
| `pw-module-process` | Admin dashboards, routing, and RBAC |
| `pw-module-textformatter` | String formatting modules for output rendering |
| `pw-selectors` | Query construction, filtering, sorting |
| `pw-url-routing` | URL/Path hooks for custom routing and API endpoints |

#### Agent-Specific Skill Formats

| Agent | Format |
|-------|--------|
| **OpenCode** | Prepends YAML frontmatter (`name`, `description`, `license`, `compatibility`) |
| **Trae** | Wraps in YAML frontmatter, deploys to `.trae/rules/` |
| **All others** | Plain Markdown `SKILL.md` in `{skillName}/SKILL.md` structure |

---

### Blueprints

JSON schema snapshots of ProcessWire structures for quick agent reference:

```bash
ls .llms/blueprints/
# page.json  — Page class method/property summary
```

---

### MCP Server

The MCP (Model Context Protocol) server exposes ProcessWire operations as JSON-RPC tools over stdio.

```bash
php vendor/bin/wire boost:mcp
```

#### Available Tools

| Tool | Description | Safety |
|------|-------------|--------|
| `pw_query` | Run a ProcessWire selector, return JSON | Read-only |
| `pw_schema_read` | Read current site schema (templates/fields) | Read-only |
| `pw_schema_field_create` | Create a new field | ⚠️ Write |
| `pw_schema_template_create` | Create a new template | ⚠️ Write |
| `pw_execute` | Execute PHP code via Tinker (guarded) | ⚠️ Write |
| `pw_module_list` | List installed modules | Read-only |
| `pw_module_info` | Get detailed module info | Read-only |
| `pw_module_install` | Install a module | ⚠️ Write |
| `pw_module_uninstall` | Uninstall a module | ⚠️ Write |
| `pw_module_enable` | Enable a module | ⚠️ Write |
| `pw_module_disable` | Disable a module | ⚠️ Write |
| `pw_module_refresh` | Refresh modules | Read-only |
| `pw_module_upgrade` | Attempt module upgrade | ⚠️ Write |
| `pw_access_user_list` | List users | Read-only |
| `pw_access_user_create` | Create a new user | ⚠️ Write |
| `pw_access_user_update` | Update user email/pass/roles | ⚠️ Write |
| `pw_access_user_delete` | Delete a user | ⚠️ Write |
| `pw_access_role_create` | Create a new role | ⚠️ Write |
| `pw_access_role_grant` | Grant permission to a role | ⚠️ Write |
| `pw_access_role_revoke` | Revoke permission from a role | ⚠️ Write |
| `pw_permission_delete` | Delete a custom permission | ⚠️ Write |
| `pw_system_get_logs` | Retrieve system logs | Read-only |
| `pw_system_logs_tail_last` | Tail last N log lines | Read-only |
| `pw_system_logs_clear` | Clear a log file | ⚠️ Write |
| `pw_system_cache_clear` | Clear system caches | ⚠️ Write |
| `pw_system_cache_wire_clear` | Clear WireCache by pattern | ⚠️ Write |
| `pw_system_backup` | Create database backup | ⚠️ Write |
| `pw_system_backup_list` | List database backups | Read-only |
| `pw_system_backup_purge` | Purge old backups | ⚠️ Write |
| `pw_system_restore` | Restore from backup | ⚠️ Write |

#### MCP Config Format by Agent

**JSON — Relative paths** (Cursor, Claude Code, Copilot, Amp):
```json
{
  "mcpServers": {
    "processwire": {
      "command": "php",
      "args": ["vendor/bin/wire", "boost:mcp"]
    }
  }
}
```

**JSON — Absolute paths** (Gemini CLI, Junie):
```json
{
  "mcpServers": {
    "processwire": {
      "command": "/opt/homebrew/bin/php",
      "args": ["/Users/you/project/vendor/bin/wire", "boost:mcp"]
    }
  }
}
```

**JSON — ${workspaceFolder}** (Trae):
```json
{
  "mcpServers": {
    "processwire": {
      "command": "php",
      "args": ["${workspaceFolder}/vendor/bin/wire", "boost:mcp"]
    }
  }
}
```

**TOML format** (Codex):
```toml
[mcp_servers.processwire]
command = "php"
args = ["vendor/bin/wire", "boost:mcp"]
```

**OpenCode format** (nested command array):
```json
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "processwire": {
      "type": "local",
      "enabled": true,
      "command": ["php", "vendor/bin/wire", "boost:mcp"]
    }
  }
}
```

---

## Commands Reference

| Command | Description |
|---------|-------------|
| `boost:install` | Interactive or flag-based setup. Deploys guidelines, skills, MCP, and agent configurations |
| `boost:mcp` | Start the JSON-RPC MCP server on stdio |
| `boost:update` | Re-sync guidelines and skills from saved `.llms/boost.json` configuration |
| `boost:version` | Display Boost version information |
| `boost:build:docs` | Generate comprehensive API documentation from ProcessWire core PHPDoc |
| `boost:add-skill` | Add skills from a remote GitHub repository |

### Installer Flags

```bash
php vendor/bin/wire boost:install [OPTIONS]

Options:
  --guidelines          Install AI Guidelines
  --skills              Install Agent Skills
  --mcp                 Install MCP Server Configuration
  -a, --agents=AGENTS   Comma-separated agents (e.g. "Cursor,Claude Code")
  -m, --modules=MODULES Comma-separated modules (e.g. "Htmx,Blog")
```

When no flags are provided, the installer runs in interactive mode with multiselect prompts.

---

## Module Integration

Third-party ProcessWire modules can expose their own guidelines and skills to Boost.

### Directory Structure

Place an `llms/` directory inside your module:

```
site/modules/YourModule/
└── llms/
    ├── guidelines/
    │   └── your-module-rules.md
    └── skills/
        └── your-module-skill/
            └── SKILL.md
```

### Discovery Rules

1. Boost scans `site/modules/` and `wire/modules/` for `llms/` directories
2. Guidelines from `llms/guidelines/*.md` are compiled into the agent instruction file
3. Skills from `llms/skills/*/SKILL.md` are deployed alongside core skills
4. Fallback: if no `llms/guidelines/` exists, `llms.txt` in the module root is used

### Site-Level Overrides

Place overrides in `site/boost/` to add project-specific resources:

```
site/boost/
├── guidelines/    # Project-specific guidelines
├── skills/        # Project-specific skills
└── blueprints/    # Project-specific blueprints
```

---

## Configuration

### `boost.json`

The installer stores its state in `.llms/boost.json`:

```json
{
  "version": "1.0.0",
  "guidelines": true,
  "skills": true,
  "mcp": true,
  "modules": ["Htmx", "FieldtypeAiAssistant"],
  "agents": ["Cursor", "Claude Code", "Gemini CLI"],
  "generated_at": "2026-04-08 12:00:00"
}
```

This file is used for incremental updates — rerunning `boost:install` preserves your previous selections as defaults.

### `map.json`

A schema snapshot generated on every install containing:

- **Templates** — names, IDs, and field assignments
- **Fields** — names, IDs, types, and labels
- **Modules** — installed modules with titles and versions
- **Roles** — names, IDs, and assigned permissions
- **Permissions** — names, IDs, and titles

AI agents use this map for context-aware code generation without requiring live MCP connections.

---

## Troubleshooting

### Skills Not Appearing

```bash
# Verify skill deployment for your agent
ls -la .cursor/skills/pw-selectors/SKILL.md
ls -la .claude/skills/pw-selectors/SKILL.md
ls -la .trae/rules/pw-selectors/SKILL.md

# Reinstall skills
php vendor/bin/wire boost:install --skills --agents="Cursor"
```

### MCP Server Not Working

```bash
# Test manually
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php vendor/bin/wire boost:mcp

# Check agent config file path
cat .cursor/mcp.json     # Cursor
cat .mcp.json             # Claude Code
cat .trae/mcp.json        # Trae

# Junie requires absolute paths — verify:
cat .junie/mcp/mcp.json
# Should show full paths like /usr/local/bin/php, not just "php"
```

### Guidelines Not Updating

The merge strategy preserves your custom content. To force a full regeneration:

```bash
# Remove existing guidelines
rm AGENTS.md CLAUDE.md GEMINI.md

# Reinstall
php vendor/bin/wire boost:install --guidelines --agents="Cursor,Claude Code"
```

### ProcessWire Core Not Found

Boost requires ProcessWire's `wire/` directory to be accessible. Ensure:

```bash
# Verify wire/ exists
ls wire/core/

# Check from project root
php vendor/bin/wire list
```

---

## Composer Integration

Boost registers its commands through ProcessWire Console's Composer extra metadata. The console reads `composer.json` → `extra.processwire-console.commands` and auto-discovers command classes.

No manual registration is needed — just `composer require` and commands are available.

---

## Language Policy

All code, documentation, comments, variable names, issues, and pull request descriptions must be written in English.

---

## Contributing

Contributions are welcome. Please:

1. Open an issue describing the change
2. Follow existing code style (strict types, PHPDoc, camelCase)
3. Ensure all PHP files pass `php -l` linting
4. Test with at least two different agents

---

## License

MIT
