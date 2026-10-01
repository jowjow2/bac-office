<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The system now runs on Philippine Standard Time (Asia/Manila, used by the
 * LGU of San Jose, Occidental Mindoro) instead of UTC (config/app.php).
 *
 * System-recorded TIMESTAMP columns (created_at, submitted_at,
 * notice_of_award_at, ...) were written as UTC wall-clock time, so they are
 * moved forward 8 hours to the same instant in Philippine time.
 *
 * The DATETIME schedule columns (project deadline, bid opening, pre-bid
 * conference, clarification deadline) are NOT changed: they were typed by the
 * BAC as local Philippine time and were already correct.
 */
return new class extends Migration
{
    private const OFFSET_HOURS = 8;

    public function up(): void
    {
        $this->shift(self::OFFSET_HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::OFFSET_HOURS);
    }

    private function shift(int $hours): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            return; // SQLite test databases start empty.
        }

        foreach ($this->systemTimestampColumns($driver) as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // One UPDATE per table so no column is auto-touched by another.
            $assignments = collect($columns)->mapWithKeys(fn (string $column) => [
                $column => DB::raw($driver === 'pgsql'
                    ? "\"{$column}\" + interval '{$hours} hours'"
                    : "DATE_ADD(`{$column}`, INTERVAL {$hours} HOUR)"),
            ])->all();

            DB::table($table)->update($assignments);
        }
    }

    /**
     * @return array<string, array<int, string>> table => timestamp columns
     */
    private function systemTimestampColumns(string $driver): array
    {
        $rows = $driver === 'pgsql'
            ? DB::select("select table_name, column_name from information_schema.columns where table_schema = current_schema() and data_type in ('timestamp without time zone', 'timestamp with time zone')")
            : DB::select("select table_name as table_name, column_name as column_name from information_schema.columns where table_schema = database() and data_type = 'timestamp'");

        $userEntered = [
            'projects' => ['deadline'],
            'project_schedules' => ['pre_bid_conference_date', 'clarification_deadline', 'bid_submission_deadline', 'bid_opening_date'],
        ];

        $columns = [];
        foreach ($rows as $row) {
            $table = $row->table_name ?? $row->TABLE_NAME;
            $column = $row->column_name ?? $row->COLUMN_NAME;

            if (in_array($column, $userEntered[$table] ?? [], true) || in_array($table, ['migrations', 'sessions', 'cache', 'jobs'], true)) {
                continue;
            }

            $columns[$table][] = $column;
        }

        return $columns;
    }
};
