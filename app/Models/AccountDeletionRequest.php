<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reason',
        'additional_feedback',
        'requested_at',
        'scheduled_archive_at',
        'cancelled_at',
        'archived_at',
        'status',
        'hold_reason',
        'previous_status',
        'previous_account_status',
        'reminder_sent_at',
        'restored_at',
        'restored_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'scheduled_archive_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'archived_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
