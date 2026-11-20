# Github Workflow

### Table of contents

- [Github Workflow](#github-workflow)
    - [Table of contents](#table-of-contents)
  - [Routine (before start working on your ticket)](#routine-before-start-working-on-your-ticket)
  - [Work w/ tickets!](#work-w-tickets)
  - [Pushing your changes](#pushing-your-changes)
  - [Branch behind? do rebase!](#branch-behind-do-rebase)
  - [Still in master branch?](#still-in-master-branch)
  - [Merge to master (for reviewers)](#merge-to-master-for-reviewers)

## Routine (before start working on your ticket)

1. Check for Changes on master

_if you are on another branch_
```bash
git fetch origin master
git rebase origin/master
```

_but if on master_
```bash
git fetch origin master
git pull
```

2. Do Migration
```bash
php artisan migrate:fresh
php artisan migrate --seed
```
3. Install new dependencies
```bash
npm i 
npm run build
composer install
```




## Work w/ tickets!

```bash
git checkout -b RK-<ticket number> origin/master
git push -u origin RK-<ticket number>
```

1. Go to projects kanban board
2. Locate your ticket
3. Right sidebar scroll down to development
4. Connect your ticket

## Pushing your changes

_Please refer to [Conventional commits](https://www.conventionalcommits.org/en/v1.0.0/) for the format of your commit messages_

```bash
git add .
git commit -m "your message here"
git push origin <current branch name>
```

1. Create a PR (Pull Request)
2. Move the ticket to "For review" column
3. In case of errors:

```bash
git push origin <current branch name> --force
```

## Branch behind? do rebase!

_Do this first before pushing your changes if you are behind!_

```bash
git fetch origin master
git rebase origin/master
```

_Stash if you can't rebase_

```bash
git stash
git fetch origin master
git rebase origin/master
git stash pop
```

**_Fix possible merge conflicts_**

## Still in master branch?

_Move to a remote branch_

```bash
git checkout -b D20-<ticket number> origin/master
```

_If you can't move, stash!_

```bash
git stash
git checkout -b D20-<ticket number> origin/master
git stash pop
```

## Merge to master (for reviewers)

1. Do not merge!
2. Press the dropdown beside merge button
3. Click rebase & merge

**_If you have problems with the workflow never hesitate to ask questions_**

**_1 ticket 1 branch rule, make sure to only have 1 branch for each ticket for easier monitoring and reviews_**

---

# Deployment

### Table of contents

- [Docker with Sail](#docker-with-sail)
- [Docker without Sail](#docker-without-sail)
- [Manual Deployment](#manual-deployment)

### Required environment variables

Regardless of deployment method, make sure the following are set in your `.env`:

| Variable | Description |
|---|---|
| `APP_KEY` | Generated via `artisan key:generate` |
| `APP_URL` | Full URL including scheme (e.g. `https://example.com`) |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL connection |
| `REDIS_HOST` / `REDIS_PASSWORD` | Redis connection |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Google OAuth |
| `RESEND_API_KEY` | Transactional email via Resend |
| `SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD` | Seeded super-admin credentials |
| `OCR_WEBHOOK_SECRET` | Webhook secret for OCR callbacks |

---

## Docker with Sail

> **Prerequisites:** Docker Desktop (or Docker Engine + Compose plugin)

**1. Copy and configure environment**
```bash
cp .env.example .env
# Edit .env — set APP_URL, DB_*, REDIS_*, GOOGLE_*, RESEND_*, etc.
```

**2. Install PHP dependencies using a temporary Composer container (no local PHP needed)**
```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --no-interaction --no-progress
```

**3. Start the stack**
```bash
./vendor/bin/sail up -d
```

**4. Bootstrap the application**
```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

The application is now available at `http://localhost` (or the `APP_PORT` you configured).

**Useful Sail commands**
```bash
./vendor/bin/sail down          # stop containers
./vendor/bin/sail logs          # tail all logs
./vendor/bin/sail artisan ...   # run any artisan command
./vendor/bin/sail shell         # open a shell inside the app container
```

---

## Docker without Sail

> **Prerequisites:** Docker Engine + Compose v2 (`docker compose` — not `docker-compose`)

**1. Export host user/group IDs** (required by `compose.yaml`)
```bash
export WWWUSER=$(id -u)
export WWWGROUP=$(id -g)
```

**2. Copy and configure environment**
```bash
cp .env.example .env
# Edit .env — set APP_URL, DB_HOST=mysql, REDIS_HOST=redis, etc.
```

**3. Build and start the stack**
```bash
docker compose build
docker compose up -d
```

**4. Bootstrap the application**
```bash
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed
docker compose exec laravel.test npm install
docker compose exec laravel.test npm run build
```

**Useful commands**
```bash
docker compose down                              # stop containers
docker compose logs -f laravel.test             # tail app logs
docker compose exec laravel.test php artisan ... # run artisan commands
docker compose exec laravel.test bash           # open a shell
```

---

## Manual Deployment

> **Prerequisites (Ubuntu/Debian):** PHP 8.4, Composer, Node 22+, MySQL 8.0, Redis, Tesseract OCR, Poppler Utils, Supervisor, Nginx

**1. Install system dependencies**
```bash
sudo apt update && sudo apt install -y \
    php8.4 php8.4-fpm php8.4-cli php8.4-mbstring php8.4-xml php8.4-bcmath \
    php8.4-curl php8.4-zip php8.4-mysql php8.4-redis php8.4-gd php8.4-intl \
    php8.4-pcov \
    composer nodejs npm \
    mysql-server redis-server \
    tesseract-ocr poppler-utils \
    supervisor nginx
```

**2. Clone and configure**
```bash
git clone <repo-url> /var/www/chedonse-racks
cd /var/www/chedonse-racks
cp .env.example .env
# Edit .env — set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, REDIS_*, etc.
```

**3. Install dependencies and build assets**
```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

**4. Bootstrap the application**
```bash
php artisan key:generate
php artisan migrate --seed --force
php artisan optimize
```

**5. Set file permissions**
```bash
chown -R www-data:www-data /var/www/chedonse-racks
chmod -R 755 /var/www/chedonse-racks
chmod -R 775 /var/www/chedonse-racks/storage /var/www/chedonse-racks/bootstrap/cache
```

**6. Configure Nginx**

Create `/etc/nginx/sites-available/chedonse-racks`:
```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/chedonse-racks/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
```bash
ln -s /etc/nginx/sites-available/chedonse-racks /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

**7. Configure Supervisor queue workers**

Create `/etc/supervisor/conf.d/chedonse-racks.conf`:
```ini
[program:queue-worker]
command=php /var/www/chedonse-racks/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/queue-worker.log
stderr_logfile=/var/log/supervisor/queue-worker.log

; Dedicated OCR worker — isolated so heavy Tesseract/pdftoppm jobs cannot
; block thumbnail generation or file assembly on the default queue.
; Scale numprocs up if you have multiple CPU cores to spare for OCR.
[program:queue-worker-ocr]
command=php /var/www/chedonse-racks/artisan queue:work --queue=ocr --sleep=5 --tries=2 --max-time=3600 --timeout=900 --memory=1024
user=www-data
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/var/log/supervisor/queue-worker-ocr.log
stderr_logfile=/var/log/supervisor/queue-worker-ocr.log
```
```bash
supervisorctl reread
supervisorctl update
supervisorctl start queue-worker queue-worker-ocr
```

