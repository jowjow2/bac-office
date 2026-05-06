<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->string('eligibility_status', 30)->default('pending')->after('status');
            $table->timestamp('eligibility_reviewed_at')->nullable()->after('eligibility_status');
            $table->foreignId('eligibility_reviewed_by')->nullable()->after('eligibility_reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eligibility_reviewed_by');
            $table->dropColumn(['eligibility_status', 'eligibility_reviewed_at']);
        });
    }
};
