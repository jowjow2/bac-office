<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_login_tokens', function (Blueprint $table) {
            $table->longText('token_ciphertext')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('qr_login_tokens', function (Blueprint $table) {
            $table->dropColumn('token_ciphertext');
        });
    }
};
