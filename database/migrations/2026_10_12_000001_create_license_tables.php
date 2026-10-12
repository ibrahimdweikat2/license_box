<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('product_uid', 32)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_key', 64)->unique();
            $table->string('type', 20)->default('lifetime');
            $table->unsignedInteger('max_activations')->default(1);
            $table->json('allowed_domains')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('updates_expire_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('client_name')->nullable();
            $table->string('instance_id', 64)->unique();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version', 32);
            $table->text('changelog')->nullable();
            $table->string('main_file_path')->nullable();
            $table->string('sql_file_path')->nullable();
            $table->date('release_date')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'version']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key_prefix', 12);
            $table->string('key_hash', 64)->unique();
            $table->string('type', 20)->default('external');
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->string('result', 20)->index();
            $table->string('message')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'activity_logs', 'api_keys', 'product_versions', 'activations', 'licenses', 'clients', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
