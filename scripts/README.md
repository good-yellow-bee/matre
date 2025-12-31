# Scripts

## allure_cleanup.py

Remove test results from Allure reports with advanced filtering. Supports multi-environment batch operations and suite-based filtering.

### What It Removes

1. **Test-case file** (`data/test-cases/{uid}.json`)
2. **Behaviors entry** (`data/behaviors.json`)
3. **Suites entry** (`data/suites.json`)
4. **History entry** (`history/history.json`)

### Environment Shortcuts

| Shortcut | Environments |
|----------|--------------|
| `all` | stage-us, stage-es, preprod-us, preprod-es |
| `us` | stage-us, preprod-us |
| `es` | stage-es, preprod-es |
| `stage` | stage-us, stage-es |
| `preprod` | preprod-us, preprod-es |

### Usage Examples

```bash
# Remove test from all environments
python3 scripts/allure_cleanup.py -t MOEC8899ES --sudo

# Remove from specific suite only (e.g., group1)
python3 scripts/allure_cleanup.py -t MOEC2417 --in-suite group1 --sudo

# Remove from ES environments only
python3 scripts/allure_cleanup.py -t MOEC3758 --env es --sudo

# Remove from group1 on US environments only
python3 scripts/allure_cleanup.py -t MOEC-5173 --in-suite group1 --env us --sudo

# Keep entries in functional\us suite (remove from everywhere else)
python3 scripts/allure_cleanup.py -t MOEC5317 --not-in-suite us --sudo

# Multiple test IDs
python3 scripts/allure_cleanup.py -t MOEC3758 -t MOEC11676 --env es --sudo

# Dry run (preview changes)
python3 scripts/allure_cleanup.py -t MOEC2417 --in-suite group1 --dry-run
```

### Options

| Option | Short | Default | Description |
|--------|-------|---------|-------------|
| `--test-id` | `-t` | (required) | Test ID pattern (can use multiple times) |
| `--env` | `-e` | `all` | Environment shortcut or comma-separated list |
| `--in-suite` | | | Only remove from these suites (can use multiple times) |
| `--not-in-suite` | | | Do not remove from these suites (can use multiple times) |
| `--ssh` | `-s` | `abb` | SSH host alias (use `local` for local execution) |
| `--base-path` | `-p` | `/home/ubuntu/ABBTests/src/pub/allure-report-{env}` | Base path template |
| `--dry-run` | `-n` | | Preview changes without modifying files |
| `--sudo` | | | Use sudo for file operations |

### Example Output

```
Allure History Cleanup
==================================================
Test IDs: MOEC2417
Environments: stage-us, stage-es, preprod-us, preprod-es
Base path: /home/ubuntu/ABBTests/src/pub/allure-report-{env}
In suites: group1
SSH host: abb
Dry run: False
Use sudo: True

stage-us: Removed 236 entries
  - MOEC2417: Test for LV Drives configurators - Edit
    Suite: Magento\FunctionalTestingFramework.functional\group1
  ... and 231 more

stage-es: Removed 234 entries
  - MOEC2417: Test for LV Drives configurators - Edit
    Suite: Magento\FunctionalTestingFramework.functional\group1
  ... and 229 more

==================================================
Total: 580 entries removed across 4 environment(s)
```

### Recovery

Backups created with `.bak` extension. To restore:

```bash
ssh abb "sudo cp /path/to/report/data/behaviors.json.bak /path/to/report/data/behaviors.json"
ssh abb "sudo cp /path/to/report/data/suites.json.bak /path/to/report/data/suites.json"
ssh abb "sudo cp /path/to/report/history/history.json.bak /path/to/report/history/history.json"
```
