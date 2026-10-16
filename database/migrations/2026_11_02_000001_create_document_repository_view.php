<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A live database index of every document reference. The file bytes stay in
 * private storage; this view follows existing and future source records.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sources = [
            ['bid_documents', 'file_path', 'original_name', 'label', 'Bid submission'],
            ['bid_document_review_events', 'file_path', 'original_name', 'requirement_key', 'Bid document version'],
            ['bidder_documents', 'file_path', 'original_name', 'document_type', 'Bidder registration'],
            ['project_documents', 'file_path', 'original_name', 'document_type', 'Project document'],
            ['procurement_request_documents', 'file_path', 'original_name', 'document_type', 'Purchase request'],
            ['bids', 'proposal_file', null, null, 'Bid proposal'],
            ['bids', 'eligibility_file', null, null, 'Bid eligibility'],
            ['projects', 'document_path', 'document_original_name', null, 'Project document'],
            ['bidders', 'document_path', null, null, 'Bidder document'],
            ['awards', 'certificate_file_path', null, null, 'Notice of Award'],
            ['awards', 'ntp_file_path', null, null, 'Notice to Proceed'],
            ['project_proceedings', 'file_path', 'original_name', 'type', 'Procurement proceeding'],
            ['bid_trackings', 'attachment_path', 'attachment_name', null, 'Bid decision attachment'],
            ['contract_implementations', 'signed_contract_file_path', 'signed_contract_file_name', null, 'Signed contract'],
            ['contract_implementation_events', 'document_path', 'document_name', 'action', 'Contract implementation'],
            ['messages', 'attachment_path', 'attachment_original_name', 'attachment_kind', 'Message attachment'],
        ];

        $selects = array_map(function (array $source): string {
            [$table, $path, $name, $type, $category] = $source;
            $nameSql = $name ? "COALESCE(NULLIF($name, ''), $path)" : $path;
            $typeSql = $type ? "COALESCE(NULLIF($type, ''), '$category')" : "'$category'";

            return "SELECT CONCAT('$table:', id, ':$path') AS repository_key, "
                ."'$table' AS source_table, id AS source_id, '$path' AS source_field, "
                ."'$category' AS category, $typeSql AS document_type, "
                ."$nameSql AS file_name, $path AS file_path, created_at AS recorded_at "
                ."FROM $table WHERE $path IS NOT NULL AND $path <> ''";
        }, $sources);

        DB::statement('CREATE OR REPLACE VIEW document_repository AS '.implode(' UNION ALL ', $selects));
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS document_repository');
    }
};
