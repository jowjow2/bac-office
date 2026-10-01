<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods contract implementation under RA 12009 IRR Sec. 71.1 and 90.1:
 * an approved time extension of the delivery deadline, the warranty terms
 * of the signed contract, the acceptance date the warranty runs from, and
 * the release of the warranty security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_implementations', function (Blueprint $table) {
            $table->date('revised_deadline')->nullable()->after('delivery_deadline');
            // True when an approved extension kept liquidated damages running from the original deadline.
            $table->boolean('extension_keeps_ld')->default(false)->after('revised_deadline');
            $table->string('supply_type', 20)->nullable()->after('contract_items');
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('supply_type');
            $table->string('warranty_security', 20)->nullable()->after('warranty_months');
            $table->decimal('warranty_percent', 4, 2)->nullable()->after('warranty_security');
            $table->date('accepted_on')->nullable()->after('warranty_percent');
            $table->date('warranty_released_on')->nullable()->after('accepted_on');
        });
    }

    public function down(): void
    {
        Schema::table('contract_implementations', function (Blueprint $table) {
            $table->dropColumn(['revised_deadline', 'extension_keeps_ld', 'supply_type', 'warranty_months', 'warranty_security', 'warranty_percent', 'accepted_on', 'warranty_released_on']);
        });
    }
};
