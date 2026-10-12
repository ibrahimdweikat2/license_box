<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\ResponseSigner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ResponseSigner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Release ZIPs are larger than Livewire's default 12 MB temporary upload limit.
        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:512000'],
            'livewire.temporary_file_upload.max_upload_time' => 30,
        ]);

        if ($proxies = config('licensebox.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        RateLimiter::for('license-api', function (Request $request) {
            try {
                $perMinute = (int) Setting::get('api_rate_limit');
            } catch (Throwable) {
                $perMinute = (int) config('licensebox.defaults.api_rate_limit');
            }

            return Limit::perMinute(max(1, $perMinute))->by($request->ip());
        });
    }
}
