<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An end-user office may save a purchase request as a draft before every
 * detail is known. The details stay required for submission (checked in
 * ProcurementRequestController); only the columns become nullable. Existing
 * rows are unchanged.
 */
return new class extends Migration
{
    private const COLUMNS = ['specifications', 'quantity', 'unit', 'estimated_cost', 'fund_source', 'delivery_period'];

    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->text('specifications')->nullable()->change();
            $table->decimal('quantity', 15, 2)->nullable()->change();
            $table->string('unit', 40)->nullable()->change();
            $table->decimal('estimated_cost', 15, 2)->nullable()->change();
            $table->string('fund_source')->nullable()->change();
            $table->string('delivery_period')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Only possible while no draft has an empty detail.
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->text('specifications')->nullable(false)->change();
            $table->decimal('quantity', 15, 2)->nullable(false)->change();
            $table->string('unit', 40)->nullable(false)->change();
            $table->decimal('estimated_cost', 15, 2)->nullable(false)->change();
            $table->string('fund_source')->nullable(false)->change();
            $table->string('delivery_period')->nullable(false)->change();
        });
    }
};
