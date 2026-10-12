<div>
    <x-admin.page-header title="Settings" />

    <form wire:submit="save" class="card p-6 space-y-6">
        <x-admin.field label="Site name" name="site_name" hint="Shown in the admin panel and returned by check_connection." class="sm:w-1/2">
            <input id="site_name" type="text" wire:model="site_name" class="input" required>
        </x-admin.field>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-admin.field label="API rate limit (per minute, per IP)" name="api_rate_limit">
                <input id="api_rate_limit" type="number" min="1" wire:model="api_rate_limit" class="input">
            </x-admin.field>
            <x-admin.field label="Online check interval (days)" name="verify_interval_days" hint="The SDK re-verifies with the server this often.">
                <input id="verify_interval_days" type="number" min="1" wire:model="verify_interval_days" class="input">
            </x-admin.field>
            <x-admin.field label="Offline grace period (days)" name="offline_grace_days" hint="Extra days the app keeps working if your server is unreachable.">
                <input id="offline_grace_days" type="number" min="0" wire:model="offline_grace_days" class="input">
            </x-admin.field>
            <x-admin.field label="Download link lifetime (minutes)" name="download_link_minutes">
                <input id="download_link_minutes" type="number" min="1" wire:model="download_link_minutes" class="input">
            </x-admin.field>
            <x-admin.field label="Keep logs for (days)" name="log_retention_days" hint="Used by “Prune old logs” and the daily cleanup.">
                <input id="log_retention_days" type="number" min="1" wire:model="log_retention_days" class="input">
            </x-admin.field>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary">Save settings</button>
        </div>
    </form>

    <div class="card p-6 mt-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-semibold">Response signing key</h2>
                <p class="mt-1 text-sm text-gray-600 max-w-2xl">
                    Every API response and local license key is signed with this server's RSA private key.
                    The SDK holds only the public key below, so it can detect forged responses or a fake license server.
                </p>
                <p class="mt-2 text-sm">Key ID: <span class="font-mono">{{ $fingerprint }}</span></p>
            </div>
            <button type="button" wire:click="regenerateKeys"
                    wire:confirm="Generate a new keypair? Every SDK you have already shipped will reject responses until it is updated with the new public key."
                    class="btn-danger whitespace-nowrap">Regenerate keypair</button>
        </div>
        <pre class="mt-4 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">{{ $publicKey }}</pre>
    </div>
</div>
