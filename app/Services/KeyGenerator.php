<?php

namespace App\Services;

use App\Models\License;

class KeyGenerator
{
    /** Unambiguous alphabet: no 0/O or 1/I/L. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * Generate a key formatted as XXXX-XXXX-XXXX-XXXX (groups x length).
     */
    public function make(int $groups = 4, int $length = 4, string $prefix = ''): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $parts = [];

        for ($g = 0; $g < $groups; $g++) {
            $part = '';
            for ($i = 0; $i < $length; $i++) {
                $part .= self::ALPHABET[random_int(0, $max)];
            }
            $parts[] = $part;
        }

        return ($prefix !== '' ? strtoupper($prefix).'-' : '').implode('-', $parts);
    }

    /**
     * Generate a license key that does not exist in the licenses table yet.
     */
    public function unique(string $prefix = ''): string
    {
        do {
            $key = $this->make(prefix: $prefix);
        } while (License::where('license_key', $key)->exists());

        return $key;
    }
}
