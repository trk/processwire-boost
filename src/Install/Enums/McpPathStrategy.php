<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Install\Enums;

/**
 * Determines how MCP server paths are resolved in agent configuration files.
 *
 * - Relative:        `php vendor/bin/wire boost:mcp` — portable, assumes cwd is project root.
 * - Absolute:        `/usr/bin/php /full/path/vendor/bin/wire boost:mcp` — machine-specific.
 * - WorkspaceFolder: `php ${workspaceFolder}/vendor/bin/wire boost:mcp` — IDE variable.
 */
enum McpPathStrategy: string
{
    /** Relative paths from project root (default for most agents) */
    case Relative = 'relative';

    /** Absolute filesystem paths (for agents that cannot resolve relative paths) */
    case Absolute = 'absolute';

    /** IDE workspace variable `${workspaceFolder}` (for IDEs like Trae that resolve this) */
    case WorkspaceFolder = 'workspace_folder';
}
