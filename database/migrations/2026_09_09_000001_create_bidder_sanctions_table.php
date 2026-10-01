<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidder_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidder_id')->constrained('bidders')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('previous_approval_status', 30)->nullable();
            $table->text('reason');
            $table->string('reference_number');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->string('authorized_by');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bidder_id', 'type']);
            $table->index(['type', 'effective_date', 'end_date']);
            $table->index('lifted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidder_sanctions');
    }
};