<div>
    <x-admin.page-header title="Client SDK" subtitle="A single PHP file that your product uses to activate licenses and install updates." />

    @if ($products->isEmpty())
        <div class="card p-6 text-sm text-gray-600">Create a <a href="{{ route('admin.products') }}" wire:navigate class="link">product</a> first.</div>
    @else
        <div class="grid gap-6 lg:grid-cols-5">
            <form wire:submit="download" class="card p-6 space-y-4 lg:col-span-2 self-start">
                <h2 class="font-semibold">Generate LicenseBoxClient.php</h2>
                <x-admin.field label="Product" name="product_id">
                    <select id="product_id" wire:model.live="product_id" class="input">
                        @foreach ($products as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Server URL" name="server_url" hint="Public URL your customers' installations will call.">
                    <input id="server_url" type="url" wire:model="server_url" class="input">
                </x-admin.field>
                <fieldset class="space-y-2 text-sm">
                    <legend class="label">API key</legend>
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="key_mode" value="new" class="text-indigo-600"> Create a new external key for this product</label>
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="key_mode" value="existing" class="text-indigo-600"> Use an existing external key</label>
                </fieldset>
                @if ($key_mode === 'existing')
                    <x-admin.field label="Existing key" name="api_key">
                        <input id="api_key" type="text" wire:model="api_key" class="input font-mono text-xs" placeholder="lb_…">
                    </x-admin.field>
                @endif
                <button type="submit" class="btn-primary w-full">Download SDK</button>
                <p class="text-xs text-gray-500">The file embeds the server URL, Product ID, API key and the public signing key.</p>
            </form>

            <div class="card p-6 lg:col-span-3 space-y-4 text-sm">
                <h2 class="font-semibold">Usage</h2>
<pre class="overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">require __DIR__.'/LicenseBoxClient.php';

$lb = new LicenseBoxClient([
    'current_version' => '1.0.0',
    'storage_path'    => __DIR__.'/storage',   // writable, for the .license file
    'root_path'       => __DIR__,              // where updates are extracted
]);

// Once, from your install / settings screen:
$result = $lb->activateLicense($_POST['license_key'], $_POST['client_name']);
echo $result['message'];

// On every request (works offline, checks online every few days):
if (! $lb->isLicensed()) {
    exit('Please activate your license.');
}

// Updates:
$update = $lb->checkUpdate();
if ($update['data']['can_download'] ?? false) {
    $lb->downloadUpdate($update['data']['latest_version'], $pdo); // $pdo optional, runs update.sql
}

// Moving to another server:
$lb->deactivateLicense();</pre>
                <p class="text-gray-600">Product ID for this product: <span class="font-mono">{{ $productUid }}</span>. Full endpoint reference in the <a href="{{ route('docs') }}" target="_blank" class="link">API docs</a>.</p>
            </div>
        </div>
    @endif
</div>
