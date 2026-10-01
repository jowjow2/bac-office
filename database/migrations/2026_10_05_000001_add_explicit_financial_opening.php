<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('financial_opening_at')->nullable();
            $table->decimal('minimum_technical_score', 10, 4)->nullable();
            $table->string('opening_documents_reference', 500)->nullable();
        });
        Schema::table('bids', function (Blueprint $table) {
            $table->timestamp('financial_opened_at')->nullable();
            $table->foreignId('financial_opened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('technical_score', 10, 4)->nullable();
            $table->timestamp('technical_scored_at')->nullable();
            $table->foreignId('technical_scored_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('technical_score_basis')->nullable();
        });
        // Never infer financial opening from a schedule, examination or award.
        // Existing submissions and recorded project technical openings are retained.
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_opened_by');
            $table->dropConstrainedForeignId('technical_scored_by');
            $table->dropColumn(['financial_opened_at', 'technical_score', 'technical_scored_at', 'technical_score_basis']);
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn([
            'financial_opening_at', 'minimum_technical_score', 'opening_documents_reference',
        ]));
    }
};
