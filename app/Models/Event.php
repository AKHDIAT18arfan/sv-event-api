<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    /** MAHASISWA = khusus mahasiswa, SEMUA = terbuka untuk mahasiswa dan umum. */
    public const TARGETS = ['MAHASISWA', 'SEMUA'];

    /** Tidak ada Draft. "Selesai" sama dengan "Ditutup". */
    public const STATUSES = ['dibuka', 'ditutup'];

    protected $fillable = [
        'name',
        'description',
        'venue',
        'poster_path',
        'target_peserta',
        'organizer_name',
        'registration_open_at',
        'registration_close_at',
        'status',
        'last_ticket_no',
        'created_by',
        'deleted_reason',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** Akun panitia pemilik event. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Aturan bisnis 4: klaim hanya saat status Dibuka dan di dalam jadwal pendaftaran. */
    public function isRegistrationOpen(): bool
    {
        return $this->status === 'dibuka'
            && now()->between($this->registration_open_at, $this->registration_close_at);
    }
}
