<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinOperation extends Model
{
    use HasFactory;

    public const ACTION_CHECKIN = 'checkin';

    public const ACTION_UNCHECKIN = 'uncheckin';

    protected $fillable = [
        'op_id', 'site_id', 'wp_attendee_id', 'wp_event_id', 'action',
        'occurred_at', 'synced_at', 'result_status', 'attempts',
    ];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('synced_at');
    }
}
