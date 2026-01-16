# MOEC-12902: MATRE Deployment to AWS

## Task Summary

**Ticket:** MOEC-12902 - Update Allure
**Estimate:** 16h (2 days)
**Assignee:** Piotr Francuz

---

## Overview

Replace custom test execution code on ABB MFTF server with MATRE - open-source test orchestration platform providing stable, maintainable test management.

**Repository:** https://github.com/good-yellow-bee/matre

## Current vs Target State

| Current | Target |
|---------|--------|
| Custom code, not stable | MATRE - stable, maintainable |
| Hardly manageable | Admin panel for full control |
| Old Allure version | Latest Allure with basic auth |
| No live preview | noVNC live browser preview |
| Manual test runs | Cron scheduling + on-demand |

## Key Features for ABB

- Service version management (MFTF, Allure, Chrome)
- Execution log inspection
- Dual reporting interface (admin panel + standard Allure)
- Single test execution on demand
- Live browser preview during test runs
- **No change to ABB workflow** - same Allure reports, newer version

## Technical Details

| Setting | Value |
|---------|-------|
| Server | i-05f57e49af44919d5 (t2.xlarge, eu-central-1) |
| Domain | 35-158-214-255.com |
| IP | 3.67.180.58 |
| SSL | Let's Encrypt |
| Auth | MATRE login + Allure basic auth (separate) |

## Implementation Phases

| Phase | Task | Estimate |
|-------|------|----------|
| 1 | Local preparation - verify master, test config | 2h |
| 2 | Server discovery - SSH, SMTP config, cron docs | 2h |
| 3 | Server setup - Docker, .env, SSL, Allure auth | 5h |
| 4 | Data migration - admin, environments, suites, cron | 3h |
| 5 | Verification - full test cycle, all environments | 3h |
| 6 | Cutover - notify ABB, monitor, cleanup | 1h |
| | **Total** | **16h** |

## Acceptance Criteria

- [ ] MATRE accessible via HTTPS at 35-158-214-255.com
- [ ] Allure reports protected with basic auth
- [ ] Test execution works for all 4 environments
- [ ] Scheduled runs trigger via cron
- [ ] Email notifications working
- [ ] Live browser preview functional
- [ ] Module clone from Bitbucket production branch works

---

# Detailed Step-by-Step Instructions

## Phase 1: Local Preparation (2h)

### 1.1 Verify MATRE master branch is stable

```bash
cd /path/to/matre
git checkout master
git pull origin master

# Run tests
docker-compose up -d
docker-compose exec php bin/phpunit

# Check for any pending issues
git log --oneline -10
```

### 1.2 Test fresh clone workflow (simulate production)

```bash
# Temporarily disable dev mode
# In .env, ensure:
DEV_MODULE_PATH=

# Run a test to verify git clone works
docker-compose exec php php bin/console app:test:run --filter="SomeTestName" --sync
```

### 1.3 Create production config checklist

Required `.env` variables for production:
```env
# App
APP_ENV=prod
APP_SECRET=<generate-new-secret>

# Database
DB_HOST=db
DB_PORT=3306
DB_NAME=matre
DB_USER=matre
DB_PASS=<strong-password>

# Test Module
TEST_MODULE_REPO=https://bitbucket.sii.pl/scm/abb/abb-custom-mftf.git
TEST_MODULE_BRANCH=production
TEST_MODULE_PATH=app/code/SiiPoland/Catalog
DEV_MODULE_PATH=

# Bitbucket credentials
REPO_USERNAME=<bitbucket-user>
REPO_PASSWORD=<bitbucket-app-password>

# URLs (update for production domain)
ALLURE_URL=http://allure:5050
ALLURE_PUBLIC_URL=https://35-158-214-255.com:5050
NOVNC_URL=http://35-158-214-255.com:7900

# Email (discover from server)
MAILER_DSN=smtp://user:pass@smtp.server:port
```

---

## Phase 2: Server Discovery (2h)

### 2.1 SSH to server

```bash
ssh abb
# or
ssh -i ~/.ssh/your-key.pem ec2-user@3.67.180.58
```

### 2.2 Check existing SMTP configuration

```bash
# Look for mail config in common locations
cat /etc/msmtprc 2>/dev/null
cat ~/.msmtprc 2>/dev/null
cat /etc/ssmtp/ssmtp.conf 2>/dev/null
env | grep -i mail
env | grep -i smtp

# Check if any mail service is running
systemctl list-units | grep -i mail
ps aux | grep -i mail

# Check existing app configs
find /home -name "*.env" 2>/dev/null | xargs grep -l SMTP
find /var/www -name "*.env" 2>/dev/null | xargs grep -l SMTP
```

### 2.3 Document current cron jobs

```bash
# User crontabs
crontab -l
sudo crontab -l

# System cron
ls -la /etc/cron.d/
cat /etc/cron.d/*

# Document what tests run and when
# Note the schedule patterns for migration to MATRE
```

### 2.4 Check current services

```bash
# Docker containers
docker ps -a

# Running services
systemctl list-units --type=service --state=running

# Disk usage (ensure space for MATRE)
df -h

# Memory usage
free -h
```

### 2.5 Backup existing data (if needed)

```bash
# Backup Allure reports if needed for reference
tar -czvf allure-backup-$(date +%Y%m%d).tar.gz /path/to/allure-reports

# Save cron config
crontab -l > cron-backup-$(date +%Y%m%d).txt
```

---

## Phase 3: Server Setup (5h)

### 3.1 Install Docker & Docker Compose (if not present)

```bash
# Check if Docker exists
docker --version
docker-compose --version

# If not installed (Amazon Linux 2):
sudo yum update -y
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Re-login for group changes
exit
ssh abb
```

### 3.2 Clone MATRE repository

```bash
cd /opt  # or preferred location
sudo git clone https://github.com/good-yellow-bee/matre.git
sudo chown -R $USER:$USER matre
cd matre
```

### 3.3 Configure production environment

```bash
# Copy example env
cp .env.example .env

# Edit with production values
nano .env

# Generate app secret
php -r "echo bin2hex(random_bytes(16));"
# Or use: openssl rand -hex 16
```

**Key .env changes for production:**
```env
APP_ENV=prod
APP_SECRET=<generated-secret>
DB_PASS=<strong-password>

TEST_MODULE_REPO=https://bitbucket.sii.pl/scm/abb/abb-custom-mftf.git
TEST_MODULE_BRANCH=production
TEST_MODULE_PATH=app/code/SiiPoland/Catalog
DEV_MODULE_PATH=

REPO_USERNAME=<your-bitbucket-user>
REPO_PASSWORD=<your-bitbucket-app-password>

# Update URLs for production
ALLURE_PUBLIC_URL=https://35-158-214-255.com/allure
NOVNC_URL=http://35-158-214-255.com:7900

# SMTP from Phase 2 discovery
MAILER_DSN=smtp://...
```

### 3.4 Configure SSL with Let's Encrypt (Traefik)

Create/update `docker/traefik/traefik.yml`:
```yaml
api:
  dashboard: true

entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
  websecure:
    address: ":443"

certificatesResolvers:
  letsencrypt:
    acme:
      email: your-email@sii.pl
      storage: /letsencrypt/acme.json
      httpChallenge:
        entryPoint: web

providers:
  docker:
    exposedByDefault: false
  file:
    directory: /etc/traefik/dynamic
```

### 3.5 Configure Allure basic auth

Generate password hash:
```bash
# Install htpasswd if needed
sudo yum install -y httpd-tools  # Amazon Linux
# or: sudo apt install apache2-utils  # Ubuntu

# Generate hash (replace 'allure-user' and 'allure-password')
htpasswd -nb allure-user allure-password
# Output: allure-user:$apr1$xxxxx...
```

Create `docker/traefik/dynamic/allure-auth.yml`:
```yaml
http:
  middlewares:
    allure-auth:
      basicAuth:
        users:
          - "allure-user:$apr1$xxxxx..."  # paste hash from htpasswd
```

### 3.6 Create production docker-compose override

Create `docker-compose.prod.yml`:
```yaml
version: '3.8'

services:
  traefik:
    image: traefik:v2.10
    container_name: matre_traefik
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./docker/traefik/traefik.yml:/etc/traefik/traefik.yml:ro
      - ./docker/traefik/dynamic:/etc/traefik/dynamic:ro
      - ./docker/traefik/letsencrypt:/letsencrypt
    networks:
      - matre_network

  php:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.matre.rule=Host(`35-158-214-255.com`)"
      - "traefik.http.routers.matre.entrypoints=websecure"
      - "traefik.http.routers.matre.tls.certresolver=letsencrypt"

  allure:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.allure.rule=Host(`35-158-214-255.com`) && PathPrefix(`/allure`)"
      - "traefik.http.routers.allure.entrypoints=websecure"
      - "traefik.http.routers.allure.tls.certresolver=letsencrypt"
      - "traefik.http.routers.allure.middlewares=allure-auth@file"
      - "traefik.http.services.allure.loadbalancer.server.port=5050"
    ports: []  # Remove direct port exposure

networks:
  matre_network:
    driver: bridge
```

### 3.7 Build and start containers

```bash
# Stop existing services that might conflict
sudo systemctl stop <old-services>

# Build and start MATRE
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# Check status
docker-compose ps

# View logs
docker-compose logs -f php
docker-compose logs -f allure
```

---

## Phase 4: Data Migration (3h)

### 4.1 Create admin user

```bash
docker-compose exec php php bin/console app:create-admin
# Follow prompts for username, email, password
```

### 4.2 Import test environments

Option A: Via admin UI
1. Login to https://35-158-214-255.com/admin
2. Go to Test Environments
3. Add each Magento environment with credentials

Option B: Via command (if bulk import exists)
```bash
docker-compose exec php php bin/console app:import-environments environments.json
```

**Environment data needed for each:**
- Name (e.g., "ABB Production", "ABB Staging")
- Magento URL
- Admin URL
- Admin credentials
- Any environment-specific variables

### 4.3 Import global env variables

```bash
# Clone test module and import variables
docker-compose exec php php bin/console app:env:import --clone

# Or for specific environment file:
docker-compose exec php php bin/console app:env:import production --clone
```

### 4.4 Configure test suites

Via admin UI:
1. Go to Test Suites
2. Create suites matching current cron schedule
3. Assign tests to each suite
4. Set cron expression (e.g., `0 2 * * *` for daily at 2 AM)

### 4.5 Install cron jobs

```bash
docker-compose exec php php bin/console app:cron:install

# Verify cron is installed
crontab -l
```

---

## Phase 5: Verification (3h)

### 5.1 Access MATRE UI

```bash
# Open in browser
https://35-158-214-255.com

# Verify:
# - HTTPS works (no certificate warnings)
# - Login page loads
# - Can login with admin credentials
```

### 5.2 Run single test manually

```bash
# Via UI: Test Runs > New Run > Select single test > Execute

# Or via CLI:
docker-compose exec php php bin/console app:test:run \
  --filter="YourTestName" \
  --environment=1 \
  --sync
```

**Verify:**
- Test starts executing
- Progress updates in UI
- Results appear when complete

### 5.3 Check Allure report generation

```bash
# After test completes, access Allure
https://35-158-214-255.com/allure

# Verify:
# - Basic auth prompt appears
# - Can login with Allure credentials
# - Report shows test results
# - Screenshots/attachments visible
```

### 5.4 Verify live browser preview (noVNC)

```bash
# During test execution:
# 1. Go to running test in UI
# 2. Click "Watch Live" button
# 3. noVNC viewer should open

# Direct access:
http://35-158-214-255.com:7900
```

### 5.5 Test scheduled run

```bash
# Trigger cron manually
docker-compose exec php php bin/console app:cron:run

# Or wait for scheduled time and verify:
# - Test suite starts automatically
# - Email notification sent on completion
```

### 5.6 Verify email notifications

```bash
# Check mailer logs
docker-compose logs php | grep -i mail

# Verify email received after test completion
```

### 5.7 Test all 4 environments

Repeat test execution for each configured environment:
1. Environment 1 (e.g., Production)
2. Environment 2 (e.g., Staging)
3. Environment 3
4. Environment 4

---

## Phase 6: Cutover (1h)

### 6.1 Notify ABB team

Send notification with:
- New Allure URL: https://35-158-214-255.com/allure
- Allure credentials (basic auth)
- Any schedule changes
- Contact for issues

### 6.2 Monitor first automated runs

```bash
# Watch logs during first scheduled runs
docker-compose logs -f matre_test_worker

# Check for issues:
# - Module clone working
# - Tests executing properly
# - Reports generating
# - Emails sending
```

### 6.3 Remove old test execution code

```bash
# Once verified working, clean up old system
# CAUTION: Only after confirming MATRE is stable

# Stop old services
sudo systemctl stop <old-test-service>
sudo systemctl disable <old-test-service>

# Archive old code (don't delete immediately)
sudo mv /path/to/old-code /path/to/old-code.bak-$(date +%Y%m%d)
```

---

## Troubleshooting

### SSL Certificate Issues
```bash
# Check Traefik logs
docker-compose logs traefik

# Verify acme.json permissions
ls -la docker/traefik/letsencrypt/acme.json
# Should be 600

# Force certificate renewal
rm docker/traefik/letsencrypt/acme.json
docker-compose restart traefik
```

### Module Clone Fails
```bash
# Test Bitbucket credentials
git clone https://USERNAME:PASSWORD@bitbucket.sii.pl/scm/abb/abb-custom-mftf.git /tmp/test-clone

# Check REPO_USERNAME and REPO_PASSWORD in .env
# Ensure Bitbucket App Password has repo read permissions
```

### Allure Not Generating Reports
```bash
# Check Allure container
docker-compose logs allure

# Verify results directory
ls -la var/allure-results/

# Manual report generation
docker-compose exec php php bin/console app:allure:generate
```

### Email Not Sending
```bash
# Test SMTP connection
docker-compose exec php php bin/console debug:container mailer

# Send test email
docker-compose exec php php bin/console app:test-email recipient@example.com
```

---

## Rollback Plan

If critical issues arise:

1. Stop MATRE services
   ```bash
   docker-compose down
   ```

2. Restore old system
   ```bash
   sudo mv /path/to/old-code.bak-YYYYMMDD /path/to/old-code
   sudo systemctl start <old-test-service>
   ```

3. Notify team of rollback

4. Investigate issues before retry
