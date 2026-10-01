<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - projects.bids_opened_at: the authorized bid opening. Proposals and bid
 *   amounts stay sealed until it is recorded.
 * - bid_trackings gets structured columns so it can serve as the single event
 *   history shown to admins, staff and (filtered) bidders.
 *
 * Legacy data: existing tracking rows keep their text and are marked
 * internal (not shown to bidders), because older staff entries embed internal
 * remarks. A project is marked opened only when its bids already carry a
 * recorded examination/decision timestamp, i.e. opening demonstrably happened;
 * the earliest such timestamp is used and bids_opened_by stays null.
 */
return new class extends Migration
{
    private const OPENING_EVIDENCE = [
        'documents_validated_at',
        'eligibility_reviewed_at',
        'approved_at',
        'bac_evaluation_at',
        'evaluated_at',
        'disqualified_at',
        'awarded_at',
    ];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('bids_opened_at')->nullable()->after('status');
            $table->foreignId('bids_opened_by')->nullable()->after('bids_opened_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('bid_trackings', function (Blueprint $table) {
            $table->string('stage', 40)->nullable()->after('status_type');
            $table->string('decision', 40)->nullable()->after('stage');
            $table->text('reason')->nullable()->after('decision');
            $table->boolean('visible_to_bidder')->default(false)->after('reason');
            $table->json('details')->nullable()->after('visible_to_bidder');
        });

        $this->backfillBidOpening();
    }

    public function down(): void
    {
        Schema::table('bid_trackings', function (Blueprint $table) {
            $table->dropColumn(['stage', 'decision', 'reason', 'visible_to_bidder', 'details']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bids_opened_by');
            $table->dropColumn('bids_opened_at');
        });
    }

    private function backfillBidOpening(): void
    {
        $bids = DB::table('bids')->get(array_merge(['project_id'], self::OPENING_EVIDENCE));
        $awards = DB::table('awards')->get(['project_id', 'created_at']);

        $earliest = [];
        $consider = function ($projectId, $value) use (&$earliest) {
            if ($value === null) {
                return;
            }
            if (! isset($earliest[$projectId]) || $value < $earliest[$projectId]) {
                $earliest[$projectId] = $value;
            }
        };

        foreach ($bids as $bid) {
            foreach (self::OPENING_EVIDENCE as $column) {
                $consider($bid->project_id, $bid->{$column});
            }
        }

        foreach ($awards as $award) {
            $consider($award->project_id, $award->created_at);
        }

        foreach ($earliest as $projectId => $openedAt) {
            DB::table('projects')->where('id', $projectId)->whereNull('bids_opened_at')->update(['bids_opened_at' => $openedAt]);
        }
    }
};
