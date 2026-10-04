<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Notice to Proceed of an award: the signed PDF and its issuance date,
 * published under Awards & Contracts; the bidder's actual receipt date; and
 * the PhilGEPS posting, recorded by hand (never assumed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            if (! Schema::hasColumn('awards', 'ntp_file_path')) {
                $table->string('ntp_file_path')->nullable();
                $table->date('ntp_issued_on')->nullable();
                $table->timestamp('ntp_published_at')->nullable();
                $table->date('ntp_received_on')->nullable();
                $table->date('ntp_philgeps_posted_on')->nullable();
                $table->string('ntp_philgeps_reference', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dropColumn(['ntp_file_path', 'ntp_issued_on', 'ntp_published_at', 'ntp_received_on', 'ntp_philgeps_posted_on', 'ntp_philgeps_reference']);
        });
    }
};
