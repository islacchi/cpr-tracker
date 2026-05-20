<?php

namespace App\Console\Commands;

use App\Models\CprRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RefreshCprStatus
 *
 * Recalculates `days_remaining` and `status` for every CprRecord that has an
 * expiry_date. Run this daily via the scheduler so records never go stale
 * between scans.
 */
class RefreshCprStatus extends Command
{
    protected $signature   = 'cpr:refresh-status';
    protected $description = 'Refresh days_remaining and status for all CPR records with an expiry date.';

    public function handle(): int
    {
        $rows = CprRecord::whereNotNull('expiry_date')->get();

        if ($rows->isEmpty()) {
            $this->info('No records to refresh.');
            return self::SUCCESS;
        }

        $updates = $rows->map(function (CprRecord $record) {
            $computed = CprRecord::resolveStatus($record->expiry_date?->toDateString());

            return [
                'id'             => $record->id,
                'days_remaining' => $computed['days_remaining'],
                'status'         => $computed['status'],
                'updated_at'     => now(),
            ];
        });

        // Batch update in chunks to avoid memory pressure on large tables.
        foreach ($updates->chunk(200) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('cpr_records')
                    ->where('id', $row['id'])
                    ->update([
                        'days_remaining' => $row['days_remaining'],
                        'status'         => $row['status'],
                        'updated_at'     => $row['updated_at'],
                    ]);
            }
        }

        $this->info("Refreshed {$rows->count()} CPR records.");
        return self::SUCCESS;
    }
}