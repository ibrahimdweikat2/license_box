<?php

namespace App\Console\Commands;

use App\Models\ProductVersion;
use App\Models\User;
use App\Services\ResponseSigner;
use App\Services\UpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deployment preflight: reports what would break or weaken a production install.
 * Exits non-zero when any check fails, so deploy scripts can stop on it.
 */
#[Signature('licensebox:check')]
#[Description('Check that this install is ready for production')]
class CheckCommand extends Command
{
    private const WARN = 'WARN';

    private const FAIL = 'FAIL';

    private int $failures = 0;

    public function handle(ResponseSigner $signer): int
    {
        $production = $this->laravel->isProduction();

        $this->components->info('Application');
        $this->check(filled(config('app.key')), 'APP_KEY is set', 'run php artisan key:generate');
        $this->check($production, 'APP_ENV is production', 'currently "'.$this->laravel->environment().'"', self::WARN);
        $this->check(! config('app.debug'), 'APP_DEBUG is off', 'error pages would show secrets', $production ? self::FAIL : self::WARN);
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses https', (string) config('app.url'), self::WARN);
        $this->check($this->laravel->configurationIsCached(), 'Config is cached', 'run php artisan optimize', self::WARN);
        $this->check(! in_array(config('mail.default'), ['log', 'array'], true), 'Mail is delivered', 'MAIL_MAILER='.config('mail.default').', so password reset emails are not sent', self::WARN);

        $this->components->info('Database');
        $connected = $this->checkDatabase();

        $this->components->info('Signing keys');
        $this->checkKeys($signer);

        $this->components->info('Storage');
        foreach (['storage/app/private', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $this->check(is_dir(base_path($dir)) && is_writable(base_path($dir)), "$dir is writable", 'fix ownership/permissions for the web server user');
        }
        if ($connected) {
            $this->checkReleaseFiles();
        }

        $this->components->info('Scheduler');
        $this->checkScheduler();

        $this->newLine();
        if ($this->failures > 0) {
            $this->components->error("{$this->failures} check(s) failed.");

            return self::FAILURE;
        }

        $this->components->info('No failed checks.');

        return self::SUCCESS;
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->check(false, 'Database connection', $e->getMessage());

            return false;
        }
        $this->check(true, 'Database connection', info: DB::connection()->getDriverName().' / '.DB::connection()->getDatabaseName());

        $migrator = $this->laravel->make('migrator');
        if (! $migrator->repositoryExists()) {
            $this->check(false, 'Migrations are up to date', 'not migrated: run php artisan migrate --force');

            return false;
        }

        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
        $this->check($pending === [], 'Migrations are up to date', count($pending).' pending: run php artisan migrate --force');

        $this->check(User::exists(), 'An admin account exists', 'run php artisan licensebox:install', self::WARN);

        return true;
    }

    private function checkKeys(ResponseSigner $signer): void
    {
        if (! $signer->hasKeys()) {
            $this->check(false, 'Signing keys exist', 'missing in '.config('licensebox.signing.path').': restore them from a backup, or run php artisan licensebox:install on a new server');

            return;
        }

        try {
            $this->check($signer->verify('licensebox', $signer->sign('licensebox')), 'Signing keys work', 'private and public key do not match', info: 'key id '.$signer->fingerprint());
        } catch (Throwable $e) {
            $this->check(false, 'Signing keys work', $e->getMessage());
        }

        if (DIRECTORY_SEPARATOR === '/') {
            $mode = fileperms($signer->path('private.pem')) & 0777;
            $this->check(($mode & 0077) === 0, 'Private key is readable only by its owner', sprintf('mode %o: run chmod 600 on it', $mode), self::WARN);
        }
    }

    private function checkReleaseFiles(): void
    {
        $disk = Storage::disk(UpdateService::disk());
        $missing = 0;

        ProductVersion::query()->select(['id', 'main_file_path', 'sql_file_path'])->each(function (ProductVersion $release) use ($disk, &$missing) {
            foreach ([$release->main_file_path, $release->sql_file_path] as $path) {
                if ($path && ! $disk->exists($path)) {
                    $missing++;
                }
            }
        });

        $this->check($missing === 0, 'Release files are present', "$missing file(s) used by releases are missing from the '".UpdateService::disk()."' disk");
    }

    private function checkScheduler(): void
    {
        $lastRun = Cache::get('licensebox:scheduler_heartbeat');

        $this->check(
            $lastRun && now()->timestamp - $lastRun < 2 * 3600,
            'Scheduler is running',
            $lastRun
                ? 'last heartbeat '.now()->createFromTimestamp($lastRun)->diffForHumans().': check the cron entry'
                : 'no heartbeat yet: add the cron entry (it can take up to an hour to show here)',
            self::WARN,
        );
    }

    /**
     * Print one result line. $hint explains a problem; $info is shown next to a passing check.
     */
    private function check(bool $passed, string $label, string $hint = '', string $severity = self::FAIL, string $info = ''): void
    {
        if ($passed) {
            $this->components->twoColumnDetail($label.($info !== '' ? " <fg=gray>$info</>" : ''), '<fg=green;options=bold>OK</>');

            return;
        }

        $this->failures += $severity === self::FAIL ? 1 : 0;
        $color = $severity === self::FAIL ? 'red' : 'yellow';
        $this->components->twoColumnDetail("$label <fg=gray>$hint</>", "<fg=$color;options=bold>$severity</>");
    }
}
