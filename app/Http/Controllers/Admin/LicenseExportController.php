<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Livewire\Admin\Licenses;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LicenseExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $query = Licenses::filteredQuery($request->only(['search', 'product', 'status', 'type']));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['License key', 'Product', 'Client', 'Client e-mail', 'Type', 'Status', 'Active installs', 'Max activations', 'Expires at', 'Updates until', 'Created at']);

            $query->chunk(500, function ($licenses) use ($out) {
                foreach ($licenses as $license) {
                    fputcsv($out, array_map([self::class, 'safeCell'], [
                        $license->license_key,
                        $license->product->name,
                        $license->client?->name,
                        $license->client?->email,
                        $license->type,
                        $license->status,
                        $license->active_activations_count,
                        $license->max_activations,
                        $license->expires_at?->toDateString(),
                        $license->updates_expire_at?->toDateString(),
                        $license->created_at?->toDateTimeString(),
                    ]));
                }
            });

            fclose($out);
        }, 'licenses-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Prevent CSV formula injection when the file is opened in a spreadsheet. */
    private static function safeCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
