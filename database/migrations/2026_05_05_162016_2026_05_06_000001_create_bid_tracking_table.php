<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained()->onDelete('cascade');
            $table->foreignId('bidder_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('status_title');
            $table->text('status_description')->nullable();
            $table->string('status_type'); // timeline, validation, rejection, approval, award, notification
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bid_id', 'created_at']);
            $table->index(['bidder_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_trackings');
    }
};
