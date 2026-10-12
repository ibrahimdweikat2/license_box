<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['name', 'key_prefix', 'key_hash', 'type', 'product_id', 'last_used_at', 'active'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Create a key and return [model, plaintext]. The plaintext is never stored.
     */
    public static function generate(string $name, string $type = 'external', ?int $productId = null): array
    {
        $plain = 'lb_'.Str::random(40);

        $key = static::create([
            'name' => $name,
            'key_prefix' => substr($plain, 0, 10),
            'key_hash' => hash('sha256', $plain),
            'type' => $type,
            'product_id' => $productId,
        ]);

        return [$key, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        return static::where('key_hash', hash('sha256', $plain))->where('active', true)->first();
    }
}
