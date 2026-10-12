<?php

use App\Models\ActivityLog;
use App\Models\License;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Mark licenses whose expiry date has passed (the API also does this lazily).
Schedule::call(fn () => License::where('status', 'active')->where('expires_at', '<', now())->update(['status' => 'expired']))
    ->daily()->name('licenses:expire');

Schedule::call(fn () => ActivityLog::where('created_at', '<', now()->subDays((int) Setting::get('log_retention_days')))->delete())
    ->daily()->name('logs:prune');

// Lets `php artisan licensebox:check` confirm that cron is actually running the scheduler.
Schedule::call(fn () => Cache::forever('licensebox:scheduler_heartbeat', now()->timestamp))
    ->hourly()->name('scheduler:heartbeat');
