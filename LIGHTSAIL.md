# Pag-deploy ng Anabu I-G Smart Barangay sa AWS Lightsail

Ang guide na ito ay para sa **bagong Ubuntu 24.04 LTS instance**, Nginx, PHP 8.4, MySQL, Composer 2, at Node.js 22.12 o mas bago sa Node 22 series. Kapareho nito ang PHP/Node major versions ng CI. Kung may existing deployment ka na, tingnan muna ang **Pag-update** sa ibaba; huwag palitan ang existing database o `.env`.

Ang commands ay sa **Bash terminal ng Lightsail**, maliban sa seksiyong Windows. Palitan ang `barangay.example.com`, passwords, at SSH key path. Hindi pa awtomatikong na-deploy ng Git push ang app: tests lamang ang existing GitHub Actions workflow.

## 1. Gumawa ng server at domain

1. Sa Lightsail, piliin ang Linux/Unix, OS Only, Ubuntu 24.04 LTS. Bilang panimulang sizing para sa app, database, at asset build sa isang server, gumamit ng 2 GB RAM o higit pa; subaybayan ang actual usage.
2. Gumawa at ikabit ang Static IP. Ituro ang DNS A record ng domain sa IP na iyon. [AWS static IP guide](https://docs.aws.amazon.com/lightsail/latest/userguide/lightsail-create-static-ip.html)
3. Sa Networking/firewall, buksan ang TCP 80 at 443. Limitahan ang SSH 22 sa iyong IP; kung browser SSH ang gagamitin, paganahin din ang Lightsail browser SSH access. Huwag buksan sa internet ang MySQL 3306. Kung enabled ang IPv6, ayusin din ang IPv6 rules; huwag gumawa ng AAAA record kung hindi pa configured. [AWS firewall reference](https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-firewall-rules-reference.html)
4. Kumonekta gamit ang Connect using SSH sa Lightsail console.

## 2. Install ng server software

PHP 8.4 dito ay galing sa third-party [Ondrej PHP PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php), hindi sa default Ubuntu 24.04 repository.

```bash
sudo apt update
sudo apt install -y software-properties-common ca-certificates curl git unzip nginx mysql-server composer
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd php8.4-intl php8.4-sqlite3
sudo update-alternatives --set php /usr/bin/php8.4
sudo systemctl enable --now nginx mysql php8.4-fpm
php -v
composer --version
```

Install Node bilang `ubuntu`, hindi root. Ito ang [official nvm installer](https://github.com/nvm-sh/nvm); kailangan ng Vite ang [supported Node version](https://vite.dev/guide/).

```bash
curl -fsSLo /tmp/install-nvm.sh https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.7/install.sh
bash /tmp/install-nvm.sh
source ~/.nvm/nvm.sh
nvm install 22
nvm alias default 22
node --version
npm --version
```

## 3. Kunin ang code

```bash
sudo install -d -o ubuntu -g www-data -m 2750 /var/www/anabu1g-smartbrgy
git clone --branch main https://github.com/mjksksks1325/anabu1g-smartbrgy.git /var/www/anabu1g-smartbrgy
cd /var/www/anabu1g-smartbrgy
umask 0027
cp .env.example .env
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build
```

Kung private ang repository, mag-configure ng read-only GitHub deploy key at gamitin ang SSH clone URL. Huwag ilagay ang token sa clone URL o committed files. Walang kasamang `.env`, database records, uploads, `vendor`, `node_modules`, o generated `public/build` ang Git clone.

## 4. Database at production environment

```bash
sudo mysql
```

Sa MySQL prompt, palitan ang password bago patakbuhin:

```sql
CREATE DATABASE anabu1g_smartbrgy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'anabu_app'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD';
GRANT ALL PRIVILEGES ON anabu1g_smartbrgy.* TO 'anabu_app'@'localhost';
EXIT;
```

```bash
nano .env
```

I-update ang existing values; huwag mag-iwan ng duplicate entries:

```dotenv
APP_NAME="Anabu I-G Smart Barangay"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://barangay.example.com
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=anabu1g_smartbrgy
DB_USERNAME=anabu_app
DB_PASSWORD="REPLACE_WITH_A_LONG_RANDOM_PASSWORD"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

`FILESYSTEM_DISK=public` makes the attachment URLs match the public disk where this application stores uploads. ID attachments currently use public storage: someone with their exact URL can access them. Before accepting real resident IDs on a public deployment, move these attachments behind authenticated access; HTTPS alone does not restrict who can open their URL.

Configure a real SMTP provider for password-reset emails: set `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp`, `MAIL_HOST`, `MAIL_PORT` (commonly 587), `MAIL_USERNAME`, `MAIL_PASSWORD`, and verified `MAIL_FROM_ADDRESS`. Follow your provider's TLS settings. `MAIL_MAILER=log` only writes mail to logs and does not deliver it. Document requests do not currently send automatic status emails.

For a **new empty database only**:

```bash
php artisan key:generate --force --no-interaction
php artisan migrate --force --no-interaction
php artisan storage:link --no-interaction
```

If transferring existing data, follow section 8 before running migrations. Preserve the original `APP_KEY` for encrypted data such as existing two-factor secrets. Never run `migrate:fresh`, `db:wipe`, or demo seeders on a database you want to keep.

## 5. Permissions, uploads, and Nginx

```bash
sudo chown -R ubuntu:www-data /var/www/anabu1g-smartbrgy
sudo find /var/www/anabu1g-smartbrgy -type d -exec chmod g+rx,g+s,o-rwx {} +
sudo find /var/www/anabu1g-smartbrgy -type f -exec chmod g+r,o-rwx {} +
sudo chmod -R ug+rwX storage bootstrap/cache
sudo chmod 640 .env
sudo tee /etc/php/8.4/fpm/conf.d/99-anabu.ini > /dev/null <<'INI'
upload_max_filesize = 6M
post_max_size = 8M
expose_php = Off
INI
sudo systemctl restart php8.4-fpm
sudo nano /etc/nginx/sites-available/anabu1g
```

Paste the configuration below and replace the domain. The document root must end in `/public`, as required by [Laravel's deployment guide](https://laravel.com/docs/13.x/deployment).

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name barangay.example.com;
    root /var/www/anabu1g-smartbrgy/public;
    index index.php;
    charset utf-8;
    client_max_body_size 8m;

    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param HTTP_X_FORWARDED_FOR "";
        fastcgi_param HTTP_X_FORWARDED_PORT "";
        fastcgi_param HTTP_X_FORWARDED_PROTO "";
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
```

The forwarded headers are cleared because this configuration serves requests directly. The application currently trusts proxies; if adding a load balancer/CDN, configure trusted proxy addresses and forwarding deliberately.

```bash
sudo ln -s /etc/nginx/sites-available/anabu1g /etc/nginx/sites-enabled/anabu1g
sudo nginx -t
sudo systemctl reload nginx
```

Keep the default Nginx site if it is used elsewhere; the configured domain selects the new site. DNS must point to this instance before issuing TLS certificates.

## 6. HTTPS and first administrator

Use [Certbot's Nginx instructions](https://certbot.eff.org/instructions?ws=nginx&os=snap):

```bash
sudo snap install --classic certbot
sudo /snap/bin/certbot --nginx -d barangay.example.com --redirect
sudo /snap/bin/certbot renew --dry-run
cd /var/www/anabu1g-smartbrgy
php artisan optimize --no-interaction
```

If you imported your database, use your existing administrator. For an empty database, registration is disabled, so create the first administrator through SSH:

```bash
php artisan tinker
```

Paste this into Tinker. The password is requested through a hidden prompt, not embedded in the command history:

```php
App\Models\User::forceCreate(['name' => Laravel\Prompts\text('Administrator name', required: true), 'email' => Laravel\Prompts\text('Administrator email', required: true), 'password' => Laravel\Prompts\password('Unique password, at least 12 characters', required: true, validate: ['min:12']), 'role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
```

Exit Tinker. Open `https://barangay.example.com/login`, sign in, and configure two-factor authentication. Add other staff through User Management.

## 7. Queue worker and verification

The configured database queue needs a persistent worker when queued jobs are used. Create a systemd service:

```bash
sudo nano /etc/systemd/system/anabu-queue.service
```

```ini
[Unit]
Description=Anabu I-G Laravel queue
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/anabu1g-smartbrgy
ExecStart=/usr/bin/php8.4 artisan queue:work --sleep=3 --tries=3 --timeout=60
Restart=always
RestartSec=5
TimeoutStopSec=90

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now anabu-queue
curl -I https://barangay.example.com/up
curl -I https://barangay.example.com/portal
php artisan migrate:status --no-interaction
```

Check in a desktop browser and actual phone: portal submission, ID upload, reference-code tracking, staff login, approval/rejection, certificate print and QR verification, user suspension, and audit log. Test password reset with the configured mail provider. `/up` returning 200 alone does not verify the database or these workflows. IoT hardware integration is outside this deployment.

There are currently no scheduled jobs in `routes/console.php`; add Laravel's scheduler cron when scheduled tasks are introduced.

## 8. Paglipat ng existing local data (optional)

The current local installation uses **MySQL**. A Git push does not transfer its records or users. Export into a private folder outside the repository, and stop writes while taking the database and upload backup.

On **Windows PowerShell**, use your local DB username and database name from `.env`. `--result-file` avoids PowerShell changing the dump encoding:

```powershell
mysqldump --host=127.0.0.1 --user=YOUR_LOCAL_DB_USER --password --single-transaction --no-tablespaces --set-gtid-purged=OFF --result-file="C:\private-backups\anabu.sql" YOUR_LOCAL_DB_NAME
scp -i "C:\keys\lightsail.pem" "C:\private-backups\anabu.sql" ubuntu@STATIC_IP:~/anabu.sql
scp -r -i "C:\keys\lightsail.pem" "C:\Users\markj\Herd\anabu1g-smartbrgy\storage\app\public" ubuntu@STATIC_IP:~/anabu-public
```

Create `C:\private-backups` first. Use Herd's MySQL executable path if `mysqldump` is not on PATH. Transfer the existing `APP_KEY` securely into the server `.env`; keep the server DB credentials, domain, and production settings.

On **Lightsail**, restore only into the new empty target database; take a separate backup first if it already contains records:

```bash
chmod 600 ~/anabu.sql
mysql --user=anabu_app --password anabu1g_smartbrgy < ~/anabu.sql
cd /var/www/anabu1g-smartbrgy
cp -a ~/anabu-public/. storage/app/public/
sudo chown -R ubuntu:www-data storage
sudo chmod -R ug+rwX storage
php artisan migrate --force --no-interaction
php artisan storage:link --no-interaction
php artisan optimize --no-interaction
sudo systemctl restart anabu-queue php8.4-fpm
```

Do not generate a new `APP_KEY` after this restore. Existing attachment URLs and QR images can contain the old local domain: inspect imported requests and certificates before use. Changing `APP_URL` fixes newly generated URLs, but does not rewrite stored URLs or already printed QR codes. Those need a reviewed data update or reissue. Retain a protected backup and remove temporary transfer copies when done.

## Pag-update ng existing Lightsail installation

Use this only when the server already follows the paths/services above. A Bitnami/Apache instance uses different paths and services. Preserve `.env`, uploads, and database. Before each update, take a Lightsail snapshot and a database/upload backup; confirm you can restore them. Note the current commit using `git rev-parse HEAD`.

Run one command at a time and **stop on any failure**:

```bash
cd /var/www/anabu1g-smartbrgy
umask 0027
git status --short
git fetch origin
git log --oneline HEAD..origin/main
php artisan down --no-interaction
sudo systemctl stop anabu-queue
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build
php artisan optimize:clear --no-interaction
php artisan migrate --force --no-interaction
php artisan optimize --no-interaction
sudo chown -R ubuntu:www-data storage bootstrap/cache public/build
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find public/build -type d -exec chmod 2750 {} +
sudo find public/build -type f -exec chmod 640 {} +
sudo systemctl restart php8.4-fpm anabu-queue
php artisan up --no-interaction
```

If `git status` shows server edits, review them before pulling; do not discard them with a hard reset. If a migration or build fails, keep maintenance mode on while resolving the error. Restoring an older commit alone may not reverse schema changes: recovery must account for both code and database backups.

Check `/portal`, `/login`, and the actual workflows again after updating. If there is an error:

```bash
tail -n 80 storage/logs/laravel.log
sudo tail -n 80 /var/log/nginx/error.log
sudo journalctl -u anabu-queue -n 80 --no-pager
```

Do not publish log contents containing resident information or credentials. Keep automated snapshots plus protected database/upload backups outside the instance, and test restoration periodically.
