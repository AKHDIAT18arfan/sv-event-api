<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    public const AKTIF = 'aktif';
    public const HADIR = 'hadir';
    public const DIBATALKAN = 'dibatalkan';

    /** Status yang dihitung sebagai tiket aktif (menempati kuota). */
    public const ACTIVE_STATUSES = [self::AKTIF, self::HADIR];

    public const TYPE_MAHASISWA = 'MAHASISWA';
    public const TYPE_UMUM = 'UMUM';

    protected $fillable = [
        'event_id',
        'session_id',
        'user_id',
        'participant_type',
        'nim',
        'study_program_id',
        'ticket_no',
        'status',
        'active_key',
        'nim_key',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'ticket_no' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    /** withTrashed: tiket tetap bisa menampilkan pemiliknya walau akunnya sudah dihapus. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('registrations.status', self::ACTIVE_STATUSES);
    }

    public static function activeKey(int $userId, int $eventId): string
    {
        return "u{$userId}e{$eventId}";
    }

    public static function nimKey(int $eventId, string $nim): string
    {
        return "e{$eventId}n{$nim}";
    }
}
