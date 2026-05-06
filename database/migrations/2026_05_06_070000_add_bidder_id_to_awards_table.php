<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('awards')) {
            return;
        }

        Schema::table('awards', function (Blueprint $table) {
            if (! Schema::hasColumn('awards', 'bidder_id')) {
                $table->foreignId('bidder_id')
                    ->nullable()
                    ->after('bid_id')
                    ->constrained('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            if (Schema::hasColumn('awards', 'bidder_id')) {
                $table->dropForeign(['bidder_id']);
                $table->dropColumn('bidder_id');
            }
        });
    }
};
