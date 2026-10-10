<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The performance security the supplier posted before signing the contract: its form, amount,
 * how long it is valid and the bond or check number. The LGU records it with the contract
 * terms (or later), and sees when it expires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_implementations', function (Blueprint $table) {
            // One of ContractImplementation::PERFORMANCE_SECURITIES, or "not_required".
            $table->string('performance_security_form', 30)->nullable()->after('warranty_released_on');
            $table->decimal('performance_security_amount', 14, 2)->nullable()->after('performance_security_form');
            $table->date('performance_security_valid_until')->nullable()->after('performance_security_amount');
            $table->string('performance_security_reference', 120)->nullable()->after('performance_security_valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('contract_implementations', function (Blueprint $table) {
            $table->dropColumn(['performance_security_form', 'performance_security_amount', 'performance_security_valid_until', 'performance_security_reference']);
        });
    }
};
