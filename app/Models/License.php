<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    use HasFactory;

    public const TYPES = ['lifetime', 'subscription', 'trial'];

    public const STATUSES = ['active', 'suspended', 'expired', 'revoked'];

    protected $fillable = [
        'product_id', 'client_id', 'license_key', 'type', 'max_activations',
        'allowed_domains', 'allowed_ips', 'expires_at', 'updates_expire_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'allowed_domains' => 'array',
            'allowed_ips' => 'array',
            'expires_at' => 'datetime',
            'updates_expire_at' => 'datetime',
            'max_activations' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    public function activeActivations(): HasMany
    {
        return $this->activations()->where('is_active', true);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canReceiveUpdates(): bool
    {
        return $this->updates_expire_at === null || $this->updates_expire_at->isFuture();
    }
}
