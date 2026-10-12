<?php

use App\Services\ActivityLogger;
use App\Services\KeyGenerator;
use App\Services\LicenseService;
use App\Services\ResponseSigner;
use App\Services\UpdateService;

it('generates formatted keys from an unambiguous alphabet', function () {
    $keys = new KeyGenerator;

    expect($keys->make())->toMatch('/^[2-9A-HJKMNP-Z]{4}(-[2-9A-HJKMNP-Z]{4}){3}$/')
        ->and($keys->make(groups: 2, length: 6, prefix: 'pro'))->toMatch('/^PRO-[2-9A-Z]{6}-[2-9A-Z]{6}$/');
});

it('normalizes domains', function (?string $input, ?string $expected) {
    expect(LicenseService::normalizeDomain($input))->toBe($expected);
})->with([
    ['https://www.Example.com:8080/path?x=1', 'example.com'],
    ['shop.example.com', 'shop.example.com'],
    ['http://localhost', 'localhost'],
    ['', null],
    [null, null],
]);

it('masks license keys', function () {
    expect(ActivityLogger::mask('ABCD-EFGH-JKLM-NPQR'))->toBe('ABCD-****-****-NPQR');
});

it('strips a leading v from versions', function () {
    expect(UpdateService::cleanVersion(' v1.2.3 '))->toBe('1.2.3');
});

it('signs and verifies tokens', function () {
    $signer = app(ResponseSigner::class);
    $token = $signer->token(['a' => 1]);

    expect($signer->decodeToken($token))->toBe(['a' => 1])
        ->and($signer->decodeToken('x'.$token))->toBeNull();
});
