<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->enum('document_type', [
                'invitation_to_bid',
                'bidding_documents',
                'terms_of_reference',
                'technical_specifications',
                'bill_of_quantities',
                'project_plans',
                'supplemental_bulletin',
                'other'
            ])->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropColumn(['document_type']);
        });
    }
};
