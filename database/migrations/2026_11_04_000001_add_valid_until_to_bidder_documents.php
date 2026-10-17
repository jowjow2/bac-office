<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The date a bidder document (permit, clearance, PhilGEPS...) stops being valid, as written on it. */
    public function up(): void
    {
        if (Schema::hasTable('bidder_documents') && ! Schema::hasColumn('bidder_documents', 'valid_until')) {
            Schema::table('bidder_documents', function (Blueprint $table) {
                $table->date('valid_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bidder_documents', 'valid_until')) {
            Schema::table('bidder_documents', function (Blueprint $table) {
                $table->dropColumn('valid_until');
            });
        }
    }
};
