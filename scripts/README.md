# Scripts

## allure_cleanup.py

Completely remove test results from Allure reports by test ID pattern.

### What It Removes

1. **Test-case file** (`data/test-cases/{uid}.json`)
2. **Behaviors entry** (`data/behaviors.json`)
3. **Suites entry** (`data/suites.json`)
4. **History entry** (`history/history.json`)

### Usage

```bash
# Dry run (preview what will be removed):
python allure_cleanup.py --ssh abb --path /path/to/allure-report --test-id "TEST-123" --dry-run

# Execute cleanup with sudo (required for permission issues):
python allure_cleanup.py --ssh abb --path /path/to/allure-report --test-id "TEST-123" --sudo

# Multiple tests:
python allure_cleanup.py --ssh abb --path /path/to/report --test-id "TEST-123" --test-id "TEST-456" --sudo

# Local execution (no SSH):
python allure_cleanup.py --path /local/path/to/allure-report --test-id "TEST-123"
```

### Options

| Option | Short | Required | Description |
|--------|-------|----------|-------------|
| `--path` | `-p` | Yes | Path to allure report directory |
| `--test-id` | `-t` | Yes | Test ID pattern (can use multiple times) |
| `--ssh` | `-s` | No | SSH host alias from ~/.ssh/config |
| `--dry-run` | `-n` | No | Preview changes without modifying files |
| `--sudo` | | No | Use sudo for file operations |

### Example Output

```
Allure History Cleanup
==================================================
Report path: /home/ubuntu/ABBTests/src/pub/allure-report-stage-us
SSH host: abb
Test IDs: MOEC-2609US
Dry run: False
Use sudo: True

Step 1: Searching for tests in report...
  Found 1 matching test(s):
    - MOEC-2609US: UAT Product Details Page
      UID: 5cbfffa5c14812ad, Status: broken, Retries: 24

Step 2: Finding history IDs...
  Found 1 history ID(s):
    - 171e13759e7d9b98c19bda7a48a75381

Step 3: Removing test-case files...
  Removed: .../data/test-cases/5cbfffa5c14812ad.json

Step 4: Removing from behaviors.json...
  Removed 1 entries from behaviors.json (backup created)

Step 5: Removing from suites.json...
  Removed 1 entries from suites.json (backup created)

Step 6: Removing from history.json...

Done! Complete cleanup finished
```

### Recovery

Backups created with `.bak` extension. To restore:
```bash
ssh abb "sudo cp /path/to/report/data/behaviors.json.bak /path/to/report/data/behaviors.json"
ssh abb "sudo cp /path/to/report/data/suites.json.bak /path/to/report/data/suites.json"
ssh abb "sudo cp /path/to/report/history/history.json.bak /path/to/report/history/history.json"
```
