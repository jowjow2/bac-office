<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidders', function (Blueprint $table) {
            $table->string('contact_person')->nullable()->after('company_name');
            $table->text('rejection_reason')->nullable()->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('bidders', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'rejection_reason']);
        });
    }
};
