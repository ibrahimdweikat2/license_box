# LicenseBox

A self-hosted license manager and update server for PHP applications, similar to licensebox.app.
Built with Laravel 13, Livewire 4 and Tailwind.

- **Admin panel**: products, releases (ZIP + optional SQL), licenses (single or bulk), clients, activations, API keys, activity logs, settings and a CSV export.
- **REST API** (`/api/v1`): activate, verify and deactivate licenses, check for and download updates, plus internal endpoints to create, read, block and unblock licenses.
- **Signed responses**: every API response is signed with an RSA key. The client SDK only holds the public key, so it rejects forged responses and fake servers.
- **PHP client SDK** (`client-sdk/LicenseBoxClient.php`): a single file with no dependencies. The admin panel's *Client SDK* page generates it with your server URL, Product ID, API key and public key already filled in.

## Setup

For a production server (AWS EC2 / Amazon Linux 2023: install script, deploys, backups), see **[DEPLOYMENT.md](DEPLOYMENT.md)**.
Local development:

```bash
composer install
cp .env.example .env        # set APP_URL, and DB_* for MySQL (SQLite works out of the box)
php artisan key:generate
php artisan migrate --seed  # demo data: prints the admin login and API keys
npm install && npm run build
php artisan serve
```

For MySQL/MariaDB, create a `utf8mb4` database and set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME` and `DB_PASSWORD` in `.env` before migrating. Tests use in-memory SQLite by default; to run them on MySQL
instead, point them at a separate, throwaway database (`RefreshDatabase` wipes it):

```bash
DB_CONNECTION=mysql DB_DATABASE=licensebox_test php artisan test
```

Log in at `/login` as `admin@example.com` / `password` (seeded). Change this password right away.
Only the first admin can register themselves; after that `/register` is closed.

Add the scheduler to cron so expired licenses are marked and old logs are pruned every day:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## How it fits together

| Piece | Where |
|---|---|
| License rules (status, expiry, activation limit, domain/IP wildcards) | `app/Services/LicenseService.php` |
| Releases, semantic version compare, signed download links | `app/Services/UpdateService.php` |
| RSA signing of responses and local keys | `app/Services/ResponseSigner.php` (keys in `storage/app/private/keys`) |
| API controllers / API key middleware | `app/Http/Controllers/Api`, `app/Http/Middleware/VerifyApiKey.php` |
| Admin pages | `app/Livewire/Admin`, `resources/views/livewire/admin` |
| Client SDK template | `client-sdk/LicenseBoxClient.php` |
| Defaults for settings | `config/licensebox.php` |

API keys are stored as SHA-256 hashes, so the full key is shown only once. Use **external** keys inside your product and **internal** keys only on your own servers.

The full endpoint reference is at `/docs` on your server.

## Using the SDK

```php
require 'LicenseBoxClient.php';

$lb = new LicenseBoxClient(['current_version' => '1.0.0', 'storage_path' => __DIR__.'/storage']);

$lb->activateLicense($licenseKey, $customerName);   // once
if (! $lb->isLicensed()) { exit('Unlicensed'); }   // every request; works offline between checks

$update = $lb->checkUpdate();
if ($update['data']['can_download'] ?? false) {
    $lb->downloadUpdate($update['data']['latest_version'], $pdo); // $pdo optional: runs update.sql
}
```

## Tests

```bash
php artisan test
```

The tests cover the API (activation rules, signatures, updates, signed downloads, internal endpoints, rate limiting), the admin pages and actions, and SDK generation.

> **Regenerating the signing keypair** (Settings page) invalidates every SDK you have already shipped. Ship a fresh SDK afterwards.
