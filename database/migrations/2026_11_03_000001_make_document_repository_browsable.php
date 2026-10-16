<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Neon Data Editor lists tables, not views. Keep a browsable table in sync with
 * the document-bearing source rows while the file bytes remain in Vercel Blob.
 */
return new class extends Migration
{
    public $withinTransaction = true;

    private const SOURCE_TABLES = [
        'bid_documents',
        'bid_document_review_events',
        'bidder_documents',
        'project_documents',
        'procurement_request_documents',
        'bids',
        'projects',
        'bidders',
        'awards',
        'project_proceedings',
        'bid_trackings',
        'contract_implementations',
        'contract_implementation_events',
        'messages',
    ];

    public function up(): void
    {
        // The live Vercel database is PostgreSQL. Leave the existing view in
        // place on other drivers, which cannot run the PostgreSQL triggers.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('document_repository_records', function (Blueprint $table) {
            $table->string('repository_key')->primary();
            $table->string('source_table');
            $table->bigInteger('source_id');
            $table->string('source_field');
            $table->string('category');
            $table->string('document_type');
            $table->text('file_name');
            $table->text('file_path');
            $table->timestamp('recorded_at')->nullable();
            $table->index(['source_table', 'source_id']);
        });

        // Copy the complete live index before replacing its view name. This
        // migration runs in one PostgreSQL transaction.
        DB::statement('INSERT INTO document_repository_records
            (repository_key, source_table, source_id, source_field, category,
             document_type, file_name, file_path, recorded_at)
            SELECT repository_key, source_table, source_id, source_field, category,
                   document_type, file_name, file_path, recorded_at
            FROM document_repository');
        DB::statement('DROP VIEW document_repository');
        Schema::rename('document_repository_records', 'document_repository');

        DB::unprepared(<<<'SQL'
CREATE FUNCTION sync_document_repository() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    source_row jsonb;
    source_id_value bigint;
    spec record;
    path_value text;
BEGIN
    IF TG_OP = 'DELETE' THEN
        source_row := to_jsonb(OLD);
    ELSE
        source_row := to_jsonb(NEW);
    END IF;
    source_id_value := (source_row ->> 'id')::bigint;

    DELETE FROM document_repository
    WHERE source_table = TG_TABLE_NAME AND source_id = source_id_value;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    FOR spec IN
        SELECT * FROM (VALUES
            ('bid_documents', 'file_path', 'original_name', 'label', 'Bid submission'),
            ('bid_document_review_events', 'file_path', 'original_name', 'requirement_key', 'Bid document version'),
            ('bidder_documents', 'file_path', 'original_name', 'document_type', 'Bidder registration'),
            ('project_documents', 'file_path', 'original_name', 'document_type', 'Project document'),
            ('procurement_request_documents', 'file_path', 'original_name', 'document_type', 'Purchase request'),
            ('bids', 'proposal_file', NULL, NULL, 'Bid proposal'),
            ('bids', 'eligibility_file', NULL, NULL, 'Bid eligibility'),
            ('projects', 'document_path', 'document_original_name', NULL, 'Project document'),
            ('bidders', 'document_path', NULL, NULL, 'Bidder document'),
            ('awards', 'certificate_file_path', NULL, NULL, 'Notice of Award'),
            ('awards', 'ntp_file_path', NULL, NULL, 'Notice to Proceed'),
            ('project_proceedings', 'file_path', 'original_name', 'type', 'Procurement proceeding'),
            ('bid_trackings', 'attachment_path', 'attachment_name', NULL, 'Bid decision attachment'),
            ('contract_implementations', 'signed_contract_file_path', 'signed_contract_file_name', NULL, 'Signed contract'),
            ('contract_implementation_events', 'document_path', 'document_name', 'action', 'Contract implementation'),
            ('messages', 'attachment_path', 'attachment_original_name', 'attachment_kind', 'Message attachment')
        ) AS fields(table_name, path_field, name_field, type_field, category_name)
        WHERE fields.table_name = TG_TABLE_NAME
    LOOP
        path_value := NULLIF(source_row ->> spec.path_field, '');
        IF path_value IS NULL THEN
            CONTINUE;
        END IF;

        INSERT INTO document_repository
            (repository_key, source_table, source_id, source_field, category,
             document_type, file_name, file_path, recorded_at)
        VALUES
            (TG_TABLE_NAME || ':' || source_id_value || ':' || spec.path_field,
             TG_TABLE_NAME, source_id_value, spec.path_field, spec.category_name,
             COALESCE(NULLIF(source_row ->> spec.type_field, ''), spec.category_name),
             COALESCE(NULLIF(source_row ->> spec.name_field, ''), path_value),
             path_value, NULLIF(source_row ->> 'created_at', '')::timestamp);
    END LOOP;

    RETURN NEW;
END;
$$
SQL);

        foreach (self::SOURCE_TABLES as $sourceTable) {
            DB::statement("CREATE TRIGGER sync_document_repository_after_change
                AFTER INSERT OR UPDATE OR DELETE ON {$sourceTable}
                FOR EACH ROW EXECUTE FUNCTION sync_document_repository()");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::SOURCE_TABLES as $sourceTable) {
            DB::statement("DROP TRIGGER IF EXISTS sync_document_repository_after_change ON {$sourceTable}");
        }
        DB::statement('DROP FUNCTION IF EXISTS sync_document_repository()');
        Schema::dropIfExists('document_repository');

        $originalMigration = require database_path('migrations/2026_11_02_000001_create_document_repository_view.php');
        $originalMigration->up();
    }
};
