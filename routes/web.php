<?php

use App\Http\Controllers\Admin\LicenseExportController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Admin;
use App\Services\ResponseSigner;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/docs', 'docs')->name('docs');

// The public half of the signing keypair (safe to publish; the private key never leaves storage/).
Route::get('/public-key.pem', fn (ResponseSigner $signer) => response($signer->publicKey(), 200, [
    'Content-Type' => 'text/plain; charset=UTF-8',
    'X-LicenseBox-Key-Id' => $signer->fingerprint(),
    'Cache-Control' => 'public, max-age=300',
]))->name('public-key');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', Admin\Dashboard::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/products', Admin\Products::class)->name('products');
        Route::livewire('/products/{product}/versions', Admin\ProductVersions::class)->name('products.versions');

        Route::livewire('/licenses', Admin\Licenses::class)->name('licenses');
        Route::get('/licenses/export', LicenseExportController::class)->name('licenses.export');
        Route::livewire('/licenses/create', Admin\LicenseForm::class)->name('licenses.create');
        Route::livewire('/licenses/{license}', Admin\LicenseShow::class)->name('licenses.show');
        Route::livewire('/licenses/{license}/edit', Admin\LicenseForm::class)->name('licenses.edit');

        Route::livewire('/activations', Admin\Activations::class)->name('activations');
        Route::livewire('/clients', Admin\Clients::class)->name('clients');
        Route::livewire('/api-keys', Admin\ApiKeys::class)->name('api-keys');
        Route::livewire('/logs', Admin\ActivityLogs::class)->name('logs');
        Route::livewire('/sdk', Admin\Sdk::class)->name('sdk');
        Route::livewire('/settings', Admin\Settings::class)->name('settings');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
