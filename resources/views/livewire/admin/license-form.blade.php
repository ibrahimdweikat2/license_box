<div>
    <a href="{{ $license ? route('admin.licenses.show', $license) : route('admin.licenses') }}" wire:navigate class="text-sm link">&larr; Back</a>
    <x-admin.page-header :title="$license ? 'Edit license' : 'New license'" />

    @if ($products->isEmpty())
        <div class="card p-6 text-sm text-gray-600">
            Create a <a href="{{ route('admin.products') }}" wire:navigate class="link">product</a> first.
        </div>
    @else
        <form wire:submit="save" class="space-y-6">
            <div class="card p-6 space-y-4">
                <h2 class="font-semibold">License</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="Product" name="product_id">
                        <select id="product_id" wire:model="product_id" class="input" required>
                            @foreach ($products as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field label="Status" name="status">
                        <select id="status" wire:model="status" class="input">
                            @foreach (\App\Models\License::STATUSES as $s)
                                <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-admin.field label="License key" name="license_key" class="sm:col-span-2"
                                   :hint="$license ? 'Changing the key breaks existing installations until they re-enter it.' : 'Leave empty to generate a random key.'">
                        <div class="flex gap-2">
                            <input id="license_key" type="text" wire:model="license_key" class="input font-mono uppercase" placeholder="XXXX-XXXX-XXXX-XXXX">
                            <button type="button" wire:click="generateKey" class="btn-secondary whitespace-nowrap">Generate</button>
                        </div>
                    </x-admin.field>
                    <x-admin.field label="Key prefix" name="key_prefix" hint="Optional, e.g. PRO">
                        <input id="key_prefix" type="text" wire:model="key_prefix" class="input font-mono uppercase" maxlength="10">
                    </x-admin.field>
                </div>

                @unless ($license)
                    <x-admin.field label="Quantity" name="quantity" hint="Generate up to 500 licenses with the same settings." class="sm:w-1/3">
                        <input id="quantity" type="number" min="1" max="500" wire:model="quantity" class="input">
                    </x-admin.field>
                @endunless
            </div>

            <div class="card p-6 space-y-4">
                <h2 class="font-semibold">Client</h2>
                <x-admin.field label="Existing client" name="client_id">
                    <select id="client_id" wire:model="client_id" class="input">
                        <option value="">— None —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}{{ $client->email ? " ({$client->email})" : '' }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <p class="text-xs text-gray-500">…or create a new client (takes priority over the selection above):</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="New client name" name="new_client_name">
                        <input id="new_client_name" type="text" wire:model="new_client_name" class="input">
                    </x-admin.field>
                    <x-admin.field label="New client e-mail" name="new_client_email">
                        <input id="new_client_email" type="email" wire:model="new_client_email" class="input">
                    </x-admin.field>
                </div>
            </div>

            <div class="card p-6 space-y-4">
                <h2 class="font-semibold">Limits</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-admin.field label="Type" name="type">
                        <select id="type" wire:model="type" class="input">
                            @foreach (\App\Models\License::TYPES as $t)
                                <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field label="Max activations" name="max_activations" hint="0 = unlimited">
                        <input id="max_activations" type="number" min="0" wire:model="max_activations" class="input">
                    </x-admin.field>
                    <x-admin.field label="License expires" name="expires_at" hint="Empty = never">
                        <input id="expires_at" type="date" wire:model="expires_at" class="input">
                    </x-admin.field>
                    <x-admin.field label="Updates until" name="updates_expire_at" hint="Empty = forever">
                        <input id="updates_expire_at" type="date" wire:model="updates_expire_at" class="input">
                    </x-admin.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="Allowed domains" name="allowed_domains" hint="One per line. Wildcards allowed: *.example.com. Empty = any domain.">
                        <textarea id="allowed_domains" wire:model="allowed_domains" rows="3" class="input font-mono text-xs"></textarea>
                    </x-admin.field>
                    <x-admin.field label="Allowed IPs" name="allowed_ips" hint="One per line. Wildcards allowed: 10.0.0.*. Empty = any IP.">
                        <textarea id="allowed_ips" wire:model="allowed_ips" rows="3" class="input font-mono text-xs"></textarea>
                    </x-admin.field>
                </div>
                <x-admin.field label="Internal notes" name="notes">
                    <textarea id="notes" wire:model="notes" rows="2" class="input"></textarea>
                </x-admin.field>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ $license ? route('admin.licenses.show', $license) : route('admin.licenses') }}" wire:navigate class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">{{ $license ? 'Save changes' : 'Create license' }}</button>
            </div>
        </form>
    @endif
</div>
