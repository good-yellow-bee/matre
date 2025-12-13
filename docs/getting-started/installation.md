# Installation Guide

## Prerequisites

**Docker (Recommended):**
- Docker and Docker Compose

**Manual Setup:**
- PHP 8.5+ with extensions: mbstring, xml, intl, pdo_mysql, gd, zip
- Composer 2.8+
- Node.js 20+
- MySQL 8.0+ or MariaDB 11+
- Git

---

## Docker Setup (Recommended)

### 1. Clone and Start

```bash
git clone https://github.com/good-yellow-bee/matre.git
cd matre
docker-compose up -d --build
```

This starts all services:

| Service | Description | Port |
|---------|-------------|------|
| matre_php | PHP 8.5 FPM | - |
| matre_nginx | Web server | 8089 |
| matre_db | MariaDB 11 | 33067 |
| matre_mailpit | Email testing | 1031 (SMTP), 8031 (UI) |
| matre_frontend_build | Vite asset builder | - |
| matre_scheduler | Cron job worker | - |
| matre_test_worker | Test execution worker | - |
| matre_selenium_hub | Selenium Grid hub | 4444 |
| matre_chrome_node | Chrome browser node | - |
| matre_playwright | Playwright runner | - |
| matre_allure | Allure report service | 5050 |
| matre_magento | Magento MFTF environment | - |

### 2. Frontend Build

The `matre_frontend_build` container automatically runs on startup:
- Image: `node:20-alpine`
- Command: `npm install && npm run build`
- Output: `public/build/`

The PHP container depends on frontend-build, so assets are ready before the app starts.

### 3. Database Setup

```bash
# Create database schema
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Load sample data
docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### 4. Access the Application

- **Admin Panel:** http://localhost:8089
- **Allure Reports:** http://localhost:5050
- **Selenium Grid:** http://localhost:4444
- **Mailpit UI:** http://localhost:8031
- **Database:** localhost:33067 (user: matre, password: matre)

**Default login:** `admin` / `admin123`

### Common Docker Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f matre_php
docker-compose logs -f matre_test_worker
docker-compose logs -f matre_scheduler

# Run Symfony commands
docker-compose exec php php bin/console <command>

# Open shell in PHP container
docker-compose exec php sh

# Rebuild containers
docker-compose up -d --build

# Remove volumes (reset database)
docker-compose down -v
```

---

## Manual Setup

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Configure Environment

```bash
cp .env.example .env.local
```

Edit `.env.local`:
```dotenv
DB_NAME=matre
DB_USER=matre
DB_PASS=matre
DB_HOST=127.0.0.1
DB_PORT=3306
MAILER_DSN=smtp://localhost:1025
```

### 3. Setup Database

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

### 4. Build Frontend

```bash
# Development with HMR
npm run dev

# Production build
npm run build
```

### 5. Start Server

```bash
symfony server:start
# OR
php -S localhost:8000 -t public/
```

---

## Frontend Development

For hot module replacement during development:

```bash
npm run dev -- --host 127.0.0.1 --port 5173
```

Vite dev server runs on http://localhost:5173 with HMR enabled.

The Twig helpers (`vite_entry_script_tags`) automatically detect dev mode and serve from Vite.

---

## Test Infrastructure

MATRE includes a complete test infrastructure:

### Selenium Grid
- Hub: `matre_selenium_hub` on port 4444
- Chrome node: `matre_chrome_node` with 2 sessions

### Playwright
- Container: `matre_playwright`
- Results: `var/playwright-results/`

### Magento (MFTF)
- Container: `matre_magento`
- Results: `var/mftf-results/`

### Allure Reports
- Container: `matre_allure` on port 5050
- Results: `var/allure-results/`

---

## Troubleshooting

### "Database connection failed"
- Check DB_* variables in `.env.local`
- Ensure MySQL/MariaDB is running
- Verify credentials

### "Class not found"
```bash
composer dump-autoload
php bin/console cache:clear
```

### "Migration already executed"
```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:version --delete <version>
```

### Docker: "Port already in use"
```bash
# Find process using port
lsof -i :8089

# Or change port in docker-compose.yml
```

### Test worker not processing
```bash
# Check worker logs
docker-compose logs -f matre_test_worker

# Restart worker
docker-compose restart matre_test_worker
```
