<?php
/**
 * Writes the demo purchase requests, projects and awarded projects as PostgreSQL for Neon, in files that each fit
 * the 100,000-character limit of the Vercel/Neon query editor (run them in order, one at a time):
 *
 *   tools/demo-procurement/demo-procurement-1.sql ... -N.sql   adds the records (safe to run again)
 *   tools/demo-procurement/demo-procurement-remove.sql         removes them
 *
 * It runs the seeders on a throwaway in-memory SQLite database (never the configured one) and converts the rows.
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
DB::purge();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Models\User::create(['name' => 'x', 'email' => 'x@example.org', 'password' => 'x', 'role' => 'admin', 'status' => 'active']);
(new Database\Seeders\DemoProcurementSeeder)->run();
(new Database\Seeders\DemoAwardedSeeder)->run();

const LIMIT = 85000;
$admin = 'v_admin';
$q = fn ($v) => $v === null ? 'NULL' : (is_bool($v) ? ($v ? 'true' : 'false') : (is_int($v) || is_float($v) ? (string) $v : "'".str_replace("'", "''", (string) $v)."'"));
$t = fn ($c, $v) => $q($v).(is_string($v) && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $v) ? '::timestamp' : ($c === 'items' ? '::json' : (is_string($v) && preg_match('/^\d{4}-\d\d-\d\d$/', $v) ? '::date' : '')));
$refOf = fn ($id) => DB::table('projects')->where('id', $id)->value('reference_no');
$userSql = fn ($id) => '(SELECT id FROM users WHERE email = '.$q(DB::table('users')->where('id', $id)->value('email')).')';
$projectSql = fn ($id) => '(SELECT id FROM projects WHERE reference_no = '.$q($refOf($id)).')';
$convert = function (array $row, array $skip, array $map) use ($t, $admin) {
    $cols = []; $vals = [];
    foreach ($row as $c => $v) {
        if ($v === null || in_array($c, $skip, true)) continue;
        $cols[] = $c;
        $vals[] = $map[$c] ?? (preg_match('/_by$/', $c) ? $admin : $t($c, $v));
    }

    return [$cols, $vals];
};

// ---- Base records: purchase requests, projects (all 50), their schedules and requirements, and the fee fix ----
$base = [];
foreach (DB::table('procurement_requests')->where('reference_no', 'like', 'PR-2026-D%')->orderBy('id')->get() as $row) {
    $row = (array) $row; unset($row['id'], $row['seen_at']); // seen_at only exists once its migration has run
    $cols = []; $vals = [];
    foreach ($row as $c => $v) { if ($v === null) continue; $cols[] = $c; $vals[] = $c === 'budget_available' ? $q((bool) $v) : $t($c, $v); }
    $base[] = 'INSERT INTO procurement_requests ('.implode(', ', $cols).') SELECT '.implode(', ', $vals).' WHERE NOT EXISTS (SELECT 1 FROM procurement_requests WHERE reference_no = '.$q($row['reference_no']).');';
}
foreach (DB::table('projects')->where('reference_no', 'like', 'SJ-BAC-2026-%-D__')->orderBy('id')->get() as $row) {
    $row = (array) $row; $id = $row['id']; unset($row['id'], $row['seen_at']); // seen_at only exists once its migration has run
    $reqRef = $row['procurement_request_id'] ? DB::table('procurement_requests')->where('id', $row['procurement_request_id'])->value('reference_no') : null;
    [$cols, $vals] = $convert($row, [], [
        'procurement_request_id' => '(SELECT id FROM procurement_requests WHERE reference_no = '.$q($reqRef).')',
        'bid_security_required' => $q((bool) $row['bid_security_required']),
    ]);
    $ref = $q($row['reference_no']);
    $base[] = 'INSERT INTO projects ('.implode(', ', $cols).') SELECT '.implode(', ', $vals).' WHERE NOT EXISTS (SELECT 1 FROM projects WHERE reference_no = '.$ref.');';
    $sch = (array) DB::table('project_schedules')->where('project_id', $id)->first(); unset($sch['id']);
    $sc = []; $sv = [];
    foreach ($sch as $c => $v) { if ($v === null || $c === 'project_id') continue; $sc[] = $c; $sv[] = $t($c, $v); }
    $base[] = 'INSERT INTO project_schedules (project_id, '.implode(', ', $sc).') SELECT p.id, '.implode(', ', $sv).' FROM projects p WHERE p.reference_no = '.$ref.' AND NOT EXISTS (SELECT 1 FROM project_schedules s WHERE s.project_id = p.id);';
    $base[] = "INSERT INTO project_requirements (project_id, required_documents, created_at, updated_at) SELECT p.id, '[]'::json, now(), now() FROM projects p WHERE p.reference_no = {$ref} AND NOT EXISTS (SELECT 1 FROM project_requirements r WHERE r.project_id = p.id);";
}
// Demo projects added by an earlier run without a bidding documents fee: set the schedule maximum for their ABC.
$case = [];
foreach (App\Support\BiddingDocumentsFee::schedule() as $row) {
    $case[] = $row['limit'] === null ? 'ELSE '.$row['maximum'] : 'WHEN budget <= '.$row['limit'].' THEN '.$row['maximum'];
}
$base[] = "UPDATE projects SET bidding_fee_mode = 'schedule', bidding_documents_fee = CASE ".implode(' ', $case)." END WHERE reference_no LIKE 'SJ-BAC-2026-%-D__' AND bidding_fee_mode IS NULL;";

// ---- Awarded projects: one unit per project (its bids and its award), only when the demo bidder accounts exist ----
$emails = DB::table('bids')->join('users', 'users.id', '=', 'bids.user_id')->distinct()->pluck('users.email')->all();
$units = [];
foreach (DB::table('awards')->orderBy('id')->get() as $award) {
    $award = (array) $award;
    $unit = [];
    foreach (DB::table('bids')->where('project_id', $award['project_id'])->orderBy('id')->get() as $bid) {
        $row = (array) $bid;
        [$cols, $vals] = $convert($row, ['id'], ['project_id' => $projectSql($row['project_id']), 'user_id' => $userSql($row['user_id'])]);
        $unit[] = 'INSERT INTO bids ('.implode(', ', $cols).') SELECT '.implode(', ', $vals).' WHERE NOT EXISTS (SELECT 1 FROM bids WHERE receipt_no = '.$q($row['receipt_no']).');';
    }
    $bidSql = '(SELECT b.id FROM bids b WHERE b.project_id = '.$projectSql($award['project_id']).' AND b.user_id = '.$userSql($award['bidder_id']).')';
    [$cols, $vals] = $convert($award, ['id', 'certificate_number'], ['project_id' => $projectSql($award['project_id']), 'bid_id' => $bidSql, 'bidder_id' => $userSql($award['bidder_id'])]);
    $unit[] = 'INSERT INTO awards ('.implode(', ', $cols).') SELECT '.implode(', ', $vals).' WHERE NOT EXISTS (SELECT 1 FROM awards WHERE project_id = '.$projectSql($award['project_id']).');';
    $units[] = $unit;
}
$certificate = "UPDATE awards SET certificate_number = 'BAC-AWD-' || to_char(created_at, 'YYYY') || '-' || lpad(id::text, 6, '0') WHERE certificate_number IS NULL AND project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');";
// Demo awards added by an earlier run have no recorded bid opening or financial opening: record them, so their bids are
// not treated as sealed any more.
$reopen = [
    "UPDATE projects SET bids_opened_at = (SELECT s.bid_opening_date FROM project_schedules s WHERE s.project_id = projects.id), bids_opened_by = v_admin WHERE status = 'awarded' AND bids_opened_at IS NULL AND reference_no LIKE 'SJ-BAC-2026-%-D__';",
    "UPDATE bids SET financial_opened_at = documents_validated_at + interval '3 hours', financial_opened_by = v_admin WHERE financial_opened_at IS NULL AND award_decision = 'approved' AND documents_validated_at IS NOT NULL AND project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');",
];
$guard = 'IF (SELECT count(*) FROM users WHERE email IN ('.implode(', ', array_map($q, $emails)).')) = '.count($emails).' THEN';
$guardElse = "ELSE\nRAISE NOTICE 'The awarded projects of this part were not added: the demo bidder accounts are not all in the database. Load the demo accounts first, then run this part again.';\nEND IF;";

$parts = [['kind' => 'base', 'statements' => $base]];
$current = [];
$size = 0;
foreach ($units as $unit) {
    $unitSize = array_sum(array_map('strlen', $unit)) + count($unit);
    if ($current !== [] && $size + $unitSize > LIMIT - strlen($guard) - 1000) {
        $parts[] = ['kind' => 'awarded', 'statements' => $current];
        $current = []; $size = 0;
    }
    array_push($current, ...$unit);
    $size += $unitSize;
}
if ($current !== []) $parts[] = ['kind' => 'awarded', 'statements' => $current];

// The base part is also split if it is too large.
$final = [];
foreach ($parts as $part) {
    if ($part['kind'] === 'awarded') { $final[] = $part; continue; }
    $chunk = []; $s = 0;
    foreach ($part['statements'] as $st) {
        if ($chunk !== [] && $s + strlen($st) > LIMIT - 1000) { $final[] = ['kind' => 'base', 'statements' => $chunk]; $chunk = []; $s = 0; }
        $chunk[] = $st; $s += strlen($st) + 1;
    }
    if ($chunk !== []) $final[] = ['kind' => 'base', 'statements' => $chunk];
}

foreach (glob(__DIR__.'/demo-procurement*.sql') as $old) unlink($old);
$total = count($final);
foreach ($final as $i => $part) {
    $lines = [
        '-- Demo purchase requests and projects, part '.($i + 1).' of '.$total.'. Run the parts in order, one at a time. Safe to run again.',
        'DO $$',
        'DECLARE v_admin integer := (SELECT id FROM users WHERE role = \'admin\' AND status = \'active\' ORDER BY id LIMIT 1);',
        'BEGIN',
    ];
    if ($part['kind'] === 'awarded') {
        $lines[] = $guard;
        array_push($lines, ...$part['statements']);
        $lines[] = $certificate;
        array_push($lines, ...$reopen);
        $lines[] = $guardElse;
    } else {
        array_push($lines, ...$part['statements']);
    }
    $lines[] = 'END $$;';
    $sql = implode("\n", $lines)."\n";
    file_put_contents(__DIR__.'/demo-procurement-'.($i + 1).'.sql', $sql);
    echo 'part '.($i + 1).': '.strlen($sql)." characters\n";
}

$demo = "SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__'";
file_put_contents(__DIR__.'/demo-procurement-remove.sql', implode("\n", [
    '-- Removes the demo purchase requests, projects, bids and awards (PR-2026-D001..D020, SJ-BAC-2026-*-D01..D50).',
    'DO $$',
    'BEGIN',
    "DELETE FROM contract_implementation_events WHERE contract_implementation_id IN (SELECT id FROM contract_implementations WHERE award_id IN (SELECT id FROM awards WHERE project_id IN ({$demo})));",
    "DELETE FROM contract_implementations WHERE award_id IN (SELECT id FROM awards WHERE project_id IN ({$demo}));",
    "DELETE FROM awards WHERE project_id IN ({$demo});",
    "DELETE FROM bids WHERE project_id IN ({$demo});",
    "DELETE FROM project_schedules WHERE project_id IN ({$demo});",
    "DELETE FROM project_requirements WHERE project_id IN ({$demo});",
    "DELETE FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__';",
    "DELETE FROM procurement_requests WHERE reference_no LIKE 'PR-2026-D%';",
    'END $$;',
])."\n");
echo "written\n";
