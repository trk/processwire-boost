# ProcessWire Boost

AI-focused context bridge for ProcessWire. Generates guidelines and skills from the local core and exposes a JSON-RPC (MCP) server to let AI agents access ProcessWire data safely.

## Features

- **Guidelines**: Build `.ai/guidelines` from core PHPDoc and best practices
- **Skills**: Task playbooks for agents (`.trae/skills/` for Trae, `.opencode/skills/` for OpenCode)
- **MCP Server**: JSON-RPC (stdio) for schema, pages, modules, access and system ops
- **Agent Integration**: Install/Agents for Cursor, Gemini CLI, Trae, OpenCode and others
- **Composer Integration**: Registers commands via processwire-console (Composer extra)

## Installation

```bash
composer require trk/processwire-boost
```

**Notes:**

- `processwire-console` is required to run commands (`vendor/bin/wire`).
- ProcessWire core (`wire/`) must exist; commands boot the core when possible.

## Quick Start

### 1. Run the installer

Interactive mode:
```bash
php vendor/bin/wire boost:install
```

Or use flags to select features directly:
```bash
# Install guidelines and skills for specific agents
php vendor/bin/wire boost:install --guidelines --skills --agents="Trae,OpenCode"

# Install only guidelines
php vendor/bin/wire boost:install --guidelines --agents="Trae"
```

Available flags:
- `--guidelines` - Install AI Guidelines
- `--skills` - Install Agent Skills  
- `--mcp` - Install MCP Server Configuration
- `--agents="Trae,OpenCode"` - Configure specific agents (comma-separated)
- `--modules="Blog,MultiLang"` - Include third-party module resources (optional)

The installer will:
- Prompt for features if no flags given (multiselect)
- Generate selected assets to `.ai/` directory
- Create agent-specific config files (`TRAE.md`, `OPENCODE.md`, etc.)
- Write MCP configurations if requested

### 2. Verify installation

```bash
# Check generated guidelines
ls .ai/guidelines/

# Check skills for agents
ls .trae/skills/      # Trae skills
ls .opencode/skills/  # OpenCode skills

# Verify boost storage
cat .ai/boost.json
```

**Expected output:**
```json
{
  "version": "1.0.0",
  "guidelines": true,
  "skills": true,
  "mcp": false,
  "modules": [],
  "agents": ["Trae", "OpenCode"],
  "generated_at": "2026-03-10 10:27:47"
}
```

### 3. Start the MCP server (optional)

For agents that support MCP:

```bash
php vendor/bin/wire boost:mcp
```

The server listens on stdio for JSON-RPC requests. The installer writes client configs (e.g., `.opencode.json` for OpenCode).

## Outputs and Sources

### Guidelines
- **Output**: `.ai/guidelines/` (foundation, boost, php, pw_core)
- **Sources**: `vendor/trk/processwire-boost/resources/boost/guidelines`

### Skills by Agent
- **Trae**: `.trae/skills/<skill-name>/SKILL.md` (flat format)
- **OpenCode**: `.opencode/skills/<skill-name>/SKILL.md` with YAML frontmatter
- **Sources**: `vendor/trk/processwire-boost/resources/builder/skills`

### Configuration Storage
- **File**: `.ai/boost.json` (key-based storage)
- **Structure**: Boolean flags for each feature + arrays for modules/agents
- **Purpose**: Tracks installed features for incremental updates

## Commands

| Command | Description |
|---------|-------------|
| `boost:install` | Interactive or flag-based setup and agent configuration |
| `boost:mcp` | Start the JSON-RPC MCP server |
| `boost:build:guides` | Generate guidelines from the local core |
| `boost:build:skills` | Generate skills from builder/skills sources |
| `boost:build:all` | Build guidelines and skills |
| `boost:scan:core` | Scan `wire/core` classes and print a summary |
| `boost:scan:modules` | Scan `wire/modules` classes and print a summary |
| `boost:scan:all` | Scan both core and modules |
| `boost:assert` | Validate generated assets for quality |

Show available commands:
```bash
php vendor/bin/wire list
php vendor/bin/wire help boost:build:all
```

## Agent Integration

### Supported Agents
- **Trae** - Writes skills to `.trae/skills/`, config in `TRAE.md`
- **OpenCode** - Writes skills to `.opencode/skills/` with YAML frontmatter, MCP config in `opencode.json`
- **Cursor, Gemini CLI, Claude Code, Codex, GitHub Copilot, Amp, Junie** - MCP configurations only

### Skill Formats

OpenCode requires YAML frontmatter:
```yaml
---
name: backup-restore
description: Create, list, purge and restore database backups safely.
license: MIT
compatibility: opencode
---
```

Trae uses plain markdown without frontmatter.

### MCP Configuration
When `--mcp` is selected, the installer:
1. Asks for path type (relative or absolute)
2. Writes MCP server config to each agent's config file
3. Command: `php vendor/bin/wire boost:mcp`

## Composer Integration (Console Command Registration)

`processwire-console` loads external commands from Composer metadata. Boost declares its commands under `extra.processwire-console.commands`. The console reads `vendor/composer/installed.json` and root `composer.json` path repositories to register these commands.

## Requirements

- PHP 8.3+
- `processwire/processwire` 3.x
- `processwire-console` (recommended for commands)

## Configuration

### Custom Skill Sources
You can extend or override skills by placing them in:
- `site/modules/*/boost/skills/*.md` - Module-specific skills
- Custom builder sources (see `SkillBuilder` class)

### Third-Party Modules
Use `--modules` flag or select interactively to include resources from:
- Site modules with a `boost/` directory
- Composer-installed modules with Boost resources

### Permissions (OpenCode)
Skills in `.opencode/skills/` are automatically discovered. To control access, add to `opencode.json`:

```json
{
  "permission": {
    "skill": {
      "*": "allow",
      "internal-*": "deny"
    }
  }
}
```

## Troubleshooting

### Skills not appearing in OpenCode
- Verify `.opencode/skills/<name>/SKILL.md` exists
- Check YAML frontmatter has `name` and `description`
- Ensure skill names are lowercase with hyphens (no underscores)
- Run `opencode debug skill` to list detected skills
- Skills size should be reasonable (< 100KB)

### MCP server issues
- Ensure `boost:mcp` command is in PATH or use absolute path
- Check agent config file (e.g., `.opencode.json`) for correct command
- Run `php vendor/bin/wire boost:mcp` manually to test

### Guidelines not generating
- Verify `wire/core` is present and readable
- Check `.ai/guidelines/` directory exists
- Run `php vendor/bin/wire boost:build:guides` to regenerate

## Development

### Rebuilding Assets
```bash
# Build all guidelines and skills
php vendor/bin/wire boost:build:all

# Or separately
php vendor/bin/wire boost:build:guides
php vendor/bin/wire boost:build:skills
```

### Testing Changes
```bash
# Clear generated assets
rm -rf .ai/ .trae/ .opencode/ TRAE.md OPENCODE.md

# Reinstall
php vendor/bin/wire boost:install --guidelines --skills --agents="Trae,OpenCode"

# Validate
php vendor/bin/wire boost:assert
```

## Language Policy

All code, documentation, examples, README content, issues and pull-request descriptions must be in English only.

## Status & Contributions

This package is under active development. Suggestions, bug reports and pull requests are welcome. Please open an issue or submit a PR.

## License

MIT
