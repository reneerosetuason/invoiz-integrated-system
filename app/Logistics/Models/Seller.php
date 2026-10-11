<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'line_of_business',
        'id_image',
        'business_permit',
        'approval_status',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
