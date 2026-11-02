# Deployment guide

Two options: **(A) shared cPanel hosting** (cheap, fine for 1 business) and **(B) a VPS** (Ubuntu + Nginx + PHP-FPM + MySQL + SSL — recommended for several busy branches).

---

## 1. Production checklist (both options)

| ✔ | Item |
|---|---|
| ☐ | `.env` created from `.env.production.example`: `APP_ENV=production`, **`APP_DEBUG=false`**, correct `APP_URL` (https) |
| ☐ | `APP_KEY` generated once (`php artisan key:generate`) and **backed up** — without it encrypted data and sessions are lost |
| ☐ | Dedicated database user with a strong password (not root) |
| ☐ | `SESSION_SECURE_COOKIE=true` and HTTPS working (Let's Encrypt / AutoSSL) |
| ☐ | Mail settings filled in (password-reset emails) |
| ☐ | `DB_DUMP_PATH` points to the folder with `mysqldump` (Settings → Backup → "Create backup now" works) |
| ☐ | Cron for the scheduler (backups, low-stock alerts) — see below |
| ☐ | Queue worker running (VPS: Supervisor; cPanel: cron fallback) |
| ☐ | Demo data **not** seeded; owner account created and **all default passwords / PINs changed** |
| ☐ | Business name, logo, receipt text, tax mode, invoice prefixes set in **Settings** |
| ☐ | `storage/` and `bootstrap/cache/` writable by the web server |
| ☐ | Off-site copy of backups (download weekly or sync `storage/app/private/pos-backups` to cloud storage) |

### Deploy / update commands

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # or build locally and upload public/build
php artisan migrate --force
php artisan storage:link           # first time only
php artisan optimize               # caches config, routes, views, events
php artisan queue:restart          # workers pick up the new code
```

After changing `.env` run `php artisan optimize` again (config is cached).

### First owner account (instead of demo data)

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=SettingsSeeder --force
php artisan db:seed --class=CatalogueSeeder --force      # optional: default units, sample categories and brands
php artisan tinker
>>> $u = App\Models\User::create(['name' => 'Your Name', 'email' => 'you@yourdomain.com', 'password' => 'A-Strong-Pass1', 'is_active' => true]);
>>> $u->assignRole('owner');
```

Then log in, create your branches (Settings → Branches), then managers and cashiers (Settings → Users).

---

## 2. Option A — shared cPanel hosting

1. **PHP version**: cPanel → *MultiPHP Manager* → PHP 8.2 or newer. In *Select PHP Version → Extensions* enable `pdo_mysql, mbstring, xml, fileinfo, gd, zip`.
2. **Database**: *MySQL Databases* → create database + user, give the user ALL PRIVILEGES.
3. **Upload the code**
   - On your PC: `composer install --no-dev --optimize-autoloader` and `npm run build`.
   - Zip the project **without** `node_modules`, upload with *File Manager* to `/home/USER/pos` (outside `public_html`) and extract.
4. **Point the domain to `public/`**
   - Best: *Domains* → set the document root of `pos.yourdomain.com` to `/home/USER/pos/public`.
   - If the main domain must use `public_html`: move the contents of `pos/public` into `public_html`, then edit `public_html/index.php` and change the two paths to `__DIR__.'/../pos/vendor/autoload.php'` and `__DIR__.'/../pos/bootstrap/app.php'`.
5. **.env**: copy `.env.production.example` to `.env` in `/home/USER/pos`, fill in DB details. `DB_DUMP_PATH=/usr/bin/` (ask your host if `mysqldump` lives elsewhere).
6. **Terminal** (cPanel → *Terminal*, or ask the host):
   ```bash
   cd ~/pos
   php artisan key:generate
   php artisan migrate --force
   php artisan storage:link
   php artisan optimize
   ```
   No terminal? Create the tables by importing a `mysqldump` from your PC via *phpMyAdmin*, and create `public/storage` → `storage/app/public` symlink with a one-off cron `ln -s /home/USER/pos/storage/app/public /home/USER/pos/public/storage`.
7. **Cron** (*Cron Jobs*, every minute):
   ```
   * * * * * cd /home/USER/pos && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
8. **Queue** — shared hosts usually forbid long-running workers. Add a second cron job that processes the queue every minute and exits:
   ```
   * * * * * cd /home/USER/pos && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
   ```
9. **SSL**: *SSL/TLS Status* → Run AutoSSL. Then set `APP_URL=https://…` and `SESSION_SECURE_COOKIE=true`, run `php artisan optimize`.

---

## 3. Option B — VPS (Ubuntu 24.04, Nginx, PHP-FPM, MySQL, Let's Encrypt)

```bash
# 3.1 Packages
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server unzip git supervisor curl
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-curl php8.3-bcmath php8.3-intl
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs

# 3.2 Database
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'pos_user'@'localhost' IDENTIFIED BY 'a-long-random-password';
  GRANT ALL PRIVILEGES ON pos.* TO 'pos_user'@'localhost'; FLUSH PRIVILEGES;"

# 3.3 Code
sudo mkdir -p /var/www/pos && sudo chown $USER:www-data /var/www/pos
git clone <your-repo> /var/www/pos && cd /var/www/pos
cp .env.production.example .env && nano .env      # DB details, APP_URL, mail
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

# 3.4 Nginx
sudo cp deploy/nginx.conf /etc/nginx/sites-available/pos   # edit server_name and the php-fpm socket
sudo ln -s /etc/nginx/sites-available/pos /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# 3.5 HTTPS (Let's Encrypt)
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pos.yourdomain.com      # auto-renews via systemd timer

# 3.6 Queue worker (Supervisor)
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/pos-worker.conf
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status

# 3.7 Scheduler (cron)
sudo crontab -u www-data -e
# add:  * * * * * cd /var/www/pos && php artisan schedule:run >> /dev/null 2>&1

# 3.8 Firewall
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

### Updating a VPS install

```bash
cd /var/www/pos
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

---

## 4. Backups

- **Automatic**: every night at 23:30 (needs the cron above). Old backups are cleaned at 23:45 (spatie/laravel-backup defaults: all backups for 7 days, then daily/weekly).
- **Manual**: Settings → Backup → *Create backup now* → *Download*.
- **Restore**: unzip, then `mysql -u pos_user -p pos < db-dumps/mysql-pos.sql`.
- Keep an **off-site** copy (download weekly, or add an S3/Google Drive disk to `config/backup.php` → `destination.disks`).

## 5. Troubleshooting

| Problem | Fix |
|---|---|
| 500 error after deploy | `tail storage/logs/laravel.log`; usually permissions on `storage/` or a missing `.env` value — then `php artisan optimize` |
| Images not showing | `php artisan storage:link`; check `APP_URL` |
| Backup fails | Settings → Backup shows the error; set `DB_DUMP_PATH` to the folder containing `mysqldump` |
| Large Excel export never arrives | queue worker not running (`supervisorctl status` / cPanel queue cron) |
| Low-stock alerts / nightly backup not happening | scheduler cron missing — `php artisan schedule:list` shows what should run |
| Changes to `.env` ignored | config is cached — run `php artisan optimize` |
