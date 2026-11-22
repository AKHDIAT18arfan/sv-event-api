<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    protected $fillable = ['event_id', 'name', 'start_at', 'end_at', 'quota_total', 'quota_remaining'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'quota_total' => 'integer',
            'quota_remaining' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'session_id');
    }

    public function hasStarted(): bool
    {
        return now()->greaterThanOrEqualTo($this->start_at);
    }
}
