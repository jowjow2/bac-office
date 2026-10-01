<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidders', function (Blueprint $table) {
            // MySQL can commit this ALTER before a later ALTER fails. Keep a
            // retry safe when the migration is run again after a partial run.
            if (!Schema::hasColumn('bidders', 'review_status')) {
                $table->string('review_status', 30)->nullable()->default('new')->after('approval_status');
            }
            if (!Schema::hasColumn('bidders', 'review_message')) {
                $table->text('review_message')->nullable()->after('review_status');
            }
            if (!Schema::hasColumn('bidders', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_message');
            }
            if (!Schema::hasColumn('bidders', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('bidders', 'review_requested_at')) {
                $table->timestamp('review_requested_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('bidders', 'review_requested_by')) {
                $table->foreignId('review_requested_by')->nullable()->after('review_requested_at')->constrained('users')->nullOnDelete();
            }
        });

        // The original user_id foreign key uses the original unique index as
        // its backing index on MySQL. Drop the FK first before replacing the
        // uniqueness rule with a version-aware composite index.
        Schema::table('bidder_documents', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('bidder_documents_user_id_document_type_unique');
        });

        Schema::table('bidder_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('bidder_documents', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('status');
            }
            if (!Schema::hasColumn('bidder_documents', 'is_current')) {
                $table->boolean('is_current')->default(true)->after('version');
            }
            if (!Schema::hasColumn('bidder_documents', 'review_status')) {
                $table->string('review_status', 30)->nullable()->after('is_current');
            }
            if (!Schema::hasColumn('bidder_documents', 'review_note')) {
                $table->text('review_note')->nullable()->after('review_status');
            }
            if (!Schema::hasColumn('bidder_documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_note');
            }
            if (!Schema::hasColumn('bidder_documents', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            }
            if (!Schema::hasColumn('bidder_documents', 'supersedes_id')) {
                $table->unsignedBigInteger('supersedes_id')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('bidder_documents', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('supersedes_id')->references('id')->on('bidder_documents')->nullOnDelete();
            $table->index(['user_id', 'document_type', 'is_current']);
        });

        if (!Schema::hasTable('bidder_requirement_requests')) {
            Schema::create('bidder_requirement_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bidder_id')->constrained('bidders')->cascadeOnDelete();
                $table->string('document_type');
                $table->foreignId('document_id')->nullable()->constrained('bidder_documents')->nullOnDelete();
                $table->text('reason');
                $table->string('status', 30)->default('open');
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['bidder_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bidder_requirement_requests');

        Schema::table('bidder_documents', function (Blueprint $table) {
            $table->dropForeign(['supersedes_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'document_type', 'is_current']);
            $table->dropColumn(['version', 'is_current', 'review_status', 'review_note', 'reviewed_at', 'reviewed_by', 'supersedes_id']);
            $table->unique(['user_id', 'document_type']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('bidders', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['review_requested_by']);
            $table->dropColumn(['review_status', 'review_message', 'reviewed_at', 'reviewed_by', 'review_requested_at', 'review_requested_by']);
        });
    }
};
