<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id', 'domain', 'ip', 'client_name', 'instance_id',
        'activated_at', 'last_checked_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
