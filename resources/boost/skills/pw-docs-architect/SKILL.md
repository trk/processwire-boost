---
name: pw-docs-architect
description: "Use when creating or updating module documentation, package READMEs, architecture guides, or CLI command references for ProcessWire projects."
risk: safe
source: processwire-boost
date_added: "2026-04-08"
---

# ProcessWire Docs Architect

Create comprehensive, long-form technical documentation for ProcessWire modules, packages, and systems. Captures both the **what** and the **why** of every component.

## When to Use

- Writing a module README from scratch
- Documenting architecture decisions for a ProcessWire package
- Creating CLI command reference for `vendor/bin/wire` commands
- Generating onboarding documentation for a ProcessWire project
- Writing module installation and configuration guides
- Documenting URL hook endpoints and MCP tool interfaces

## Documentation Process

### Phase 1: Discovery

Before writing anything, analyze the codebase:

```
# Understand the schema
pw_schema_read

# List installed modules
pw_module_list

# Check module structure
ls site/modules/MyModule/

# Review existing docs
cat site/modules/MyModule/README.md
```

Key questions to answer:
- What templates and fields does this module create?
- What hooks does it register?
- What CLI commands does it provide?
- What permissions/roles does it require?
- What other modules does it depend on?

### Phase 2: Structuring

Organize documentation with progressive disclosure:

```
1. Executive Summary        → 1 paragraph, what it does
2. Installation             → Composer, module install, config
3. Quick Start              → Working example in 30 seconds
4. Configuration            → All module config options
5. Usage                    → Core features with examples
6. CLI Commands             → Full command reference
7. Architecture             → How it works internally
8. API Reference            → Hooks, methods, events
9. Security                 → RBAC, sanitization, CSRF
10. Troubleshooting         → Common issues and solutions
```

### Phase 3: Writing

Follow these ProcessWire-specific conventions:

#### Module README Structure

```markdown
# ModuleName

> One-line description of what the module does.

## Requirements

- ProcessWire >= 3.0.200
- PHP >= 8.3
- [Other module dependencies]

## Installation

\`\`\`bash
composer require vendor/module-name
\`\`\`

Then install via ProcessWire admin:
**Modules → Refresh → Find → Install**

Or via CLI:
\`\`\`bash
wire module:install ModuleName
\`\`\`

## Configuration

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `apiKey` | string | `''` | API key for external service |
| `cacheTime` | int | `3600` | Cache duration in seconds |

## Quick Start

\`\`\`php
// In a template file
$result = $modules->get('ModuleName')->process($page);
echo $result->output;
\`\`\`

## Features

### Feature A

Description with code example...

### Feature B

Description with code example...
```

#### API.md (Module API Reference)

For modules exposing public methods, always create an `API.md` file alongside `README.md`.

```markdown
# MyModuleClass

> API reference for interacting with MyModule from templates or hooks.

## Value Types
- Returns `\ProcessWire\WireArray` for grouped items.
- Returns `\ProcessWire\NullPage` on search failure.

## Selectors
\`\`\`php
$results = $modules->get('MyModuleClass')->find("status=active");
\`\`\`
```

#### Fieldtype IDE Companion Class

For Fieldtype modules, document properties by creating a companion `[Type]Field.php` class with PHPDoc annotations. This is how IDEs understand the Field settings.

```php
<?php namespace ProcessWire;
/**
 * Companion class for IDE autocompletion 
 * 
 * @property int $maxLength Maximum string length
 * @property string $defaultText Default fallback text
 */
class FieldtypeMyCustomField extends Field {}
```

#### CLI Command Documentation

```markdown
## CLI Commands

### `domain:action`

Brief description of what this command does.

**Usage:**
\`\`\`bash
wire domain:action [options] [arguments]
\`\`\`

**Arguments:**
| Argument | Required | Description |
|----------|----------|-------------|
| `name` | Yes | The resource name |

**Options:**
| Option | Short | Default | Description |
|--------|-------|---------|-------------|
| `--json` | `-j` | `false` | Output as JSON |
| `--dry-run` | | `false` | Preview without changes |
| `--force` | `-f` | `false` | Skip confirmation |

**Examples:**
\`\`\`bash
# Basic usage
wire domain:action my-resource

# With JSON output
wire domain:action my-resource --json

# Force without confirmation
wire domain:action my-resource --force
\`\`\`
```

#### Hook/Endpoint Documentation

```markdown
## Hooks

### `Pages::saved`

Triggered after a page is saved. Updates the search index.

\`\`\`php
$wire->addHookAfter('Pages::saved', function (HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'article') return;

    $indexer = $event->wire()->modules->get('SearchIndexer');
    $indexer->reindex($page);
});
\`\`\`

### URL Hook: `/api/search/`

Returns search results as JSON.

**Method:** GET
**Authentication:** None required
**Rate Limit:** 60/minute

**Parameters:**
| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `q` | string | Yes | Search query (sanitized via `selectorValue`) |
| `limit` | int | No | Max results (default: 20, max: 100) |

**Response:**
\`\`\`json
{
  "count": 5,
  "results": [
    {"id": 1042, "title": "Example", "url": "/blog/example/"}
  ]
}
\`\`\`
```

#### Architecture Documentation

```markdown
## Architecture

### Overview

Brief description of how the module works internally.

### Data Flow

\`\`\`
User Input → $sanitizer → Selector Query → $pages->find()
                                                ↓
                                         Page Processing
                                                ↓
                                    Template Rendering → Output
\`\`\`

### Database Schema

This module creates the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `ai_summary` | FieldtypeTextarea | AI-generated page summary |
| `ai_status` | FieldtypeOptions | Processing status (pending/done/error) |

### Design Decisions

**Why Page References instead of Repeaters?**
Page references allow cross-template querying and independent lifecycle management.
Repeaters would constrain data to a single parent context.

**Why URL hooks instead of template-based routing?**
The API endpoints have no corresponding page tree structure.
URL hooks provide cleaner separation between data API and content pages.
```

## Quality Checklist

Before publishing documentation:

- [ ] `API.md` is generated for modules exposing public API methods
- [ ] `[Type]Field.php` companion class is created for Fieldtypes
- [ ] Executive summary exists and is clear in 1 paragraph
- [ ] Installation steps are copy-paste ready
- [ ] Quick Start example works within 30 seconds
- [ ] All CLI commands have usage, options, and examples
- [ ] All hooks/endpoints have parameters and response format
- [ ] Code examples use `$sanitizer` for user input
- [ ] RBAC requirements are documented
- [ ] Module config options are in a table
- [ ] Requirements (PHP/PW version, dependencies) are listed
- [ ] Architecture section explains "why" not just "what"
- [ ] No Laravel/Artisan/Eloquent terminology

## Anti-Patterns

- ❌ "See source code for details" — always document inline
- ❌ Placeholder text like "TODO" or "coming soon"
- ❌ Laravel framework examples in PW module docs
- ❌ Missing installation steps
- ❌ Code examples without context (which file? template? module?)
- ❌ Undocumented config options
- ❌ Missing error/edge case documentation

## Output Characteristics

- **Length:** Proportional to module complexity (1-50+ pages)
- **Style:** Technical but accessible, progressive complexity
- **Format:** Markdown with tables, code blocks, and diagrams
- **Audience:** ProcessWire developers with varying experience levels
