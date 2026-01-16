# Fix: Scheduled Test Suites Not Running

**Issue**: Test suites with cron schedules never trigger
**Root Cause**: Manual `scheduler_test_runner` transport overrides Symfony Scheduler's auto-generated virtual transport
**Date**: 2026-01-16

## Root Cause

In `config/packages/messenger.yaml`:
```yaml
scheduler_test_runner:
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'  # <-- OVERRIDES Symfony's virtual transport
```

Symfony Scheduler auto-generates `scheduler_test_runner` as a virtual transport that evaluates schedules. Manual definition replaces it with a Doctrine queue → schedule evaluation never happens.

## Fix (Dedicated Queue)

### Step 1: Update `config/packages/messenger.yaml`

**Remove** the `scheduler_test_runner` transport block (lines 24-27):
```yaml
# DELETE THIS BLOCK:
scheduler_test_runner:
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    options:
        queue_name: scheduler_test_runner
```

**Add** new dedicated transport (after `failed` transport):
```yaml
    scheduled_test_messages:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options:
            queue_name: scheduled_test_messages
        retry_strategy:
            max_retries: 2
            delay: 5000
            multiplier: 2
```

**Update** routing:
```yaml
routing:
    App\Message\ScheduledTestRunMessage: scheduled_test_messages  # was: scheduler_test_runner
```

### Step 2: Update `docker-compose.yml`

Change scheduler service command (line 108):
```yaml
scheduler:
    command: php bin/console messenger:consume scheduler_test_runner scheduler_cron scheduled_test_messages --time-limit=60 -vv
```

Note: `docker-compose.prod.yml` does NOT override scheduler command - no changes needed there.

### Step 3: Update related docs

These docs reference old transport names and need updating:
- `docs/getting-started/configuration.md` - shows `scheduler_cron` transport/routing
- `docs/operations/scheduling.md` - shows old command without `scheduler_test_runner`
- `docs/operations/monitoring.md` - mentions `scheduler_test_runner`

### Step 4: Deploy

```bash
# On remote (after committing with task number prefix per CLAUDE.md rules)
ssh abb "cd ~/matre && git pull && docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate scheduler"
```

## Verification

```bash
# 1. Check scheduler is consuming all three transports
ssh abb "cd ~/matre && docker-compose -f docker-compose.yml -f docker-compose.prod.yml logs --tail=20 scheduler | grep 'Consuming'"
# Expected: "scheduler_test_runner, scheduler_cron, scheduled_test_messages"

# 2. Check messages appear in queue when cron triggers
ssh abb "cd ~/matre && docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec -T php php bin/console dbal:run-sql \"SELECT queue_name, COUNT(*) FROM messenger_messages GROUP BY queue_name\""

# 3. Check for scheduler-triggered test runs (after cron time passes)
ssh abb "cd ~/matre && docker-compose -f docker-compose.yml -f docker-compose.prod.yml exec -T php php bin/console dbal:run-sql \"SELECT id, triggered_by, created_at FROM matre_test_runs WHERE triggered_by = 'scheduler' ORDER BY id DESC LIMIT 5\""
```

## Message Flow After Fix

```
1. Scheduler container consumes `scheduler_test_runner` (virtual)
   ↓
2. Symfony Scheduler evaluates TestRunScheduleProvider
   ↓
3. If cron matches → dispatches ScheduledTestRunMessage to bus
   ↓
4. Bus routes to `scheduled_test_messages` transport (Doctrine queue)
   ↓
5. Scheduler container also consumes `scheduled_test_messages`
   ↓
6. ScheduledTestRunMessageHandler processes message
   ↓
7. Creates TestRun with triggered_by='scheduler'
   ↓
8. Dispatches TestRunMessage to `test_runner_per_env`
   ↓
9. Test worker executes tests
```

## Why This Fix Works

| Before | After |
|--------|-------|
| `scheduler_test_runner` = Doctrine queue (manual) | `scheduler_test_runner` = Virtual transport (auto) |
| Worker idles on empty queue | Worker evaluates schedules |
| No schedule evaluation | Cron expressions checked every cycle |
| Zero scheduler-triggered runs | Tests trigger at scheduled times |

## Files to Modify

- `config/packages/messenger.yaml` - Remove override, add dedicated transport, update routing
- `docker-compose.yml` - Update scheduler command to include `scheduled_test_messages`
- `docs/getting-started/configuration.md` - Update transport/routing examples
- `docs/operations/scheduling.md` - Update command examples
- `docs/operations/monitoring.md` - Update transport references
