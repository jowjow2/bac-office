<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_implementations')) {
            Schema::create('contract_implementations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('award_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('status', 40)->default('for_delivery')->index();
                $table->date('delivery_deadline')->nullable();
                $table->string('delivery_location')->nullable();
                $table->json('contract_items')->nullable();
                $table->string('signed_contract_reference')->nullable();
                $table->string('signed_contract_file_path')->nullable();
                $table->string('signed_contract_file_name')->nullable();
                $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('configured_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contract_implementation_events')) {
            Schema::create('contract_implementation_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_implementation_id');
                $table->string('action', 50);
                $table->string('status_from', 40)->nullable();
                $table->string('status_to', 40)->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 30)->nullable();
                $table->timestamp('occurred_at');
                $table->text('remarks')->nullable();
                $table->json('details')->nullable();
                $table->string('document_path')->nullable();
                $table->string('document_name')->nullable();
                $table->timestamps();
                $table->index(['contract_implementation_id', 'occurred_at'], 'contract_impl_events_timeline_idx');
            });
        }

        $foreignKeys = collect(Schema::getForeignKeys('contract_implementation_events'))->pluck('name');
        if (!$foreignKeys->contains('ci_events_parent_fk')) {
            Schema::table('contract_implementation_events', function (Blueprint $table) {
                $table->foreign('contract_implementation_id', 'ci_events_parent_fk')
                    ->references('id')->on('contract_implementations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_implementation_events');
        Schema::dropIfExists('contract_implementations');
    }
};