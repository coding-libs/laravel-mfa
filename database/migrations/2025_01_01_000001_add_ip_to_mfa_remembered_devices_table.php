<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mfa_remembered_devices', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('mfa_remembered_devices', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};