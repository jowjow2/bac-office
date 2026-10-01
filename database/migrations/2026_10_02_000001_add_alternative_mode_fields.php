<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the alternative modes need (RA 12009 IRR, Sections 31, 34 and 35):
 * the ground for a Negotiated Procurement, which decides whether the RFQ is
 * posted, and how many suppliers an RFQ was sent to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'negotiation_ground')) {
                $table->string('negotiation_ground', 40)->nullable()->after('procurement_mode');
            }
        });

        Schema::table('project_proceedings', function (Blueprint $table) {
            if (! Schema::hasColumn('project_proceedings', 'recipients_count')) {
                $table->unsignedSmallInteger('recipients_count')->nullable()->after('reference_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'negotiation_ground')) {
                $table->dropColumn('negotiation_ground');
            }
        });

        Schema::table('project_proceedings', function (Blueprint $table) {
            if (Schema::hasColumn('project_proceedings', 'recipients_count')) {
                $table->dropColumn('recipients_count');
            }
        });
    }
};
