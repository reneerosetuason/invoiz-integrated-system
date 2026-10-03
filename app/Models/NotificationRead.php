<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRead extends Model
{
    protected $fillable = ['user_id', 'notification_key'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
