<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitation to Bid contents required by RA 12009 IRR Sec. 50.2 that had no
 * place yet: the place of bid opening (h), the MEARB/MARB criteria and their
 * weights (e, f), the MEARB quality-price ratio (e) and, for consulting
 * services, the evaluation procedure (g). All nullable; existing projects
 * are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('bid_opening_venue', 500)->nullable()->after('submission_venue');
            $table->json('evaluation_criteria')->nullable()->after('award_criterion');
            $table->unsignedTinyInteger('quality_price_ratio')->nullable()->after('evaluation_criteria');
            $table->string('evaluation_procedure', 10)->nullable()->after('quality_price_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['bid_opening_venue', 'evaluation_criteria', 'quality_price_ratio', 'evaluation_procedure']);
        });
    }
};
