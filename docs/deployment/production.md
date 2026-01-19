# Production Deployment

This guide covers deploying MATRE to production.

## Server Requirements

- **OS:** Ubuntu 22.04 LTS or Debian 12
- **PHP:** 8.3+ with extensions: fpm, mysql, curl, xml, mbstring, intl, zip, gd, opcache
- **Database:** MySQL 8.0+ or MariaDB 10.11+
- **Web Server:** Nginx (recommended) or Apache
- **Node.js:** 20+ (for building assets)
- **Composer:** 2.8+
- **Git**

---

## Ubuntu Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Nginx and Git
sudo apt install -y nginx git

# Add PHP PPA
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl \
  php8.3-xml php8.3-mbstring php8.3-intl php8.3-zip php8.3-gd php8.3-opcache

# Install Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## Application Setup

### 1. Create Deploy User

```bash
sudo adduser matre
sudo usermod -a -G www-data matre
```

### 2. Clone Repository

```bash
sudo -i -u matre
git clone https://github.com/good-yellow-bee/matre.git ~/matre
cd ~/matre
```

### 3. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 4. Configure Environment

Create `.env.prod.local`:

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=generate-a-strong-secret-key

DATABASE_URL="mysql://user:password@localhost:3306/matre?serverVersion=8.0"

MAILER_DSN=smtp://user:pass@smtp.example.com:587

TRUSTED_HOSTS='^your-domain\.com$'
```

Generate secret:
```bash
openssl rand -base64 32
```

### 5. Setup Database

```bash
php bin/console doctrine:database:create --env=prod
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
```

### 6. Set Permissions

```bash
sudo chown -R matre:www-data var/
sudo chmod -R 775 var/
```

---

## Nginx Configuration

Create `/etc/nginx/sites-available/matre`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /home/matre/matre/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/matre_error.log;
    access_log /var/log/nginx/matre_access.log;
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/matre /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## SSL with Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

---

## Deploy Script

```bash
# Enable maintenance mode
touch public/maintenance.flag

# Pull latest code
git pull origin master

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php bin/console doctrine:migrations:migrate --env=prod --no-interaction

# Clear cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Build assets
npm install
npm run build

# Disable maintenance mode
rm public/maintenance.flag
```

---

## Docker Production

### Build Production Image

```bash
docker build --target app_prod -t matre:prod .
```

### docker-compose.prod.yml

```yaml
services:
  php:
    build:
      context: .
      target: app_prod
    environment:
      APP_ENV: prod
      APP_DEBUG: 0
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: ${DATABASE_URL}

  nginx:
    ports:
      - "80:80"
      - "443:443"
```

### Run Production

```bash
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

---

## Docker Deployment Commands

When deploying code changes in Docker production, you need to **rebuild images** because code is baked into the container (not mounted as volumes).

### Deployment Decision Table

| Change Type | Action | Why |
|-------------|--------|-----|
| PHP only | Build + recreate app containers | Code baked into image |
| Vue/JS/CSS | Build + recreate app containers | Frontend built into PHP image via multi-stage |
| Composer deps | Build + recreate app containers | `vendor/` baked into image |
| Docker config | Full `update` via `./prod.sh` | Container configuration changed |
| DB schema | Run migrations after deploy | Schema changes only |

### Multi-Stage Build Process

The Dockerfile uses multi-stage builds for frontend assets:

```
┌─────────────────────────────┐
│  Stage: frontend_build      │
│  - npm install              │
│  - npm run build            │
│  - Output: public/build/    │
└──────────────┬──────────────┘
               │ COPY --from=frontend_build
               ▼
┌─────────────────────────────┐
│  Stage: app_prod            │
│  - composer install         │
│  - COPY app code            │
│  - COPY built frontend      │
└─────────────────────────────┘
```

This means **any Vue/JS/CSS change requires rebuilding the PHP image** to trigger the frontend build stage.

### Standard Deployment Workflow

```bash
# 1. Pull latest code
git pull origin master

# 2. Rebuild app images (triggers frontend build)
docker compose -f docker-compose.yml -f docker-compose.prod.yml build php scheduler test-worker

# 3. Deploy with recreate
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate php scheduler test-worker

# 4. Run migrations (if any)
docker exec matre_php php bin/console doctrine:migrations:migrate --no-interaction

# 5. Clear cache
docker exec matre_php php bin/console cache:clear --env=prod
```

### Key Points

- **Production uses `volumes: []`** — code is baked into image, not mounted
- **`./prod.sh update` does `pull` not `build`** — it's for pulling pre-built images from a registry. Without a registry, you must `build` locally
- **Only rebuild app containers** — `nginx`, `traefik`, `chrome-node`, `db` rarely need rebuilding
- **Cache warmup** — always clear cache after deployment to pick up new services/routes

### Quick Reference

```bash
# Rebuild and deploy (most common)
docker compose -f docker-compose.yml -f docker-compose.prod.yml build php scheduler test-worker && \
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --force-recreate php scheduler test-worker

# View build output
docker compose -f docker-compose.yml -f docker-compose.prod.yml build --progress=plain php

# Check what changed
docker compose -f docker-compose.yml -f docker-compose.prod.yml config --services
```

---

## Docker with Auto SSL (Recommended)

MATRE includes an embedded Traefik reverse proxy with automatic Let's Encrypt SSL.

### Prerequisites

- Domain pointing to server (DNS A record)
- Ports 80 and 443 open
- No other services using these ports

### Setup

1. **Configure environment:**

```bash
cat > .env.prod.local << 'EOF'
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$(openssl rand -base64 32)

# SSL Configuration
APP_DOMAIN=matre.example.com
LETSENCRYPT_EMAIL=admin@example.com
CERT_RESOLVER=letsencrypt

# Database
DB_DRIVER=pdo_mysql
DB_HOST=db
DB_PORT=3306
DB_NAME=matre
DB_USER=matre
DB_PASS=secure-password
EOF
```

2. **Validate configuration:**

```bash
docker-compose exec php php bin/console app:validate-ssl-config
```

3. **Start with production profile:**

```bash
docker-compose --profile production up -d
```

4. **Verify SSL:**

```bash
curl -I https://matre.example.com
```

### How It Works

- **Profile activation:** `--profile production` starts the embedded Traefik
- **HTTP-01 challenge:** Traefik automatically requests certs via Let's Encrypt
- **Auto-renewal:** Certificates renewed before expiry
- **HTTP redirect:** All HTTP traffic redirected to HTTPS
- **Persistent storage:** Certificates stored in `traefik_certs` volume

### Local Development

Local development is unaffected. Without `--profile production`:
- Embedded Traefik does not start
- Uses external Traefik network (if available)
- Works with self-signed certs for `*.local` domains

### Troubleshooting

**Certificate not issued:**
```bash
# Check Traefik logs
docker logs matre_traefik

# Verify DNS
dig +short matre.example.com

# Test HTTP challenge path
curl http://matre.example.com/.well-known/acme-challenge/test
```

**Rate limits:** Let's Encrypt has [rate limits](https://letsencrypt.org/docs/rate-limits/). For testing, use staging:
```yaml
# In docker/traefik/traefik.yml, add under acme:
caServer: https://acme-staging-v02.api.letsencrypt.org/directory
```

---

## Monitoring

### Application Logs
```bash
tail -f var/log/prod.log
```

### Nginx Logs
```bash
tail -f /var/log/nginx/matre_error.log
```

### PHP-FPM Status
```bash
sudo systemctl status php8.3-fpm
```

---

## Performance

### PHP OPcache

Enable in `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

### Symfony Cache

Warm up cache:
```bash
php bin/console cache:warmup --env=prod
```

---

## Backup

### Database
```bash
mysqldump -u user -p matre > backup_$(date +%Y%m%d).sql
```

### Files
```bash
tar -czf uploads_$(date +%Y%m%d).tar.gz public/uploads/
```

---

## Checklist

Before deploying:

1. [ ] Environment variables configured
2. [ ] Database created and migrations run
3. [ ] Assets built (`npm run build`)
4. [ ] Cache cleared and warmed
5. [ ] Permissions set correctly
6. [ ] SSL certificate installed
7. [ ] Backup strategy in place

---

## Troubleshooting

### Snap Docker (Ubuntu)

If Docker was installed via Ubuntu Snap, the `/opt` directory is read-only:

```bash
mkdir /opt/matre: read-only file system
```

**Solution:** Deploy to `/home/$USER/matre` instead of `/opt/matre`.

```bash
# Check if Docker is Snap-installed
snap list docker

# If yes, use home directory
mkdir -p ~/matre
cd ~/matre
git clone https://github.com/good-yellow-bee/matre.git .
```

### Symfony Runtime Missing

If you see errors like `vendor/autoload_runtime.php not found`:

```bash
# Regenerate autoloader (runs Composer plugins)
docker-compose exec php composer dump-autoload --optimize

# Clear and warm cache
docker-compose exec php php bin/console cache:clear --env=prod
docker-compose exec php php bin/console cache:warmup --env=prod
```

### Cache Permission Errors

If you see `Permission denied` for `var/cache` or `var/log`:

```bash
# Fix ownership (inside container)
docker-compose exec php chown -R www-data:www-data var

# Or from host
docker-compose exec -u root php chown -R www-data:www-data var
docker-compose exec -u root php chmod -R 775 var

# Warm cache as www-data
docker-compose exec -u www-data php php bin/console cache:warmup --env=prod
```

### Traefik 504 Gateway Timeout

If Traefik can't reach the application:

```bash
# Check nginx is on same network as Traefik
docker network inspect matre_matre_network | grep matre_nginx

# If missing, connect manually
docker network connect matre_matre_network matre_traefik_prod

# Check nginx is running
docker-compose logs nginx
```

### Database Connection Refused

```bash
# Wait for DB to be healthy
docker-compose ps db

# Check DB logs
docker-compose logs db

# Test connection
docker-compose exec php php bin/console dbal:run-sql "SELECT 1"
```

### Post-Deployment Commands

After starting containers, always run:

```bash
# Run migrations
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Create admin user (first deploy only)
docker-compose exec php php bin/console app:create-admin

# Warm cache
docker-compose exec php php bin/console cache:warmup --env=prod
```
