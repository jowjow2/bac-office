<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Account details the admin records when creating a user: the position or
 * designation (e.g. "Municipal Engineer") and an office contact number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position', 120)->nullable()->after('office');
            }
            if (! Schema::hasColumn('users', 'contact_number')) {
                $table->string('contact_number', 50)->nullable()->after('position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['contact_number', 'position'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
