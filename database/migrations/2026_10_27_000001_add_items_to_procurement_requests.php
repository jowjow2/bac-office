<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase request lists its items (description, quantity, unit, estimated
 * unit cost). The quantity, unit and estimated_cost columns stay and are
 * derived from the items; existing requests keep their single-line details.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('procurement_requests', 'items')) {
            return;
        }

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->json('items')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('procurement_requests', 'items')) {
            Schema::table('procurement_requests', function (Blueprint $table) {
                $table->dropColumn('items');
            });
        }
    }
};
