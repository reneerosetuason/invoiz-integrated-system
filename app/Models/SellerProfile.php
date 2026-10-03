<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellerProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'logo_path',
        'banner_path',
        'description',
        'contact_email',
        'contact_phone',
        'address',
        'business_info',
        'operating_hours',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
