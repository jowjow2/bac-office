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
            if (! Schema::hasColumn('awards', 'verification_token')) {
                $table->string('verification_token', 80)->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('awards', 'certificate_number')) {
                $table->string('certificate_number', 40)->nullable()->unique()->after('verification_token');
            }
        });

        DB::table('awards')
            ->select(['id', 'verification_token', 'certificate_number'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $award) {
                $updates = [];

                if (blank($award->verification_token)) {
                    $updates['verification_token'] = Award::newVerificationToken();
                }

                if (blank($award->certificate_number)) {
                    $updates['certificate_number'] = Award::certificateNumberFor((int) $award->id);
                }

                if ($updates !== []) {
                    DB::table('awards')->where('id', $award->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('awards')) {
            return;
        }

        Schema::table('awards', function (Blueprint $table) {
            if (Schema::hasColumn('awards', 'certificate_number')) {
                $table->dropUnique('awards_certificate_number_unique');
                $table->dropColumn('certificate_number');
            }

            if (Schema::hasColumn('awards', 'verification_token')) {
                $table->dropUnique('awards_verification_token_unique');
                $table->dropColumn('verification_token');
            }
        });
    }
};
