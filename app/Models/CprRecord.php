<?php

namespace App\Models;

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

    public function computeStatus(int $warningDays = 90): void
    {
        if (!$this->expiry_date) {
            $this->days_remaining = null;
            $this->status = 'Unknown';
            return;
        }

        $this->days_remaining = (int) now()->startOfDay()
            ->diffInDays($this->expiry_date, false);

        $this->status = match(true) {
            $this->days_remaining < 0             => 'Expired',
            $this->days_remaining <= $warningDays => 'Expiring Soon',
            default                               => 'Valid',
        };
    }
}