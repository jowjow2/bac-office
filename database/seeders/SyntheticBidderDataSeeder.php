<?php

namespace Database\Seeders;

use App\Models\Award;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SyntheticBidderDataSeeder extends Seeder
{
    private const FILE_NAME = 'BAC_100_Synthetic_Bidder_Data.xlsx';
    private const NOTICE = 'Synthetic test data only - not an official LGU procurement record.';

    private const REQUIRED_HEADERS = [
        'Bidder_ID',
        'Business_Name',
        'Business_Type',
        'Municipality',
        'Project_ID',
        'Project_Title',
        'Project_Category',
        'ABC_PHP',
        'Bid_Amount_PHP',
        'Bid_vs_ABC_%',
        'Eligibility_Status',
        'Technical_Status',
        'Financial_Status',
        'Bid_Rank',
        'Bid_Result',
        'Award_Status',
        'Submission_Date',
        'Notes',
    ];

    private array $columnCache = [];

    public function run(): void
    {
        $this->ensureDatabaseShape();

        $path = $this->workbookPath();
        if (! is_file($path)) {
            throw new RuntimeException(
                'Synthetic bidder workbook not found. Set BAC_SYNTHETIC_BIDDER_XLSX to the full .xlsx path, '
                . 'or place ' . self::FILE_NAME . ' in storage/app/imports or your Downloads folder.'
            );
        }

        $rows = $this->readWorkbook($path);
        $this->validateRows($rows);

        $stats = [
            'projects_created' => 0,
            'projects_updated' => 0,
            'users_created' => 0,
            'users_updated' => 0,
            'bidders_created' => 0,
            'bidders_updated' => 0,
            'bids_created' => 0,
            'bids_updated' => 0,
            'awards_created' => 0,
            'awards_updated' => 0,
        ];

        DB::transaction(function () use ($rows, &$stats): void {
            $projects = $this->upsertProjects($rows, $stats);

            foreach ($rows as $row) {
                $user = $this->upsertUser($row, $stats);
                $this->upsertBidder($row, $user, $stats);
                $bid = $this->upsertBid($row, $user, $projects[$row['Project_ID']], $stats);

                if ($this->isWinner($row)) {
                    $this->upsertAward($row, $bid, $projects[$row['Project_ID']], $stats);
                }
            }
        });

        $this->command?->info('Imported synthetic BAC bidder dataset from: ' . $path);
        $this->command?->table(['Item', 'Count'], collect($stats)->map(
            fn (int $count, string $key) => [Str::headline($key), $count]
        )->values()->all());
    }

    private function ensureDatabaseShape(): void
    {
        $required = [
            'users' => ['name', 'email', 'username', 'password', 'role', 'status', 'company', 'registration_no'],
            'bidders' => ['user_id', 'company_name', 'contact_number', 'business_address', 'approval_status'],
            'projects' => ['title', 'slug', 'description', 'budget', 'status'],
            'bids' => ['user_id', 'project_id', 'bid_amount', 'status', 'eligibility_status', 'workflow_step', 'notes'],
            'awards' => ['project_id', 'bid_id', 'contract_amount', 'contract_date', 'status'],
        ];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table [{$table}] is missing.");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Required column [{$table}.{$column}] is missing.");
                }
            }
        }
    }

    private function workbookPath(): string
    {
        $configuredPath = trim((string) env('BAC_SYNTHETIC_BIDDER_XLSX', ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        $storagePath = storage_path('app/imports/' . self::FILE_NAME);
        if (is_file($storagePath)) {
            return $storagePath;
        }

        $userProfile = getenv('USERPROFILE');
        if (is_string($userProfile) && $userProfile !== '') {
            return $userProfile . DIRECTORY_SEPARATOR . 'Downloads' . DIRECTORY_SEPARATOR . self::FILE_NAME;
        }

        return $storagePath;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($path);

        if ($opened !== true) {
            throw new RuntimeException('Unable to open workbook: ' . $path);
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);

            foreach ($this->worksheetPaths($zip) as $worksheetPath) {
                $table = $this->worksheetRows($zip, $worksheetPath, $sharedStrings);
                if ($table === []) {
                    continue;
                }

                $headers = array_map(fn (string $header) => trim($header), array_shift($table));
                if (count(array_intersect(self::REQUIRED_HEADERS, $headers)) !== count(self::REQUIRED_HEADERS)) {
                    continue;
                }

                return $this->recordsFromTable($headers, $table);
            }
        } finally {
            $zip->close();
        }

        throw new RuntimeException('No worksheet with the expected synthetic bidder headers was found.');
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $sharedStrings = [];
        $root = $this->xml($xml);
        $root->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($root->xpath('//x:si') ?: [] as $item) {
            $text = '';
            foreach ($item->xpath('.//x:t') ?: [] as $part) {
                $text .= (string) $part;
            }
            $sharedStrings[] = $text;
        }

        return $sharedStrings;
    }

    /**
     * @return array<int, string>
     */
    private function worksheetPaths(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relationsXml === false) {
            return ['xl/worksheets/sheet1.xml'];
        }

        $workbook = $this->xml($workbookXml);
        $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $relations = $this->xml($relationsXml);
        $relations->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $targetsById = [];
        foreach ($relations->xpath('//r:Relationship') ?: [] as $relation) {
            $id = (string) $relation['Id'];
            $target = str_replace('\\', '/', (string) $relation['Target']);
            $targetsById[$id] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/' . ltrim($target, '/');
        }

        $paths = [];
        foreach ($workbook->xpath('//x:sheets/x:sheet') ?: [] as $sheet) {
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationId = (string) ($attributes['id'] ?? '');

            if (isset($targetsById[$relationId])) {
                $paths[] = $targetsById[$relationId];
            }
        }

        return $paths !== [] ? $paths : ['xl/worksheets/sheet1.xml'];
    }

    /**
     * @param array<int, string> $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function worksheetRows(ZipArchive $zip, string $worksheetPath, array $sharedStrings): array
    {
        $xml = $zip->getFromName($worksheetPath);
        if ($xml === false) {
            return [];
        }

        $sheet = $this->xml($xml);
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $row) {
            $cells = [];

            foreach ($row->xpath('x:c') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                $index = $this->columnIndex($reference);
                $cells[$index] = $this->cellValue($cell, $sharedStrings);
            }

            if ($cells === []) {
                continue;
            }

            $maxIndex = max(array_keys($cells));
            $values = [];
            for ($index = 0; $index <= $maxIndex; $index++) {
                $values[$index] = $cells[$index] ?? '';
            }

            if (collect($values)->every(fn (string $value) => trim($value) === '')) {
                continue;
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $table
     * @return array<int, array<string, string>>
     */
    private function recordsFromTable(array $headers, array $table): array
    {
        $records = [];

        foreach ($table as $row) {
            $record = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $record[$header] = trim((string) ($row[$index] ?? ''));
            }

            if ($record !== []) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function xml(string $contents): SimpleXMLElement
    {
        $xml = simplexml_load_string($contents);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Unable to parse workbook XML.');
        }

        return $xml;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];
        $valueNodes = $cell->xpath('x:v') ?: [];
        $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? $value;
        }

        if ($type === 'inlineStr') {
            $inlineText = '';
            foreach ($cell->xpath('x:is/x:t') ?: [] as $part) {
                $inlineText .= (string) $part;
            }

            return $inlineText;
        }

        return $value;
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/^[A-Z]+/', $cellReference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function validateRows(array $rows): void
    {
        if (count($rows) !== 100) {
            throw new RuntimeException('Expected 100 synthetic bidder records, found ' . count($rows) . '.');
        }

        foreach (self::REQUIRED_HEADERS as $header) {
            if (! array_key_exists($header, $rows[0])) {
                throw new RuntimeException("Required workbook column [{$header}] is missing.");
            }
        }

        $projectWinners = [];
        foreach ($rows as $row) {
            foreach (['Bidder_ID', 'Business_Name', 'Project_ID', 'Project_Title', 'ABC_PHP', 'Bid_Amount_PHP'] as $field) {
                if (($row[$field] ?? '') === '') {
                    throw new RuntimeException("Workbook row for bidder [{$row['Bidder_ID']}] is missing [{$field}].");
                }
            }

            $projectId = $row['Project_ID'];
            $projectWinners[$projectId] ??= 0;

            if ($this->isWinner($row)) {
                $projectWinners[$projectId]++;
            }
        }

        foreach ($projectWinners as $projectId => $winnerCount) {
            if ($winnerCount !== 1) {
                throw new RuntimeException("Expected exactly one winner for project [{$projectId}], found {$winnerCount}.");
            }
        }
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<string, int> $stats
     * @return array<string, Project>
     */
    private function upsertProjects(array $rows, array &$stats): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $projectId = $row['Project_ID'];
            $groups[$projectId]['rows'][] = $row;
            $groups[$projectId]['project'] ??= $row;
        }

        $projects = [];
        foreach ($groups as $projectId => $group) {
            $row = $group['project'];
            $submissionDates = collect($group['rows'])->map(fn (array $item) => $this->excelDate($item['Submission_Date']));
            $postedAt = $submissionDates->min()->subDays(14);
            $deadline = $submissionDates->max()->endOfDay();
            $updatedAt = $deadline->addDays(5);

            $project = Project::query()->firstOrNew([
                'slug' => $this->projectSlug($projectId),
            ]);
            $wasNew = ! $project->exists;

            $project->forceFill($this->onlyColumns('projects', [
                'title' => $row['Project_Title'],
                'description' => self::NOTICE . PHP_EOL . 'Source Project ID: ' . $projectId,
                'category' => $row['Project_Category'],
                'location' => null,
                'procurement_mode' => 'public_bidding',
                'source_of_fund' => 'Synthetic test data',
                'contract_duration' => null,
                'budget' => $this->money($row['ABC_PHP']),
                'deadline' => $deadline,
                'status' => 'awarded',
                'created_by' => null,
                'created_at' => $postedAt,
                'updated_at' => $updatedAt,
            ]));
            $project->save();

            $stats[$wasNew ? 'projects_created' : 'projects_updated']++;
            $projects[$projectId] = $project;
        }

        return $projects;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, int> $stats
     */
    private function upsertUser(array $row, array &$stats): User
    {
        $submissionDate = $this->excelDate($row['Submission_Date']);
        $user = User::query()->firstOrNew([
            'email' => $this->emailFor($row['Bidder_ID']),
        ]);
        $wasNew = ! $user->exists;

        $attributes = [
            'name' => $row['Business_Name'],
            'username' => $this->usernameFor($row['Bidder_ID']),
            'role' => 'bidder',
            'status' => 'active',
            'office' => null,
            'company' => $row['Business_Name'],
            'registration_no' => $row['Bidder_ID'],
            'created_at' => $submissionDate,
            'updated_at' => now(),
        ];

        if ($wasNew) {
            $attributes['password'] = Hash::make(Str::random(40));
        }

        $user->forceFill($this->onlyColumns('users', $attributes));
        $user->save();

        $stats[$wasNew ? 'users_created' : 'users_updated']++;

        return $user;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, int> $stats
     */
    private function upsertBidder(array $row, User $user, array &$stats): Bidder
    {
        $submissionDate = $this->excelDate($row['Submission_Date']);
        $bidder = Bidder::query()->firstOrNew([
            'user_id' => $user->id,
        ]);
        $wasNew = ! $bidder->exists;

        $bidder->forceFill($this->onlyColumns('bidders', [
            'company_name' => $row['Business_Name'],
            'contact_person' => $row['Business_Name'],
            'contact_number' => 'Not provided',
            'business_address' => $row['Municipality'],
            'document_path' => null,
            'approval_status' => 'approved',
            'rejection_reason' => null,
            'approved_at' => $submissionDate,
            'approved_by' => null,
            'created_at' => $submissionDate,
            'updated_at' => now(),
        ]));
        $bidder->save();

        $stats[$wasNew ? 'bidders_created' : 'bidders_updated']++;

        return $bidder;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, int> $stats
     */
    private function upsertBid(array $row, User $user, Project $project, array &$stats): Bid
    {
        [$status, $workflowStep] = $this->bidStatusAndWorkflow($row);
        $timeline = $this->bidTimeline($row, $status, $workflowStep);

        $bid = Bid::query()->firstOrNew([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);
        $wasNew = ! $bid->exists;

        $bid->forceFill($this->onlyColumns('bids', [
            'bid_amount' => $this->money($row['Bid_Amount_PHP']),
            'proposal_file' => null,
            'eligibility_file' => null,
            'status' => $status,
            'eligibility_status' => strcasecmp($row['Eligibility_Status'], 'Eligible') === 0
                ? Bid::ELIGIBILITY_VALID
                : Bid::ELIGIBILITY_INVALID,
            'eligibility_reviewed_at' => $timeline['eligibility_reviewed_at'],
            'eligibility_reviewed_by' => null,
            'workflow_step' => $workflowStep,
            'workflow_step_updated_at' => $timeline['workflow_step_updated_at'],
            'workflow_step_updated_by' => null,
            'documents_validated_at' => $timeline['documents_validated_at'],
            'documents_validated_by' => null,
            'bac_evaluation_at' => $timeline['bac_evaluation_at'],
            'bac_evaluation_by' => null,
            'approved_at' => $timeline['approved_at'],
            'approved_by' => null,
            'disqualified_at' => $timeline['disqualified_at'],
            'disqualified_by' => null,
            'awarded_at' => $timeline['awarded_at'],
            'awarded_by' => null,
            'notes' => $this->bidNotes($row),
            'created_at' => $timeline['submitted_at'],
            'updated_at' => $timeline['workflow_step_updated_at'],
        ]));
        $bid->save();

        $stats[$wasNew ? 'bids_created' : 'bids_updated']++;

        return $bid;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, int> $stats
     */
    private function upsertAward(array $row, Bid $bid, Project $project, array &$stats): Award
    {
        $awardDate = $this->excelDate($row['Submission_Date'])->addDays(5);
        $award = Award::query()->firstOrNew([
            'project_id' => $project->id,
        ]);
        $wasNew = ! $award->exists;

        $award->forceFill($this->onlyColumns('awards', [
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'contract_amount' => $this->money($row['Bid_Amount_PHP']),
            'contract_date' => $awardDate->toDateString(),
            'status' => Award::STATUS_VALID,
            'certificate_status' => Award::STATUS_VALID,
            'certificate_uploaded_at' => null,
            'certificate_revoked_at' => null,
            'certificate_revoked_by' => null,
            'notes' => $this->awardNotes($row),
            'created_at' => $awardDate,
            'updated_at' => $awardDate,
        ]));
        $award->save();
        $award->ensureCertificateIdentity();

        $stats[$wasNew ? 'awards_created' : 'awards_updated']++;

        return $award;
    }

    /**
     * @param array<string, string> $row
     * @return array{0: string, 1: string}
     */
    private function bidStatusAndWorkflow(array $row): array
    {
        if ($this->isWinner($row)) {
            return ['awarded', Bid::STEP_AWARDED];
        }

        if (strcasecmp($row['Bid_Result'], 'Disqualified') === 0) {
            return ['rejected', Bid::STEP_DISQUALIFIED];
        }

        return ['approved', Bid::STEP_NOT_AWARDED];
    }

    /**
     * @param array<string, string> $row
     * @return array<string, CarbonImmutable|null>
     */
    private function bidTimeline(array $row, string $status, string $workflowStep): array
    {
        $submittedAt = $this->excelDate($row['Submission_Date']);
        $eligibilityReviewedAt = $submittedAt->addDay();
        $evaluationAt = $submittedAt->addDays(2);
        $finalizedAt = $submittedAt->addDays(3);
        $awardAt = $submittedAt->addDays(5);
        $eligible = strcasecmp($row['Eligibility_Status'], 'Eligible') === 0;

        return [
            'submitted_at' => $submittedAt,
            'eligibility_reviewed_at' => $eligibilityReviewedAt,
            'documents_validated_at' => $eligible ? $eligibilityReviewedAt : null,
            'bac_evaluation_at' => $eligible && strcasecmp($row['Technical_Status'], 'Not Evaluated') !== 0
                ? $evaluationAt
                : null,
            'approved_at' => in_array($status, ['approved', 'awarded'], true) ? $finalizedAt : null,
            'disqualified_at' => $status === 'rejected'
                ? ($eligible ? $finalizedAt : $eligibilityReviewedAt)
                : null,
            'awarded_at' => $status === 'awarded' ? $awardAt : null,
            'workflow_step_updated_at' => match ($workflowStep) {
                Bid::STEP_AWARDED => $awardAt,
                Bid::STEP_DISQUALIFIED => $status === 'rejected' && ! $eligible ? $eligibilityReviewedAt : $finalizedAt,
                default => $finalizedAt,
            },
        ];
    }

    /**
     * @param array<string, string> $row
     */
    private function isWinner(array $row): bool
    {
        return strcasecmp($row['Award_Status'], 'Winner') === 0
            || strcasecmp($row['Bid_Result'], 'Awarded') === 0;
    }

    /**
     * @param array<string, string> $row
     */
    private function bidNotes(array $row): string
    {
        $rank = trim($row['Bid_Rank']) !== '' ? $row['Bid_Rank'] : 'N/A';
        $bidVsAbc = is_numeric($row['Bid_vs_ABC_%'])
            ? number_format(((float) $row['Bid_vs_ABC_%']) * 100, 2) . '%'
            : $row['Bid_vs_ABC_%'];

        $lines = [
            self::NOTICE,
            'Source Bidder ID: ' . $row['Bidder_ID'],
            'Source Project ID: ' . $row['Project_ID'],
            'Business Type: ' . $row['Business_Type'],
            'Municipality: ' . $row['Municipality'],
            'ABC: PHP ' . number_format((float) $this->money($row['ABC_PHP']), 2),
            'Bid vs ABC: ' . $bidVsAbc,
            'Eligibility Status: ' . $row['Eligibility_Status'],
            'Technical Status: ' . $row['Technical_Status'],
            'Financial Status: ' . $row['Financial_Status'],
            'Bid Rank: ' . $rank,
            'Bid Result: ' . $row['Bid_Result'],
            'Award Status: ' . $row['Award_Status'],
        ];

        if (trim($row['Notes']) !== '') {
            $lines[] = 'Spreadsheet Notes: ' . $row['Notes'];
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, string> $row
     */
    private function awardNotes(array $row): string
    {
        return implode(PHP_EOL, [
            self::NOTICE,
            'Source Bidder ID: ' . $row['Bidder_ID'],
            'Source Project ID: ' . $row['Project_ID'],
            'Award Status: ' . $row['Award_Status'],
            'Bid Result: ' . $row['Bid_Result'],
            'Spreadsheet Notes: ' . $row['Notes'],
        ]);
    }

    private function excelDate(string $value): CarbonImmutable
    {
        if (is_numeric($value)) {
            return CarbonImmutable::create(1899, 12, 30, 9, 0, 0)->addDays((int) $value);
        }

        return CarbonImmutable::parse($value)->setTime(9, 0);
    }

    private function money(string $value): string
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value) ?: '0';

        return number_format((float) $normalized, 2, '.', '');
    }

    private function projectSlug(string $projectId): string
    {
        return 'synthetic-' . Str::slug($projectId);
    }

    private function emailFor(string $bidderId): string
    {
        return Str::lower(Str::slug($bidderId, '.')) . '@synthetic-bidders.bac.test';
    }

    private function usernameFor(string $bidderId): string
    {
        return 'synthetic_' . Str::of($bidderId)->lower()->replace('-', '_');
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function onlyColumns(string $table, array $attributes): array
    {
        $columns = $this->columnCache[$table] ??= array_flip(Schema::getColumnListing($table));

        return array_filter(
            $attributes,
            fn (string $column): bool => isset($columns[$column]),
            ARRAY_FILTER_USE_KEY
        );
    }
}
