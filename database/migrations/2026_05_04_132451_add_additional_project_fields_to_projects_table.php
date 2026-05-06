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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
            $table->string('location')->nullable()->after('category');
            $table->enum('procurement_mode', [
                'public_bidding',
                'negotiated_procurement',
                'shopping',
                'small_value_procurement',
                'direct_contracting',
                'electronic_procurement'
            ])->nullable()->after('location');
            $table->string('source_of_fund')->nullable()->after('procurement_mode');
            $table->string('contract_duration')->nullable()->after('source_of_fund');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'location',
                'procurement_mode',
                'source_of_fund',
                'contract_duration',
            ]);
        });
    }

};
