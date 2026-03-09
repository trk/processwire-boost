# File permissions basics
description: Choose safe directory/file permissions for your environment.
## Steps
- Prefer restrictive permissions that still allow PW to run
- Start with 755 for writable directories, 644 for writable files if Apache runs as your user
- Use 700/600 when supported; avoid 777/666 (especially shared hosting)
- Lock down /site/config.php as much as possible (e.g., 600/640/644)
- Keep /site/modules read-only unless installing from Admin
## Request
set safe permissions for writable directories and files
## Response
recommended permission set applied
## Example
```text
Directories: 755 (or 750/700 if supported)
Files:       644 (or 640/600 if supported)
/site/config.php: 600 (if possible)
```
