<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'bank_name',
        'bank_account_number',
        'bank_account_holder_name',
        'nomor_induk_kependudukan',
        'foto_ktp',
        'saldo',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
