<?php

namespace App\Livewire\Admin;

use App\Models\Activation;
use App\Models\ActivityLog;
use App\Models\License;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $since = now()->subDays(29)->startOfDay();

        $perDay = Activation::where('activated_at', '>=', $since)->pluck('activated_at')
            ->countBy(fn (Carbon $date) => $date->toDateString());

        $chart = collect(range(29, 0))->map(function (int $daysAgo) use ($perDay) {
            $date = now()->subDays($daysAgo)->toDateString();

            return ['date' => $date, 'count' => $perDay[$date] ?? 0];
        });

        return view('livewire.admin.dashboard', [
            'stats' => [
                'Products' => Product::count(),
                'Active licenses' => License::where('status', 'active')->count(),
                'Active installs' => Activation::where('is_active', true)->count(),
                'Downloads (30d)' => ActivityLog::where('action', 'download_file')->where('created_at', '>=', $since)->count(),
                'API calls today' => ActivityLog::where('created_at', '>=', today())->count(),
                'Failed calls today' => ActivityLog::where('created_at', '>=', today())->where('result', 'failed')->count(),
            ],
            'chart' => $chart,
            'chartMax' => max(1, $chart->max('count')),
            'recent' => ActivityLog::with('product')->latest('id')->limit(8)->get(),
            'expiring' => License::with(['product', 'client'])
                ->where('status', 'active')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->orderBy('expires_at')->limit(8)->get(),
        ]);
    }
}
