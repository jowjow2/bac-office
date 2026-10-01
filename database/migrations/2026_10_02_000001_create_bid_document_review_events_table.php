<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_document_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained()->cascadeOnDelete();
            // Kept as an indexed identifier rather than a cascading FK so the
            // audit record remains useful if a legacy document row is removed.
            $table->unsignedBigInteger('bid_document_id');
            $table->string('requirement_key', 100);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24);
            $table->text('comment')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->index(['bid_document_id', 'id']);
            $table->index(['bid_id', 'requirement_key', 'id'], 'bid_doc_review_lookup');
        });

        // Existing technical uploads start in the review queue. Financial
        // documents are intentionally excluded from this workflow.
        DB::table('bid_documents')
            ->where('component', 'technical')
            ->orderBy('id')
            ->get()
            ->each(function (object $document): void {
                $uploadedAt = $document->created_at ?? now();
                DB::table('bid_document_review_events')->insert([
                    'bid_id' => $document->bid_id,
                    'bid_document_id' => $document->id,
                    'requirement_key' => $document->requirement_key,
                    'version' => 1,
                    'status' => 'pending',
                    'comment' => null,
                    'file_path' => $document->file_path,
                    'original_name' => $document->original_name,
                    'sha256' => $document->sha256,
                    'actor_id' => DB::table('bids')->where('id', $document->bid_id)->value('user_id'),
                    'uploaded_at' => $uploadedAt,
                    'created_at' => $uploadedAt,
                    'updated_at' => $uploadedAt,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_document_review_events');
    }
};