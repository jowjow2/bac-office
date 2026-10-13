<?php
/**
 * Writes tools/demo-procurement/demo-procurement.sql: the demo purchase requests and projects as PostgreSQL for
 * Neon. It runs the seeder on a throwaway in-memory SQLite database (never the configured one) and converts the rows.
 *
 *   php tools/demo-procurement/make-sql.php
 */
use Illuminate\Support\Facades\DB;

putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=:memory:'); putenv('APP_ENV=testing');
$_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = ':memory:';
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Illuminate\Support\Facades\DB::purge();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Models\User::create(['name' => 'x', 'email' => 'x@example.org', 'password' => 'x', 'role' => 'admin', 'status' => 'active']);
(new Database\Seeders\DemoProcurementSeeder)->run();

$admin = "(SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1)";
$q = fn ($v) => $v === null ? 'NULL' : (is_bool($v) ? ($v ? 'true' : 'false') : (is_int($v) || is_float($v) ? (string) $v : "'".str_replace("'", "''", (string) $v)."'"));
$t = fn ($c, $v) => $q($v).(is_string($v) && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $v) ? '::timestamp' : ($c === 'items' ? '::json' : ''));
$out = ["-- Demo purchase requests and projects (20 each). Safe to run again: existing references are skipped.", "-- Remove them with the DELETE block at the end.", "BEGIN;", ''];

$skip = ['id'];
foreach (DB::table('procurement_requests')->where('reference_no', 'like', 'PR-2026-D%')->orderBy('id')->get() as $row) {
    $row = (array) $row; foreach ($skip as $k) unset($row[$k]);
    $cols = []; $vals = [];
    foreach ($row as $c => $v) { if ($v === null) continue; $cols[] = $c; $vals[] = $c === 'budget_available' ? $q((bool) $v) : $t($c, $v); }
    $out[] = 'INSERT INTO procurement_requests ('.implode(', ', $cols).') SELECT '.implode(', ', $vals).' WHERE NOT EXISTS (SELECT 1 FROM procurement_requests WHERE reference_no = '.$q($row['reference_no']).');';
}
$out[] = '';
foreach (DB::table('projects')->where('reference_no', 'like', 'SJ-BAC-2026-%-D__')->orderBy('id')->get() as $row) {
    $row = (array) $row; $id = $row['id']; unset($row['id']);
    $reqRef = $row['procurement_request_id'] ? DB::table('procurement_requests')->where('id', $row['procurement_request_id'])->value('reference_no') : null;
    $cols = []; $vals = [];
    foreach ($row as $c => $v) {
        if ($v === null) continue;
        $cols[] = $c;
        if ($c === 'procurement_request_id') $vals[] = '(SELECT id FROM procurement_requests WHERE reference_no = '.$q($reqRef).')';
        elseif (in_array($c, ['created_by', 'published_by'], true)) $vals[] = $admin;
        elseif ($c === 'bid_security_required') $vals[] = $q((bool) $v);
        else $vals[] = $t($c, $v);
    }
    $ref = $q($row['reference_no']);
    $out[] = 'INSERT INTO projects ('.implode(', ', $cols).') SELECT '.implode(', ', $vals).' WHERE NOT EXISTS (SELECT 1 FROM projects WHERE reference_no = '.$ref.');';
    $sch = (array) DB::table('project_schedules')->where('project_id', $id)->first(); unset($sch['id']);
    $sc = []; $sv = [];
    foreach ($sch as $c => $v) { if ($v === null || $c === 'project_id') continue; $sc[] = $c; $sv[] = $t($c, $v); }
    $out[] = 'INSERT INTO project_schedules (project_id, '.implode(', ', $sc).') SELECT p.id, '.implode(', ', $sv).' FROM projects p WHERE p.reference_no = '.$ref.' AND NOT EXISTS (SELECT 1 FROM project_schedules s WHERE s.project_id = p.id);';
    $out[] = "INSERT INTO project_requirements (project_id, required_documents, created_at, updated_at) SELECT p.id, '[]'::json, now(), now() FROM projects p WHERE p.reference_no = {$ref} AND NOT EXISTS (SELECT 1 FROM project_requirements r WHERE r.project_id = p.id);";
}
$out[] = ''; $out[] = 'COMMIT;'; $out[] = '';
$out[] = "-- To remove the demo records:";
$out[] = "-- BEGIN;";
$out[] = "-- DELETE FROM project_schedules WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');";
$out[] = "-- DELETE FROM project_requirements WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');";
$out[] = "-- DELETE FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__';";
$out[] = "-- DELETE FROM procurement_requests WHERE reference_no LIKE 'PR-2026-D%';";
$out[] = "-- COMMIT;";
file_put_contents(__DIR__.'/demo-procurement.sql', implode("\n", $out)."\n");
echo "written\n";
