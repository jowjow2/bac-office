<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('date_posted')->nullable();
            $table->datetime('pre_bid_conference_date')->nullable();
            $table->datetime('clarification_deadline')->nullable();
            $table->datetime('bid_submission_deadline')->nullable();
            $table->datetime('bid_opening_date')->nullable();
            $table->date('evaluation_start_date')->nullable();
            $table->date('expected_award_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_schedules');
    }
};
