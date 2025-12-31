# Dev Mode (Local Module Development)

Skip git clone on each test run by using a local module directory with live edits.

---

## Configuration

Set `DEV_MODULE_PATH` in `.env`:

```env
# Relative path (from project root)
DEV_MODULE_PATH=./test-module

# Or absolute path
DEV_MODULE_PATH=/path/to/your/module
```

Leave empty for normal git clone behavior:
```env
DEV_MODULE_PATH=
```

---

## How It Works

| DEV_MODULE_PATH | Behavior |
|-----------------|----------|
| Empty/unset | Clone from `TEST_MODULE_REPO` (production) |
| Set to path | Symlink local module (dev mode) |
| Invalid path | Fail with clear error |

When dev mode is enabled, `ModuleCloneService.cloneModule()`:
1. Skips git clone entirely
2. Creates symlink: `var/test-modules/run-{id}/` → your local module
3. Changes in your local module are instantly visible to test runs

---

## Usage

### 1. Place Module in Project

```bash
# Clone your module repo
git clone git@bitbucket.org:org/my-module.git test-module

# Or symlink existing checkout
ln -s /path/to/existing/module test-module
```

### 2. Enable Dev Mode

```bash
# Add to .env
echo "DEV_MODULE_PATH=./test-module" >> .env
```

### 3. Run Tests

```bash
php bin/console app:test:run --filter=YourTestName
```

Logs will show:
```
[info] Using local module (dev mode) {"source":"./test-module","target":".../var/test-modules/run-123"}
[info] Local module symlinked successfully
```

### 4. Edit & Re-run

Make changes to files in `test-module/` and run tests again — no clone needed.

---

## Switching Modes

| Action | Command |
|--------|---------|
| Enable dev mode | `sed -i '' 's/DEV_MODULE_PATH=.*/DEV_MODULE_PATH=.\/test-module/' .env` |
| Disable dev mode | `sed -i '' 's/DEV_MODULE_PATH=.*/DEV_MODULE_PATH=/' .env` |

---

## Troubleshooting

### Error: Dev module path does not exist

```
Dev module path does not exist: /path/to/module (DEV_MODULE_PATH=./test-module)
```

**Fix:** Ensure the path exists and is readable:
```bash
ls -la ./test-module
```

### Tests use old code after changes

Symlinks should reflect changes immediately. If not:
1. Clear Symfony cache: `php bin/console cache:clear`
2. Check symlink is valid: `ls -la var/test-modules/run-*/`

---

## Related Files

| File | Purpose |
|------|---------|
| `src/Service/ModuleCloneService.php` | Dev mode logic |
| `config/services.yaml` | DI config for `$devModulePath` |
| `.env` / `.env.example` | `DEV_MODULE_PATH` variable |
