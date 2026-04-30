<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidder_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('status', 30)->default('uploaded');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidder_documents');
    }
};
