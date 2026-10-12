@php
    $base = rtrim(config('app.url'), '/').'/api/v1';

    $external = [
        ['check_connection', 'Test the API key and connectivity.', [], 'server, api_version, key_id'],
        ['activate_license', 'Activate a license on an installation. Re-activating the same domain reuses its slot.',
            ['license_key*' => 'License key', 'domain' => 'Domain of the installation', 'client_name' => 'Name shown in the admin panel'],
            'instance_id, local_key, license_type, expires_at, updates_expire_at, activations_used, max_activations, next_check_at'],
        ['verify_license', 'Check that a license and activation are still valid and refresh the local key.',
            ['license_key*' => 'License key', 'instance_id*' => 'From activate_license', 'domain' => 'Domain of the installation'],
            'Same as activate_license'],
        ['deactivate_license', 'Free the activation slot of an installation.',
            ['license_key*' => 'License key', 'instance_id*' => 'From activate_license'],
            'activations_used, max_activations'],
        ['latest_version', 'Public information about the newest published release.', [], 'latest_version, release_date, changelog'],
        ['check_update', 'Compare the installed version with the latest release.',
            ['license_key*' => 'License key', 'instance_id*' => 'From activate_license', 'current_version*' => 'Installed version, e.g. 1.2.0'],
            'update_available, latest_version, changelog, has_sql, can_download, updates_expire_at'],
        ['download_update', 'Get short-lived signed download links for a release.',
            ['license_key*' => 'License key', 'instance_id*' => 'From activate_license', 'version*' => 'Version to download'],
            'version, changelog, main_file, main_file_sha256, sql_file, sql_file_sha256, expires_at'],
    ];

    $internal = [
        ['create_license', 'Create a license (e.g. after a purchase).',
            ['license_key' => 'Custom key (generated if empty)', 'type' => 'lifetime | subscription | trial', 'max_activations' => '0 = unlimited (default 1)',
             'expires_at' => 'Date', 'updates_expire_at' => 'Date', 'allowed_domains' => 'Array of patterns', 'allowed_ips' => 'Array of patterns',
             'client_name' => 'Creates a client', 'client_email' => 'Reuses an existing client with this e-mail', 'notes' => 'Internal notes'],
            'The license'],
        ['get_license', 'Read a license and its activations.', ['license_key*' => 'License key'], 'The license with activations'],
        ['block_license', 'Suspend a license (e.g. refund or chargeback).', ['license_key*' => 'License key'], 'The license'],
        ['unblock_license', 'Reactivate a suspended license.', ['license_key*' => 'License key'], 'The license'],
    ];

    $reasons = [
        'invalid_api_key' => 'Missing, wrong or disabled API key (401).',
        'forbidden' => 'Key not allowed for this endpoint or product (403).',
        'validation_failed' => 'Missing or invalid parameters; see data.errors (422).',
        'invalid_product' => 'Unknown product_id (404).',
        'product_inactive' => 'The product is disabled (403).',
        'invalid_license' => 'Unknown license key for this product (404).',
        'license_suspended' => 'The license is suspended (403).',
        'license_revoked' => 'The license is revoked (403).',
        'license_expired' => 'The license expiry date has passed (403).',
        'activation_limit' => 'All activation slots are in use (403).',
        'domain_not_allowed' => 'The domain is not in the license\'s allowed list (403).',
        'ip_not_allowed' => 'The IP is not in the license\'s allowed list (403).',
        'domain_mismatch' => 'The activation was made for another domain (403).',
        'invalid_activation' => 'Unknown or deactivated instance_id (403).',
        'updates_expired' => 'The license\'s update period has ended (403).',
        'version_not_found' => 'The version does not exist or is not published (404).',
    ];
@endphp

<x-public-layout title="API documentation">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-12 lg:grid lg:grid-cols-4 lg:gap-10">
        <aside class="hidden lg:block">
            <nav class="sticky top-6 space-y-1 text-sm">
                <a href="#basics" class="block text-gray-600 hover:text-gray-900">Basics</a>
                <a href="#signatures" class="block text-gray-600 hover:text-gray-900">Response signatures</a>
                <a href="#external" class="block text-gray-600 hover:text-gray-900">License &amp; update endpoints</a>
                <a href="#internal" class="block text-gray-600 hover:text-gray-900">Internal endpoints</a>
                <a href="#errors" class="block text-gray-600 hover:text-gray-900">Error reasons</a>
                <a href="#sdk" class="block text-gray-600 hover:text-gray-900">PHP SDK</a>
            </nav>
        </aside>

        <article class="lg:col-span-3 space-y-12 min-w-0">
            <section id="basics">
                <h1 class="text-3xl font-bold">API documentation</h1>
                <p class="mt-4 text-gray-600">All endpoints accept <code>POST</code> requests with a JSON (or form) body and return JSON.</p>
                <ul class="mt-4 space-y-2 text-sm text-gray-700 list-disc pl-5">
                    <li>Base URL: <code class="break-all">{{ $base }}</code></li>
                    <li>Authenticate with the header <code>X-API-KEY: lb_…</code>. Create keys under <em>API Keys</em> in the admin panel.</li>
                    <li>Every request needs <code>product_id</code> (the Product ID shown in the admin panel).</li>
                    <li>Send a random <code>nonce</code> with each request; it is echoed back so responses cannot be replayed.</li>
                    <li>Requests are rate limited per IP (default 60 per minute).</li>
                </ul>
<pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100">curl -X POST {{ $base }}/activate_license \
  -H "X-API-KEY: lb_your_key" -H "Content-Type: application/json" \
  -d '{"product_id":"DEMOAPP1","license_key":"DEMO-DEMO-DEMO-DEMO","domain":"shop.example.com","nonce":"5f2c…"}'

{
  "status": true,
  "message": "License activated successfully.",
  "data": { "instance_id": "k3j…", "local_key": "eyJ…", "activations_used": 1, "max_activations": 2, … },
  "nonce": "5f2c…",
  "timestamp": 1760000000
}</pre>
                <p class="mt-4 text-sm text-gray-600">Failures return <code>"status": false</code>, a human readable <code>message</code> and a machine readable <code>reason</code>.</p>
            </section>

            <section id="signatures">
                <h2 class="text-2xl font-semibold">Response signatures</h2>
                <p class="mt-3 text-gray-600 text-sm">
                    Each response carries <code>X-LicenseBox-Signature</code>: a base64 RSA-SHA256 signature of the raw response body,
                    made with the server's private key. Verify it with the public key from <em>Settings</em> before trusting the response.
                    The <code>local_key</code> is a signed token (<code>base64(json).base64(signature)</code>) that lets installations
                    validate their license offline until <code>next_check_at</code>, plus the configured grace period.
                </p>
<pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100">$ok = openssl_verify($rawBody, base64_decode($signatureHeader), $publicKeyPem, OPENSSL_ALGO_SHA256) === 1;</pre>
            </section>

            @foreach ([['external', 'License & update endpoints', 'External or internal API key.', $external], ['internal', 'Internal endpoints', 'Internal API key only. Call these from your own servers, never from distributed code.', $internal]] as [$id, $heading, $auth, $endpoints])
                <section id="{{ $id }}">
                    <h2 class="text-2xl font-semibold">{{ $heading }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ $auth }} Parameters marked * are required, and <code>product_id</code> is always required.</p>
                    <div class="mt-6 space-y-6">
                        @foreach ($endpoints as [$name, $description, $params, $returns])
                            <div class="rounded-lg border border-gray-200 p-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">POST</span>
                                    <code class="font-semibold">/api/v1/{{ $name }}</code>
                                </div>
                                <p class="mt-2 text-sm text-gray-600">{{ $description }}</p>
                                @if ($params)
                                    <table class="mt-3 w-full text-sm">
                                        @foreach ($params as $param => $text)
                                            <tr class="border-t border-gray-100">
                                                <td class="py-1.5 pr-4 font-mono text-xs whitespace-nowrap align-top">{{ $param }}</td>
                                                <td class="py-1.5 text-gray-600">{{ $text }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif
                                <p class="mt-3 text-xs text-gray-500"><span class="font-semibold">Returns (data):</span> {{ $returns }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section id="errors">
                <h2 class="text-2xl font-semibold">Error reasons</h2>
                <table class="mt-4 w-full text-sm">
                    @foreach ($reasons as $reason => $text)
                        <tr class="border-t border-gray-100">
                            <td class="py-2 pr-4 font-mono text-xs whitespace-nowrap align-top">{{ $reason }}</td>
                            <td class="py-2 text-gray-600">{{ $text }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>

            <section id="sdk">
                <h2 class="text-2xl font-semibold">PHP SDK</h2>
                <p class="mt-3 text-sm text-gray-600">
                    Download <code>LicenseBoxClient.php</code> from <em>Client SDK</em> in the admin panel. It needs PHP 7.4+ with curl, json and openssl
                    (zip to install updates), checks every signature and nonce, and stores the activation in a <code>.license</code> file.
                </p>
                <table class="mt-4 w-full text-sm">
                    @foreach ([
                        'activateLicense($key, $clientName = null)' => 'Activates and stores the signed local key.',
                        'verifyLicense($forceOnline = false)' => 'Validates offline; contacts the server when a check is due. Falls back to the grace period if the server is unreachable.',
                        'isLicensed()' => 'Shortcut returning true or false.',
                        'deactivateLicense()' => 'Frees the slot and deletes the local file.',
                        'getLicenseInfo()' => 'Data from the last successful check.',
                        'checkUpdate()' => 'Asks whether a newer version is available.',
                        'downloadUpdate($version, PDO $db = null)' => 'Downloads, verifies the SHA-256, extracts into root_path and optionally runs the SQL file.',
                    ] as $method => $text)
                        <tr class="border-t border-gray-100">
                            <td class="py-2 pr-4 font-mono text-xs align-top">{{ $method }}</td>
                            <td class="py-2 text-gray-600">{{ $text }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        </article>
    </div>
</x-public-layout>
