<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderApplicationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_application_id',
        'previous_status',
        'new_status',
        'changed_by',
        'reason',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(RiderApplication::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
