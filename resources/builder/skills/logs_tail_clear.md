# Logs Tail & Clear
description: Tail or clear ProcessWire logs from CLI or MCP tools.
## When to use
- Inspect recent errors; provide excerpts; clear noisy logs
## CLI
- Tail: php vendor/bin/wire logs:tail --file errors --lines 200 -f
- Clear: php vendor/bin/wire logs:clear --file errors --force
## MCP
- Last N lines: {"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"pw_system_logs_tail_last","arguments":{"name":"errors","lines":150}}}
- Recent entries: {"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"pw_system_get_logs","arguments":{"name":"errors","limit":20}}}
## Notes
- For long-running watch, prefer CLI tail -f
