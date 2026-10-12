<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What visitors and bidders tell the BAC about the portal: a rating, a kind of feedback and a message.
 * No IP address is kept. The name and email are optional and only there so the BAC can reply.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feedback')) {
            return;
        }

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->unsignedTinyInteger('rating');
            $table->text('message');
            $table->string('name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('page_url', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
