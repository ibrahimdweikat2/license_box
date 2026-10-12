<?php

namespace App\Livewire\Admin;

use App\Models\Activation;
use App\Models\License;
use Livewire\Component;

class LicenseShow extends Component
{
    public License $license;

    public function setStatus(string $status): void
    {
        abort_unless(in_array($status, License::STATUSES, true), 422);

        $this->license->update(['status' => $status]);
        $this->dispatch('notify', message: "License is now {$status}.");
    }

    public function deactivate(Activation $activation): void
    {
        abort_unless($activation->license_id === $this->license->id, 404);

        $activation->update(['is_active' => false]);
        $this->dispatch('notify', message: 'Installation deactivated. Its slot is free again.');
    }

    public function deleteActivation(Activation $activation): void
    {
        abort_unless($activation->license_id === $this->license->id, 404);

        $activation->delete();
    }

    public function delete()
    {
        $this->license->delete();
        session()->flash('notify', 'License deleted.');

        return $this->redirectRoute('admin.licenses', navigate: true);
    }

    public function render()
    {
        $this->license->load(['product', 'client']);

        return view('livewire.admin.license-show', [
            'activations' => $this->license->activations()->latest('activated_at')->get(),
            'logs' => $this->license->logs()->latest('id')->limit(15)->get(),
        ])->title($this->license->license_key);
    }
}
