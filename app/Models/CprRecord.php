<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CprRecord extends Model
{
    protected $fillable = [
        'filename',
        'normalized_filename',
        'folder_path',
        'registration_number',
        'brand_name',
        'generic_name',
        'expiry_date',
        'days_remaining',
        'status',
    ];

    protected $casts = [
        'expiry_date'    => 'date',
        'days_remaining' => 'integer',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'Valid');
    }

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->where('status', 'Expiring Soon');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'Expired');
    }

    public function scopeErrors(Builder $query): Builder
    {
        return $query->whereIn('status', ['Parse Error', 'Unknown']);
    }

    // ── Status calculation ───────────────────────────────────────────────────

    /**
     * Static — returns a plain array.
     * Used by CprScanService, CprController::update(), and RefreshCprStatus
     * so none of them duplicate the match() logic.
     *
     * Accepts a date string (from parsed PDF data or form input).
     * The $warningDays threshold defaults to 90 but is overridable.
     *
     * @return array{days_remaining: int|null, status: string}
     */
    public static function resolveStatus(?string $expiryDate, int $warningDays = 90, ?string $brandName = null): array
    {
        if (!$expiryDate) {
            return ['days_remaining' => null, 'status' => 'Unknown'];
        }
        if (empty($brandName)) {
            $expiry        = Carbon::parse($expiryDate);
            $daysRemaining = (int) now()->startOfDay()->diffInDays($expiry, false);
            return ['days_remaining' => $daysRemaining, 'status' => 'Unknown'];
        }

        $expiry        = Carbon::parse($expiryDate);
        $daysRemaining = (int) now()->startOfDay()->diffInDays($expiry, false);

        return [
            'days_remaining' => $daysRemaining,
            'status'         => match (true) {
                $daysRemaining < 0             => 'Expired',
                $daysRemaining <= $warningDays => 'Expiring Soon',
                default                        => 'Valid',
            },
        ];
    }

    /**
     * Instance — mutates the model in place.
     * Delegates to resolveStatus() so the threshold logic stays in one place.
     */
    public function computeStatus(int $warningDays = 90): void
    {
        $computed = static::resolveStatus(
            $this->expiry_date?->toDateString(),
            $warningDays,
            $this->brand_name
        );
        $this->days_remaining = $computed['days_remaining'];
        $this->status         = $computed['status'];
    }

    // ── Filename helpers ─────────────────────────────────────────────────────

    /**
     * Build the human-readable display name stored alongside each record.
     * Used by CprScanService (on parse) and CprController::update() (on edit).
     */
    public static function buildNormalizedFilename(
        ?string $genericName,
        ?string $brandName,
        ?string $expiryDate
    ): string {
        $generic = $genericName ? strtolower(trim($genericName)) : 'unknown';
        $brand   = $brandName   ? strtoupper(trim($brandName))   : 'UNKNOWN';
        $expiry  = $expiryDate
            ? Carbon::parse($expiryDate)->format('M Y')
            : 'No Expiry';

        return ucwords($generic) . " - {$brand} - {$expiry}";
    }
}