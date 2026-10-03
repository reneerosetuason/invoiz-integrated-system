<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationsLog extends Model
{
    use HasFactory;

    protected $table = 'notifications_log';

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'body',
        'email',
        'sent',
    ];

    protected $casts = [
        'sent' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
