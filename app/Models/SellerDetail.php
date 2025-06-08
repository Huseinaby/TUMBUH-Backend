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
        'origin_id',
        'store_phone',
        'store_logo',
        'store_banner',
        'bank_name',
        'bank_account_number',
        'bank_account_holder_name',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
