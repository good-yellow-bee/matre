# Troubleshooting

Quick solutions organized by symptom.

---

## Test Execution {#test-execution}

### "Environment is locked"

**Cause:** Another test is running on the same environment.

**Solutions:**
1. Wait for current test to complete
2. Cancel running test via Admin UI → Test Runs → Cancel
3. Check for stuck jobs:
   ```bash
   docker-compose exec php php bin/console app:cron:list
   ```

### "Module clone failed"

**Cause:** Git credentials or network issue.

**Solutions:**
1. Verify repository URL in `.env`:
   ```bash
   grep TEST_MODULE_REPO .env
   ```
2. Check credentials:
   ```bash
   grep -E "REPO_USERNAME|REPO_PASSWORD" .env
   ```
3. Test clone manually:
   ```bash
   docker-compose exec php git clone {TEST_MODULE_REPO}
   ```
4. Check network from container:
   ```bash
   docker-compose exec php ping github.com
   ```

### "Selenium unreachable"

**Cause:** Selenium Grid not running or misconfigured.

**Solutions:**
1. Check grid status:
   ```bash
   curl http://localhost:4444/status
   ```
2. Restart Selenium services:
   ```bash
   docker-compose restart selenium-hub chrome-node
   ```
3. Check container logs:
   ```bash
   docker-compose logs selenium-hub
   docker-compose logs chrome-node
   ```

### "Test timeout"

**Cause:** Test exceeded time limit.

**Solutions:**
1. Check Selenium Grid load (too many concurrent sessions)
2. Increase timeout in test configuration
3. Review test for slow operations
4. Check target Magento performance

### "MFTF binary not found"

**Cause:** Magento container not properly set up.

**Solutions:**
1. Verify Magento container running:
   ```bash
   docker-compose ps matre_magento
   ```
2. Check MFTF installation:
   ```bash
   docker-compose exec magento vendor/bin/mftf --version
   ```
3. Rebuild Magento container:
   ```bash
   docker-compose up -d --build matre_magento
   ```

---

## Reports {#reports}

### "Allure report not generated"

**Cause:** Allure service issue or no test results.

**Solutions:**
1. Check Allure service:
   ```bash
   docker-compose logs allure
   curl http://localhost:5050/allure-docker-service/version
   ```
2. Verify results exist:
   ```bash
   ls var/allure-results/run-{id}/
   ```
3. Restart Allure service:
   ```bash
   docker-compose restart allure
   ```

### "Report shows no data"

**Cause:** Results not uploaded to Allure.

**Solutions:**
1. Check test execution completed successfully
2. Verify result files generated:
   ```bash
   ls var/allure-results/run-{id}/*-result.json
   ```
3. Check test worker logs:
   ```bash
   docker-compose logs matre_test_worker | grep -i allure
   ```

### "Screenshots not loading"

**Cause:** Artifact path or permissions issue.

**Solutions:**
1. Check artifacts exist:
   ```bash
   ls var/test-artifacts/{runId}/
   ```
2. Fix permissions:
   ```bash
   chmod -R 755 var/test-artifacts
   ```
3. Verify nginx can serve files (check nginx logs)

### "Old reports still showing"

**Cause:** Cleanup not running or failed.

**Solutions:**
1. Run cleanup manually:
   ```bash
   docker-compose exec php php bin/console app:test:cleanup --dry-run
   docker-compose exec php php bin/console app:test:cleanup
   ```
2. Check cleanup job in cron list

---

## Scheduling {#scheduling}

### "Scheduled job not running"

**Cause:** Scheduler container or queue issue.

**Solutions:**
1. Check scheduler container:
   ```bash
   docker-compose ps matre_scheduler
   docker-compose logs matre_scheduler
   ```
2. Restart scheduler:
   ```bash
   docker-compose restart matre_scheduler
   ```
3. Verify job is active:
   ```bash
   docker-compose exec php php bin/console app:cron:list --active-only
   ```

### "Job stuck in 'locked' status"

**Cause:** Previous run didn't release lock (crash or timeout).

**Solutions:**
1. Wait for auto-expiry (1 hour TTL)
2. Check if job is actually running:
   ```bash
   docker-compose exec php php bin/console app:cron:list
   ```
3. Manual database cleanup (last resort):
   ```sql
   UPDATE cron_job SET last_status = 'failed' WHERE last_status = 'locked';
   ```

### "Duplicate job executions"

**Cause:** Multiple scheduler instances or locking failure.

**Solutions:**
1. Ensure only one scheduler container:
   ```bash
   docker-compose ps | grep scheduler
   ```
2. Check lock mechanism in logs

---

## Docker {#docker}

### "Container restart loop"

**Solutions:**
1. Check logs for error:
   ```bash
   docker-compose logs {container}
   ```
2. Common fixes:
   ```bash
   docker-compose down
   docker-compose up -d --build
   ```
3. Check for config errors in `.env`

### "Port already in use"

**Solutions:**
1. Find process using port:
   ```bash
   lsof -i :8089
   ```
2. Kill process or change port in `docker-compose.yml`

### "Volume permission denied"

**Solutions:**
1. Fix ownership:
   ```bash
   sudo chown -R $(id -u):$(id -g) var/
   ```
2. Or run container as current user

### "Container not starting"

**Solutions:**
1. Check Docker daemon:
   ```bash
   docker info
   ```
2. Check available disk space:
   ```bash
   df -h
   ```
3. Prune unused resources:
   ```bash
   docker system prune
   ```

---

## Database {#database}

### "Migration failed"

**Solutions:**
1. Check migration status:
   ```bash
   docker-compose exec php php bin/console doctrine:migrations:status
   ```
2. See detailed error:
   ```bash
   docker-compose exec php php bin/console doctrine:migrations:migrate -vvv
   ```
3. Force specific version:
   ```bash
   docker-compose exec php php bin/console doctrine:migrations:execute --up {version}
   ```

### "Connection refused"

**Solutions:**
1. Check database container:
   ```bash
   docker-compose ps matre_db
   ```
2. Test connection:
   ```bash
   docker-compose exec db mysqladmin ping -umatre -pmatre
   ```
3. Verify credentials in `.env`

### "Lock wait timeout"

**Cause:** Long-running transaction blocking.

**Solutions:**
1. Identify blocking query:
   ```sql
   SHOW PROCESSLIST;
   ```
2. Kill blocking process:
   ```sql
   KILL {process_id};
   ```

---

## Queue {#queue}

### "Messages not processing"

**Solutions:**
1. Check worker container:
   ```bash
   docker-compose ps matre_test_worker
   docker-compose logs matre_test_worker
   ```
2. Restart worker:
   ```bash
   docker-compose restart matre_test_worker
   ```
3. Check pending messages:
   ```bash
   docker-compose exec db mysql -umatre -pmatre -e \
     "SELECT queue_name, COUNT(*) FROM matre.messenger_messages GROUP BY queue_name;"
   ```

### "Messages in failed queue"

**Solutions:**
1. View failed messages:
   ```bash
   docker-compose exec php php bin/console messenger:failed:show
   ```
2. Retry specific message:
   ```bash
   docker-compose exec php php bin/console messenger:failed:retry {id}
   ```
3. Retry all failed:
   ```bash
   docker-compose exec php php bin/console messenger:failed:retry --all
   ```

---

## Diagnostic Commands

Quick reference for troubleshooting:

```bash
# Overall service status
docker-compose ps

# Full system health check
docker-compose exec php php bin/console app:test:check-magento

# Container logs
docker-compose logs -f {container}
docker-compose logs --tail=100 {container}

# Queue status
docker-compose exec db mysql -umatre -pmatre -e \
  "SELECT queue_name, COUNT(*) FROM matre.messenger_messages GROUP BY queue_name;"

# Check running tests
docker-compose exec db mysql -umatre -pmatre -e \
  "SELECT id, status, type FROM matre.test_run WHERE status IN ('pending', 'running');"

# View cron jobs
docker-compose exec php php bin/console app:cron:list

# Clear Symfony cache
docker-compose exec php php bin/console cache:clear

# Rebuild containers
docker-compose down && docker-compose up -d --build
```

---

## Direct Artifact Access

Access test artifacts and logs directly via filesystem:

| Directory | Contents | Example |
|-----------|----------|---------|
| `var/test-artifacts/{runId}/` | Screenshots, HTML snapshots | `var/test-artifacts/42/screenshot-001.png` |
| `var/test-output/` | MFTF execution logs | `var/test-output/mftf-run-42.log` |

### Browse Artifacts

```bash
# List artifacts for run 42
ls var/test-artifacts/42/

# View MFTF log for run 42
cat var/test-output/mftf-run-42.log

# Tail live log during execution
tail -f var/test-output/mftf-run-42.log
```

---

## Getting Help

If these solutions don't resolve your issue:

1. Check container logs for detailed error messages
2. Enable debug mode: Set `APP_DEBUG=1` in `.env`
3. Review [Installation Guide](../getting-started/installation.md) for setup issues
4. Review [Configuration](../getting-started/configuration.md) for settings
