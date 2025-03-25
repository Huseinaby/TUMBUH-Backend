<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDetail extends Model
{
    //

    protected $table = 'seller_details';

    protected $fillable = [
        'user_id',
        'store_name',
        'store_description',
        'store_address',
        'store_phone',
        'store_logo',
        'store_banner',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
