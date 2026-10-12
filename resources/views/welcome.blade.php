<x-public-layout>
    <section class="bg-gradient-to-b from-indigo-50 to-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-20 sm:py-28 text-center">
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-gray-900">
                Protect your software.<br class="hidden sm:block"> Ship updates with confidence.
            </h1>
            <p class="mt-6 text-lg text-gray-600 max-w-2xl mx-auto">
                A self-hosted license manager and update server for PHP applications.
                Issue license keys, limit activations by domain or IP, and deliver signed updates
                through a single drop-in client file.
            </p>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn-primary px-6 py-3 text-base">Open admin panel</a>
                <a href="{{ route('docs') }}" class="btn-secondary px-6 py-3 text-base">Read the API docs</a>
            </div>
        </div>
    </section>

    <section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <h2 class="text-2xl font-semibold text-center">Everything you need to license a product</h2>
        @php
            $features = [
                ['License keys', 'Generate single or bulk keys, lifetime, subscription or trial, with optional expiry and update periods.'],
                ['Activation limits', 'Cap installations per license and restrict them to domains or IPs, with wildcard support.'],
                ['Update server', 'Upload release ZIPs and SQL migrations. Licensed installs download them through expiring signed links.'],
                ['Signed responses', 'Every API response is RSA-signed. The client rejects forged responses and fake license servers.'],
                ['Offline grace period', 'Installations verify periodically and keep working for a configurable time if your server is down.'],
                ['Full audit trail', 'Every API call is logged with its result, IP and masked license key, ready to filter and inspect.'],
                ['Clients & CSV export', 'Track customers, see their installations and export filtered license lists to CSV.'],
                ['Internal API', 'Let your shop or billing system create, look up, block and unblock licenses automatically.'],
                ['Drop-in PHP SDK', 'One dependency-free file, pre-filled with your server URL, product ID, API key and public key.'],
            ];
        @endphp
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as [$title, $text])
                <div class="rounded-lg border border-gray-200 p-6">
                    <h3 class="font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 grid gap-10 lg:grid-cols-2 items-center">
            <div>
                <h2 class="text-2xl font-semibold">Three lines to protect your app</h2>
                <ol class="mt-6 space-y-4 text-gray-600">
                    <li><span class="font-semibold text-gray-900">1. Create a product</span> and upload your first release in the admin panel.</li>
                    <li><span class="font-semibold text-gray-900">2. Download the SDK</span> already configured for that product.</li>
                    <li><span class="font-semibold text-gray-900">3. Call <code class="text-sm">activateLicense()</code></span> on install and <code class="text-sm">isLicensed()</code> on every request.</li>
                </ol>
            </div>
<pre class="overflow-x-auto rounded-lg bg-gray-900 p-6 text-sm text-gray-100">require 'LicenseBoxClient.php';

$lb = new LicenseBoxClient();

if (! $lb->isLicensed()) {
    $lb->activateLicense($key, $customerName);
}</pre>
        </div>
    </section>
</x-public-layout>
