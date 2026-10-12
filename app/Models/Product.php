<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'product_uid', 'description', 'status'];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug ??= Str::slug($product->name);
            $product->product_uid ??= strtoupper(Str::random(8));
        });
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }

    public function latestPublishedVersion(): ?ProductVersion
    {
        return $this->versions()->where('is_published', true)->get()
            ->sort(fn ($a, $b) => version_compare($b->version, $a->version))
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
