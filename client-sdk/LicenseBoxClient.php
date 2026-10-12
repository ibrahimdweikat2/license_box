<?php

/**
 * LicenseBox client SDK.
 *
 * Drop this single file into your product to activate, verify and deactivate licenses
 * and to install updates from your LicenseBox server. Requires PHP 7.4+ with the
 * curl, json and openssl extensions (zip for installing updates).
 *
 * Every server response is checked against the server's RSA public key embedded below,
 * so responses cannot be forged by a fake server or a man-in-the-middle.
 */
class LicenseBoxClient
{
    private const SERVER_URL = '%%SERVER_URL%%';

    private const PRODUCT_ID = '%%PRODUCT_ID%%';

    private const API_KEY = '%%API_KEY%%';

    private const PUBLIC_KEY = '%%PUBLIC_KEY%%';

    /** Server rejections after which the stored license file is discarded. */
    private const FATAL_REASONS = [
        'invalid_license', 'invalid_activation', 'license_revoked', 'license_expired',
        'domain_mismatch', 'domain_not_allowed',
    ];

    /** @var array<string, mixed> */
    private $options;

    /**
     * Options (all optional):
     *  - current_version: version of the installed product, used by checkUpdate()   (default "1.0.0")
     *  - storage_path:    writable directory for the license file                    (default: this file's directory)
     *  - root_path:       directory updates are extracted into                       (default: this file's directory)
     *  - domain:          domain of this installation                                (default: HTTP_HOST)
     *  - timeout:         HTTP timeout in seconds                                    (default 15)
     *  - server_url, product_id, api_key, public_key: override the embedded values
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'server_url' => self::SERVER_URL,
            'product_id' => self::PRODUCT_ID,
            'api_key' => self::API_KEY,
            'public_key' => self::PUBLIC_KEY,
            'current_version' => '1.0.0',
            'storage_path' => __DIR__,
            'root_path' => __DIR__,
            'domain' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
            'timeout' => 15,
            'license_file' => '.license',
        ], $options);
    }

    /* ------------------------------------------------------------------ */
    /*  License */
    /* ------------------------------------------------------------------ */

    public function checkConnection(): array
    {
        return $this->call('check_connection');
    }

    /**
     * Activate a license key on this installation and store the signed local key.
     */
    public function activateLicense(string $licenseKey, ?string $clientName = null): array
    {
        $response = $this->call('activate_license', [
            'license_key' => trim($licenseKey),
            'client_name' => $clientName,
            'domain' => $this->options['domain'],
        ]);

        if ($response['status']) {
            $this->saveLicense([
                'license_key' => strtoupper(trim($licenseKey)),
                'instance_id' => $response['data']['instance_id'],
                'local_key' => $response['data']['local_key'],
                'data' => $response['data'],
            ]);
        }

        return $response;
    }

    /**
     * Check the license. Works offline using the signed local key and only contacts the
     * server when the key is due for its periodic check (or $forceOnline is true).
     * If the server cannot be reached, the app keeps working until the grace period ends.
     *
     * @return array{status: bool, message: string, data: mixed, offline?: bool}
     */
    public function verifyLicense(bool $forceOnline = false): array
    {
        $license = $this->loadLicense();
        if ($license === null) {
            return $this->result(false, 'No license has been activated on this installation.');
        }

        $local = $this->decodeLocalKey($license['local_key'], $license['license_key']);
        if ($local === null) {
            $this->deleteLicense();

            return $this->result(false, 'The stored license is invalid. Please activate your license again.');
        }

        if (! $forceOnline && time() < $local['next_check_at']) {
            return $this->result(true, 'License is valid.', $license['data'], true);
        }

        try {
            $response = $this->call('verify_license', [
                'license_key' => $license['license_key'],
                'instance_id' => $license['instance_id'],
                'domain' => $this->options['domain'],
            ]);
        } catch (RuntimeException $e) {
            // Server unreachable or response could not be trusted: fall back to the grace period.
            if (time() < $local['valid_until']) {
                return $this->result(true, 'License server unreachable; running in offline grace period.', $license['data'], true);
            }

            return $this->result(false, 'Unable to verify the license: '.$e->getMessage());
        }

        if ($response['status']) {
            $license['local_key'] = $response['data']['local_key'];
            $license['data'] = $response['data'];
            $this->saveLicense($license);
        } elseif (in_array($response['reason'] ?? '', self::FATAL_REASONS, true)) {
            // The server definitively rejected this installation; it must be activated again.
            $this->deleteLicense();
        }

        return $response;
    }

    /**
     * Shortcut for verifyLicense()['status'].
     */
    public function isLicensed(): bool
    {
        return $this->verifyLicense()['status'];
    }

    /**
     * Free this installation's activation slot and delete the local license file.
     */
    public function deactivateLicense(): array
    {
        $license = $this->loadLicense();
        if ($license === null) {
            return $this->result(false, 'No license has been activated on this installation.');
        }

        $response = $this->call('deactivate_license', [
            'license_key' => $license['license_key'],
            'instance_id' => $license['instance_id'],
        ]);

        // Remove the local file even if the server already forgot this activation.
        if ($response['status'] || in_array($response['reason'] ?? '', ['invalid_activation', 'invalid_license'], true)) {
            $this->deleteLicense();
        }

        return $response;
    }

    /** Data returned at the last successful activation / verification, or null. */
    public function getLicenseInfo(): ?array
    {
        $license = $this->loadLicense();

        return $license === null ? null : $license['data'] + ['license_key' => $license['license_key']];
    }

    /* ------------------------------------------------------------------ */
    /*  Updates */
    /* ------------------------------------------------------------------ */

    public function latestVersion(): array
    {
        return $this->call('latest_version');
    }

    public function checkUpdate(): array
    {
        $license = $this->requireLicense();

        return $this->call('check_update', [
            'license_key' => $license['license_key'],
            'instance_id' => $license['instance_id'],
            'current_version' => $this->options['current_version'],
        ]);
    }

    /**
     * Download a version, verify its checksum, extract it into root_path and,
     * when a PDO connection is given, run the release's SQL file.
     */
    public function downloadUpdate(string $version, ?PDO $db = null): array
    {
        $license = $this->requireLicense();

        $response = $this->call('download_update', [
            'license_key' => $license['license_key'],
            'instance_id' => $license['instance_id'],
            'version' => $version,
        ]);

        if (! $response['status']) {
            return $response;
        }

        $links = $response['data'];
        $zipPath = $this->download($links['main_file'], $links['main_file_sha256']);

        try {
            $this->extract($zipPath, $this->options['root_path']);
        } finally {
            @unlink($zipPath);
        }

        $sqlRan = false;
        if ($db !== null && ! empty($links['sql_file'])) {
            $sqlPath = $this->download($links['sql_file'], $links['sql_file_sha256']);
            try {
                $this->runSql($db, (string) file_get_contents($sqlPath));
                $sqlRan = true;
            } finally {
                @unlink($sqlPath);
            }
        }

        $this->options['current_version'] = $links['version'];

        return $this->result(true, "Updated to version {$links['version']}.", [
            'version' => $links['version'],
            'changelog' => $links['changelog'],
            'sql_executed' => $sqlRan,
            'sql_available' => ! empty($links['sql_file']),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Internals */
    /* ------------------------------------------------------------------ */

    /**
     * POST to the API and return the decoded, signature-checked response.
     *
     * @throws RuntimeException on network errors or untrusted responses
     */
    private function call(string $endpoint, array $data = []): array
    {
        $nonce = bin2hex(random_bytes(16));
        $data = array_merge(['product_id' => $this->options['product_id'], 'nonce' => $nonce], $data);

        $headers = [];
        $ch = curl_init(rtrim($this->options['server_url'], '/').'/api/v1/'.$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $this->options['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-KEY: '.$this->options['api_key'],
            ],
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Connection to the license server failed: '.$error);
        }

        $signature = isset($headers['x-licensebox-signature']) ? $headers['x-licensebox-signature'] : '';
        if (! $this->verifySignature($body, $signature)) {
            throw new RuntimeException('The license server response could not be verified.');
        }

        $response = json_decode($body, true);
        if (! is_array($response) || ! isset($response['status'])) {
            throw new RuntimeException('Invalid response from the license server.');
        }

        // The echoed nonce proves this response was made for this request (no replays).
        if (! isset($response['nonce']) || ! hash_equals($nonce, (string) $response['nonce'])) {
            throw new RuntimeException('The license server response does not match the request.');
        }

        return $response + ['data' => null, 'message' => ''];
    }

    private function verifySignature(string $data, string $signature): bool
    {
        $raw = base64_decode($signature, true);

        return $raw !== false && openssl_verify($data, $raw, $this->options['public_key'], OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Validate the server-signed local key and return its payload, or null if it is not trustworthy.
     */
    private function decodeLocalKey(string $token, string $licenseKey): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || ! $this->verifySignature($parts[0], $parts[1])) {
            return null;
        }

        $payload = json_decode((string) base64_decode($parts[0]), true);
        if (! is_array($payload)
            || ! hash_equals($payload['license_hash'], hash('sha256', $licenseKey))
            || $payload['product_id'] !== $this->options['product_id']) {
            return null;
        }

        // A license file copied to another domain is rejected.
        $domain = self::normalizeDomain($this->options['domain']);
        if ($payload['domain'] !== null && $domain !== null && $payload['domain'] !== $domain) {
            return null;
        }

        return $payload;
    }

    private static function normalizeDomain(?string $domain): ?string
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace(['#^[a-z][a-z0-9+.-]*://#', '#[/?\#].*$#', '#:\d+$#', '#^www\.#'], '', $domain);

        return $domain === '' ? null : $domain;
    }

    private function download(string $url, ?string $sha256): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lbx');
        $fp = fopen($path, 'wb');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['X-API-KEY: '.$this->options['api_key']],
        ]);
        $ok = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (! $ok || $status !== 200) {
            @unlink($path);
            throw new RuntimeException("Download failed (HTTP $status).");
        }

        if ($sha256 !== null && ! hash_equals($sha256, hash_file('sha256', $path))) {
            @unlink($path);
            throw new RuntimeException('Downloaded file is corrupted (checksum mismatch).');
        }

        return $path;
    }

    private function extract(string $zipPath, string $destination): void
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP zip extension is required to install updates.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open the update package.');
        }

        // Refuse entries that would escape the destination ("zip slip").
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || $name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('#^[a-zA-Z]:#', $name)) {
                $zip->close();
                throw new RuntimeException("Unsafe path in update package: $name");
            }
        }

        if (! is_dir($destination) || ! is_writable($destination)) {
            $zip->close();
            throw new RuntimeException("Update destination is not writable: $destination");
        }

        $ok = $zip->extractTo($destination);
        $zip->close();

        if (! $ok) {
            throw new RuntimeException('Unable to extract the update package.');
        }
    }

    /**
     * Runs statements separated by ";" at the end of a line, inside a transaction where supported.
     */
    private function runSql(PDO $db, string $sql): void
    {
        $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)));

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $transaction = $db->beginTransaction();
        try {
            foreach ($statements as $statement) {
                $db->exec($statement);
            }
            if ($transaction && $db->inTransaction()) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new RuntimeException('Update SQL failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function requireLicense(): array
    {
        $license = $this->loadLicense();
        if ($license === null) {
            throw new RuntimeException('Activate a license before checking for updates.');
        }

        return $license;
    }

    private function licensePath(): string
    {
        return rtrim($this->options['storage_path'], '/\\').DIRECTORY_SEPARATOR.$this->options['license_file'];
    }

    private function loadLicense(): ?array
    {
        $path = $this->licensePath();
        if (! is_file($path)) {
            return null;
        }

        $license = json_decode((string) file_get_contents($path), true);

        return is_array($license) && isset($license['license_key'], $license['instance_id'], $license['local_key']) ? $license : null;
    }

    private function saveLicense(array $license): void
    {
        if (file_put_contents($this->licensePath(), json_encode($license), LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the license file to '.$this->licensePath());
        }
    }

    private function deleteLicense(): void
    {
        @unlink($this->licensePath());
    }

    private function result(bool $status, string $message, $data = null, bool $offline = false): array
    {
        $result = ['status' => $status, 'message' => $message, 'data' => $data];
        if ($offline) {
            $result['offline'] = true;
        }

        return $result;
    }
}
