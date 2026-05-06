<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('awards')) {
            return;
        }

        // Add new columns if they don't exist
        Schema::table('awards', function (Blueprint $table) {
            if (! Schema::hasColumn('awards', 'certificate_file_path')) {
                $table->string('certificate_file_path')->nullable()->after('certificate_number');
            }

            if (! Schema::hasColumn('awards', 'certificate_uploaded_at')) {
                $table->timestamp('certificate_uploaded_at')->nullable()->after('certificate_file_path');
            }

            if (! Schema::hasColumn('awards', 'certificate_revoked_at')) {
                $table->timestamp('certificate_revoked_at')->nullable()->after('certificate_uploaded_at');
            }

            if (! Schema::hasColumn('awards', 'certificate_revoked_by')) {
                $table->foreignId('certificate_revoked_by')->nullable()->constrained('users')->onDelete('set null')->after('certificate_revoked_at');
            }
        });

        // Update status values: 'active' -> 'valid', 'completed' -> 'expired'
        DB::table('awards')
            ->where('status', 'active')
            ->update(['status' => 'valid']);

        DB::table('awards')
            ->where('status', 'completed')
            ->update(['status' => 'expired']);

        // Change status column type to ENUM with new values if needed
        // This must be done after data conversion
        // Using raw DB to modify ENUM (MySQL syntax)
        try {
            DB::statement("ALTER TABLE awards MODIFY COLUMN status ENUM('valid', 'revoked', 'expired') DEFAULT 'valid'");
        } catch (\Throwable $e) {
            // If error (e.g., not MySQL), keep as string? We'll assume MySQL.
        }
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            if (Schema::hasColumn('awards', 'certificate_revoked_by')) {
                $table->dropForeign(['certificate_revoked_by']);
                $table->dropColumn('certificate_revoked_by');
            }
            if (Schema::hasColumn('awards', 'certificate_revoked_at')) {
                $table->dropColumn('certificate_revoked_at');
            }
            if (Schema::hasColumn('awards', 'certificate_uploaded_at')) {
                $table->dropColumn('certificate_uploaded_at');
            }
            if (Schema::hasColumn('awards', 'certificate_file_path')) {
                $table->dropColumn('certificate_file_path');
            }
        });

        // Revert status to old enum
        try {
            DB::statement("ALTER TABLE awards MODIFY COLUMN status ENUM('active', 'completed') DEFAULT 'active'");
        } catch (\Throwable $e) {
        }
    }
};
