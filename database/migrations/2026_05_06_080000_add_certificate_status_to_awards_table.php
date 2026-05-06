<?php

use App\Models\Award;
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

        Schema::table('awards', function (Blueprint $table) {
            if (! Schema::hasColumn('awards', 'certificate_status')) {
                $table->string('certificate_status', 20)
                    ->default(Award::STATUS_VALID)
                    ->after('qr_token');
            }
        });

        DB::table('awards')
            ->whereNull('certificate_status')
            ->update(['certificate_status' => Award::STATUS_VALID]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('awards') || ! Schema::hasColumn('awards', 'certificate_status')) {
            return;
        }

        Schema::table('awards', function (Blueprint $table) {
            $table->dropColumn('certificate_status');
        });
    }
};
