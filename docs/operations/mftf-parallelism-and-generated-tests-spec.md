# MFTF Parallelism and Generated Tests - Architecture & Troubleshooting

## Current Architecture (Already Implemented)

### Per-Environment Container Isolation
Each TestEnvironment gets its own dedicated Magento container:
- Container naming: `matre_magento_env_{environmentId}`
- Created on-demand by `MagentoContainerPoolService`
- Reused across test runs for same environment

### Tmpfs Isolation for _generated Directory
Each container has isolated tmpfs mount:
```
--mount type=tmpfs,destination=/var/www/html/dev/tests/acceptance/tests/functional/Magento/_generated
```
This prevents race conditions on generated test files between different environments.

### Multi-Level Locking (Redis Required)
| Lock | Purpose | Location |
|------|---------|----------|
| `test_runner_env_processing_{envId}` | Prevents fetching multiple messages for same env | `PerEnvironmentDoctrineReceiver` |
| `mftf_execution_env_{envId}` | Serializes execution within same environment | `TestRunnerService` |
| `container_{name}` | Prevents concurrent container creation | `MagentoContainerPoolService` |

**Critical:** All locks require Redis (`LOCK_DSN=redis://magento-redis:6379`) and the `predis/predis` package.

## Common Issue: First Test Fails with Missing _generated File

### Symptoms
```
file_get_contents(tests/functional/Magento/_generated/default/...Cest.php): Failed to open stream: No such file or directory
```
- Happens on first test in a run
- Subsequent tests pass
- Multiple runs show similar errors simultaneously

### Root Causes

#### 1. Missing predis Package (Most Common)
**Problem:** `LOCK_DSN=redis://...` is configured but `predis/predis` not installed.
- Locks fail silently or fall back to flock
- Flock doesn't work across Docker containers
- Multiple runs execute on same environment concurrently

**Fix:**
```bash
docker compose exec -T php composer require predis/predis
docker compose restart test-worker scheduler
```

**Verify:**
```bash
docker compose exec -T php composer show predis/predis
# Should show version installed
```

#### 2. Stale Containers After Code Changes
**Problem:** Container pool containers persist but may have stale code/config.

**Fix:**
```bash
# Remove pool containers
docker rm -f $(docker ps -a --filter "name=matre_magento_env_" -q)
# Containers will be recreated on next run
```

#### 2a. Missing Module Env File in Pool Container
**Symptom:**
```text
cat: can't open '/var/www/html/app/code/TestModule/Cron/data/.env.preprod-us': No such file or directory
```

**Problem:** Long-lived `matre_magento_env_*` containers can keep a stale bind mount where
`/var/www/html/app/code/TestModule` appears empty, even though `var/test-modules/current` on host has files.

**Fix (safe sequence):**
```bash
# 1) Stop consumers to avoid interrupting active runs
docker compose stop scheduler test-worker

# 2) Remove pooled Magento env containers
docker rm -f $(docker ps -a --filter "name=matre_magento_env_" -q)

# 3) Start consumers (containers will be recreated on demand)
docker compose up -d scheduler test-worker
```

**Verify inside recreated pool container (example preprod-us):**
```bash
docker exec matre_magento_env_1 sh -lc \
  'test -s /var/www/html/app/code/TestModule/Cron/data/.env.preprod-us && echo OK'
```

#### 3. LOCK_DSN Not Set to Redis
**Problem:** Using default `flock` lock which doesn't work across containers.

**Fix:** Set in `.env`:
```
LOCK_DSN=redis://magento-redis:6379
```

## Verification Steps

### Check Redis Lock is Working
```bash
# In php container
php bin/console debug:container lock.default.factory
# Should show RedisStore, not FlockStore
```

### Check Per-Environment Containers Exist
```bash
docker ps -a --filter "name=matre_magento_env_"
```

### Check Tmpfs Mount
```bash
docker inspect matre_magento_env_3 --format '{{json .Mounts}}' | jq '.[] | select(.Destination | contains("_generated"))'
# Should show Type: "tmpfs"
```

### Test Lock Isolation
1. Start two runs on same environment
2. Second should wait (blocked by lock)
3. Check logs: "Environment locked, skipping"

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Test Workers (x4)                            │
│                                                                      │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐                │
│  │Worker 1 │  │Worker 2 │  │Worker 3 │  │Worker 4 │                │
│  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘                │
│       │            │            │            │                       │
│       └────────────┴─────┬──────┴────────────┘                       │
│                          │                                           │
│                    ┌─────▼─────┐                                     │
│                    │Redis Lock │ (per-environment)                   │
│                    └─────┬─────┘                                     │
│                          │                                           │
│       ┌──────────────────┼──────────────────┐                        │
│       │                  │                  │                        │
│  ┌────▼────┐       ┌────▼────┐       ┌────▼────┐                    │
│  │ env_1   │       │ env_3   │       │ env_4   │   Per-environment  │
│  │(preprod │       │(preprod │       │(stage   │   containers       │
│  │  -us)   │       │  -es)   │       │  -es)   │                    │
│  └────┬────┘       └────┬────┘       └────┬────┘                    │
│       │                 │                 │                          │
│  ┌────▼────┐       ┌────▼────┐       ┌────▼────┐                    │
│  │ tmpfs   │       │ tmpfs   │       │ tmpfs   │   Isolated         │
│  │_generated│      │_generated│      │_generated│   test files      │
│  └─────────┘       └─────────┘       └─────────┘                    │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## Files Reference

| File | Purpose |
|------|---------|
| `src/Service/MagentoContainerPoolService.php` | Creates per-env containers with tmpfs |
| `src/Service/TestRunnerService.php` | Acquires per-env execution lock |
| `src/Messenger/Transport/PerEnvironmentDoctrineReceiver.php` | Acquires per-env message lock |
| `config/packages/lock.yaml` | Lock configuration |

## Historical Context

This architecture was implemented to solve:
1. Race conditions on `_generated` directory when multiple envs ran simultaneously
2. Artifact contamination between environments
3. Selenium session conflicts

The tmpfs mount ensures each environment's test generation is completely isolated, even when containers are reused across runs.
