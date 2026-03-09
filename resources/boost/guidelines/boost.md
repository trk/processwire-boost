# ProcessWire Boost Rules

ProcessWire Boost provides an MCP server and powerful tools specifically for this application.

## CLI & MCP Server

- Use the `pw_query` tool when you need to run a ProcessWire selector and return JSON.
- Use `pw_execute` to run PHP code directly via Tinker.
- Use `pw_schema_read` to understand the site's templates and fields.
- Access system logs via `pw_system_get_logs`. Use this for debugging errors.

## Searching Documentation

- Always use the `search-docs` tool (when available) before taking other approaches. This tool provides version-specific context for ProcessWire components.
- Search the developer documentation at `processwire.com/docs/` for stable API references.

## Selectors

- Prefer `id`, `name`, or `template` for identifying pages.
- Always include `limit` when fetching large results.
- Use `include=all` or `check_access=0` only when necessary and secure.
