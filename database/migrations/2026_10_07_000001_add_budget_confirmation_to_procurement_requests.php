<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who confirmed that the budget for a purchase request is available, and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->foreignId('budget_confirmed_by')->nullable()->after('budget_available')->constrained('users')->nullOnDelete();
            $table->timestamp('budget_confirmed_at')->nullable()->after('budget_confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_confirmed_by');
            $table->dropColumn('budget_confirmed_at');
        });
    }
};
