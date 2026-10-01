<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidders', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('bidders', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
