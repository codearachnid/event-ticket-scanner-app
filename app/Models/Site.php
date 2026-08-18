<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'base_url', 'username', 'last_verified_at'];

    protected function casts(): array
    {
        return ['last_verified_at' => 'datetime'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function checkinOperations(): HasMany
    {
        return $this->hasMany(CheckinOperation::class);
    }

    /** SecureStorage key holding this site's application password. */
    public function credentialKey(): string
    {
        return "site_{$this->id}_password";
    }

    /** Root of the companion plugin's REST namespace on this site. */
    public function apiBase(): string
    {
        return rtrim($this->base_url, '/').'/wp-json/tec-scanner/v1';
    }
}
