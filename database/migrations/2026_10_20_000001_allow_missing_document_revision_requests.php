<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bid_documents', function (Blueprint $table): void {
            $table->string('file_path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep these columns nullable so any revision request created for a
        // missing file remains readable and does not make rollback destructive.
    }
};