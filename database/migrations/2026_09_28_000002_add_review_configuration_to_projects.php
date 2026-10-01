<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('award_criterion', 40)->nullable()->after('procurement_mode');
            $table->string('financial_opening_stage', 40)->nullable()->after('award_criterion');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['award_criterion', 'financial_opening_stage']);
        });
    }
};
