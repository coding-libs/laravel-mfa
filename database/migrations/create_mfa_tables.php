<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mfa_methods', function (Blueprint $table) {
            $table->id();
            $table->string('user_type');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method'); // email|sms|totp
            $table->text('secret')->nullable(); // for totp
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_type', 'user_id', 'method']);
        });

        Schema::create('mfa_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('user_type');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method'); // email|sms
            $table->string('code');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_type', 'user_id', 'method']);
        });

        Schema::create('mfa_remembered_devices', function (Blueprint $table) {
            $table->id();
            $table->string('user_type');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('device_name')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_type', 'user_id', 'token_hash'], 'mfa_rd_unique');
            $table->index(['user_type', 'user_id'], 'mfa_rd_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_remembered_devices');
        Schema::dropIfExists('mfa_challenges');
        Schema::dropIfExists('mfa_methods');
    }
};

