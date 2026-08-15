<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendee extends Model
{
    use HasFactory;

    public const SOURCE_LOCAL = 'local';

    public const SOURCE_SERVER = 'server';

    protected $fillable = [
        'site_id', 'wp_attendee_id', 'wp_event_id', 'wp_ticket_id', 'ticket_name',
        'provider', 'holder_name', 'holder_email', 'security_code', 'order_status',
        'checked_in', 'checked_in_at', 'checked_in_by', 'checked_in_source',
        'remote_updated_at',
    ];

    protected function casts(): array
    {
        return ['checked_in' => 'boolean'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isEligibleForCheckin(): bool
    {
        return $this->order_status === 'completed';
    }

    /** Checked in on this device but not yet confirmed by the server. */
    public function hasPendingLocalCheckinState(): bool
    {
        return $this->checked_in_source === self::SOURCE_LOCAL;
    }
}
