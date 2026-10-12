<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\ResponseSigner;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Settings')]
class Settings extends Component
{
    public string $site_name = '';

    public int $api_rate_limit = 60;

    public int $verify_interval_days = 7;

    public int $offline_grace_days = 14;

    public int $download_link_minutes = 10;

    public int $log_retention_days = 90;

    public function mount(): void
    {
        foreach (array_keys(config('licensebox.defaults')) as $key) {
            $this->{$key} = Setting::get($key);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'site_name' => ['required', 'string', 'max:60'],
            'api_rate_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'verify_interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'offline_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'download_link_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'log_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        $this->dispatch('notify', message: 'Settings saved.');
    }

    public function regenerateKeys(ResponseSigner $signer): void
    {
        $signer->regenerate();
        $this->dispatch('notify', message: 'New signing keypair generated. Ship the new SDK to your customers.');
    }

    public function render(ResponseSigner $signer)
    {
        return view('livewire.admin.settings', [
            'publicKey' => $signer->publicKey(),
            'fingerprint' => $signer->fingerprint(),
        ]);
    }
}
