<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id', 'wp_event_id', 'title', 'starts_at', 'ends_at',
        'timezone', 'venue', 'last_synced_at', 'sync_cursor',
    ];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class, 'wp_event_id', 'wp_event_id')
            ->where('attendees.site_id', $this->site_id);
    }
}
