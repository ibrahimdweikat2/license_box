# Deploying LicenseBox

These steps put LicenseBox on an **AWS EC2 server running Amazon Linux 2023**. The server can already host
other PHP 8.3 sites; they keep running untouched. The flow:

1. **Your PC**: push the project to GitHub.
2. **Server**: clone it into `/var/www/licensebox`.
3. **Server**: `sudo bash deploy/install.sh`, once. It sets everything up and gives the site a domain made from the server IP.
4. **Server**: `sudo bash deploy/deploy.sh`, every time you push changes.

| File | What it does |
|---|---|
| `deploy/install.sh` | One-time setup: PHP pool, database, `.env`, build, Nginx config `/etc/nginx/conf.d/licensebox.conf`, domain + HTTPS, cron, backups |
| `deploy/deploy.sh` | Pulls the latest commit, installs, builds, migrates, caches; rolls back if the build fails |
| `deploy/backup.sh` | Nightly backup (cron) of the database, signing keys, release files and `.env` |

## 1. Push the project to GitHub (on your PC)

1. On github.com, create a new **private** repository, e.g. `license-box`, **empty** (no README, no .gitignore).
2. In the project folder (`E:\License_box`), using Git Bash:

```bash
git init -b main
git add .
git status        # .env, vendor/, node_modules/ and storage/app/private/* must NOT be listed
git commit -m "LicenseBox"
git remote add origin https://github.com/YOUR_USER/license-box.git
git push -u origin main
```

`.gitignore` already keeps out `.env`, the signing keys and uploaded releases. Never commit them. The shipped
SDKs trust whoever holds the private key.

Later changes go up with:

```bash
git add .
git commit -m "Describe the change"
git push
```

## 2. Clone on the server

SSH into the server, then clone into `/var/www`:

```bash
ssh -i your-key.pem ec2-user@YOUR_SERVER_IP
sudo dnf install -y git
sudo git clone https://github.com/YOUR_USER/license-box.git /var/www/licensebox
```

GitHub asks for a username and password for a private repo. Enter your username, and a
[personal access token](https://github.com/settings/tokens) (scope `repo`) as the password. That's needed only for this
first clone. `install.sh` then gives the server its own read-only **deploy key**, so later deploys need no password.

## 3. Install (once)

```bash
cd /var/www/licensebox
sudo bash deploy/install.sh
```

It asks for:

- **Site name**: press Enter for `licensebox`. It names the Nginx config `/etc/nginx/conf.d/licensebox.conf`,
  the PHP pool, the system user and the database.
- **Domain**: press Enter for a free domain made from the server IP, e.g. `licensebox.3.120.45.67.sslip.io`.
  [sslip.io](https://sslip.io) resolves any `*.<ip>.sslip.io` name to that IP, so there's nothing to register.
  Or type your own domain if its DNS already points at the server.
- **Email** for Let's Encrypt.
- **S3 location** for backups, optional (press Enter to skip).
- For a private repo, it shows a **deploy key**. Add it in *GitHub → your repo → Settings → Deploy keys → Add deploy key*
  (leave "write access" off), then press Enter.
- Your **MySQL root password**, only if the existing database server needs one.
- The **first admin account**: name, email, password.

What it adds, and what it leaves alone:

| Adds | Leaves alone |
|---|---|
| `/etc/nginx/conf.d/licensebox.conf` (one `server` block for your domain) | Your other sites' Nginx configs |
| PHP-FPM pool `/etc/php-fpm.d/licensebox.conf` running as user `licensebox`, with 512 MB uploads | `php.ini` and the other pools |
| Database `licensebox` + user `licensebox` (random password in `.env`) | Your other databases and the DB server config |
| MariaDB, **only** if no MySQL/MariaDB exists yet | Installed packages are not upgraded (Node only with your OK) |
| `/etc/cron.d/licensebox`: scheduler every minute, backup at 02:30 UTC | |
| A Let's Encrypt certificate named `licensebox` | Other certificates |

Nginx and PHP-FPM are **reloaded**, not restarted, so the other sites don't go down. An Nginx config that fails
`nginx -t` is removed again before reloading.

At the end you get the URL. Open `https://licensebox.<ip>.sslip.io/login`.

**Notes**

- The security group must allow ports **80 and 443**. It probably does already, for your other sites.
- If the certificate request fails, the site runs on `http://` and the script says so. Fix the cause and run
  `install.sh` again. Let's Encrypt limits how many certificates are issued per domain, and sslip.io names
  sometimes hit that. Your own domain avoids it.
- Set `MAIL_*` in `/var/www/licensebox/.env` to send "Forgot password" emails, then run
  `sudo -u licensebox php /var/www/licensebox/artisan optimize`.

### Moving to your own domain later

Point the domain's DNS `A` record at the server's IP (use an Elastic IP so it doesn't change), then run the install again with
the new name. It rewrites only `licensebox.conf` and replaces the certificate:

```bash
cd /var/www/licensebox
sudo DOMAIN=licenses.example.com bash deploy/install.sh
```

Shipped SDKs contain the server URL, so generate the SDK again (*Client SDK* page) after a domain change.

## 4. Deploy updates

After `git push` on your PC:

```bash
ssh -i your-key.pem ec2-user@YOUR_SERVER_IP
cd /var/www/licensebox
sudo bash deploy/deploy.sh
```

| Command | When |
|---|---|
| `sudo bash deploy/deploy.sh` | Normal deploy (does nothing if GitHub has no new commits) |
| `sudo bash deploy/deploy.sh --force` | Rebuild without new commits, e.g. after editing `.env` |
| `sudo -u licensebox php artisan up` | Bring the site back if a deploy left it in maintenance mode |
| `sudo -u licensebox php artisan licensebox:check` | Check the install (debug mode, DB, keys, permissions, cron…) |
| `sudo bash deploy/backup.sh` | Take a backup now |
| `tail -f storage/logs/laravel-*.log` | App errors |

Each deploy runs these steps:

1. Turns on maintenance mode.
2. Pulls the new commits.
3. `composer install --no-dev`.
4. `npm ci && npm run build`.
5. `migrate --force`.
6. `optimize`.
7. Reloads PHP-FPM.
8. Turns maintenance mode off.
9. Runs `licensebox:check`.

- **Customers aren't affected by maintenance mode.** The SDK treats the 503 response like an unreachable server
  and keeps working in its offline grace period.
- **If something fails before migrations**, the previous commit is restored and the site comes back up.
- **If migrations or later steps fail**, the site stays in maintenance mode, because the database may already be
  changed. Fix the cause, then run `deploy.sh --force`.
- **Never edit tracked files on the server.** Change them on your PC and push. Settings belong in `.env`.

## 5. Backups and restore

`deploy/backup.sh` writes `/var/backups/licensebox/licensebox-YYYYMMDD-HHMMSS.tar.gz` every night and keeps 14 days.
If you gave an S3 location, each backup is also uploaded there. That needs an IAM role on the instance that allows
`s3:PutObject`. Each archive contains:

- `database.sql.gz`: the database dump;
- `files.tar.gz`: `storage/app/private`, with `keys/` (**the signing keypair**) and `updates/` (release files);
- `.env`.

> **The signing keys can't be recreated.** Every SDK you've shipped contains the public key, and a new keypair
> means every customer needs a new SDK. Keep a copy off the server: use S3, or download one backup with
> `scp -i your-key.pem ec2-user@IP:/var/backups/licensebox/<file> .` (copy it to `/tmp` with sudo first).
> In production the app won't create new keys when they go missing; the API returns errors until you restore them.

**Restore:**

```bash
cd /var/www/licensebox
sudo -u licensebox php artisan down
mkdir /tmp/restore && sudo tar -xzf /var/backups/licensebox/licensebox-XXXX.tar.gz -C /tmp/restore
gunzip -c /tmp/restore/database.sql.gz | sudo mysql licensebox    # or: mysql -u licensebox -p licensebox
sudo tar -xzf /tmp/restore/files.tar.gz -C /var/www/licensebox
sudo chown -R licensebox:licensebox storage
sudo -u licensebox php artisan optimize
sudo -u licensebox php artisan up
sudo -u licensebox php artisan licensebox:check
sudo rm -rf /tmp/restore
```

On a **new server**, run `install.sh` first, then restore as above. Keep the new server's `.env`; take only
`APP_KEY` from the backed-up `.env` if you want existing admin sessions to survive.

## 6. Production settings

All of these are in `.env.production.example`. After changing `.env`, run `sudo -u licensebox php artisan optimize`.

| Setting | Why |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false` | Debug error pages show secrets. `licensebox:check` fails when debug is on in production. |
| `APP_URL` | Set by `install.sh`. Used in links and in the generated SDK. |
| `TRUSTED_PROXIES` | Only behind a load balancer, CloudFront or Cloudflare. Without it, every client appears with the proxy's IP. Not needed when Nginx on the server handles HTTPS (the setup above). |
| `QUEUE_CONNECTION=sync` | Nothing is queued, so no queue worker is needed. |

The demo seeder refuses to run in production, because it would create `admin@example.com` / `password`.

## 7. Troubleshooting

| Symptom | Look at |
|---|---|
| 502 Bad Gateway | `sudo systemctl status php-fpm`, `/var/log/php-fpm/error.log`; the socket is `/run/php-fpm/licensebox.sock` |
| 500 error | `/var/www/licensebox/storage/logs/laravel-*.log` |
| Another site shows up instead | `sudo nginx -T \| grep server_name`: two configs may claim the same name |
| 413 when uploading a release | `client_max_body_size` in `licensebox.conf`, upload limits in `/etc/php-fpm.d/licensebox.conf` |
| API: "Signing keys not found" | Restore `storage/app/private/keys` from a backup |
| Certificate failed | Ports 80/443 in the security group, then run `install.sh` again |
| `.env` change has no effect | `sudo -u licensebox php artisan optimize` |
| Scheduler WARN in the check | `cat /etc/cron.d/licensebox`, `sudo systemctl status crond` |
| Nginx logs for this site | `/var/log/nginx/licensebox.access.log`, `/var/log/nginx/licensebox.error.log` |
