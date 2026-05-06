<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bid_trackings')) {
            return;
        }

        if (Schema::hasTable('bid_tracking')) {
            Schema::rename('bid_tracking', 'bid_trackings');
            return;
        }

        Schema::create('bid_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained('bids')->cascadeOnDelete();
            $table->foreignId('bidder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('status_title')->nullable();
            $table->text('status_description')->nullable();
            $table->string('status_type')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bid_id', 'created_at']);
            $table->index(['bidder_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_trackings');
    }
};
