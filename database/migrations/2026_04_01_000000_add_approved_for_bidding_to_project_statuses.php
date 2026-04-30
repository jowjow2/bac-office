<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE projects MODIFY status ENUM('approved_for_bidding', 'open', 'closed', 'awarded') NOT NULL DEFAULT 'approved_for_bidding'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::table('projects')
                ->where('status', 'approved_for_bidding')
                ->update(['status' => 'open']);

            DB::statement("ALTER TABLE projects MODIFY status ENUM('open', 'closed', 'awarded') NOT NULL DEFAULT 'open'");
        }
    }
};
