<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCostDetail extends Model
{
    protected $fillable = [
        'origin_id',
        'destination_id',
        'weight',
        'code',
        'name',
        'service',
        'description',
        'cost',
        'etd'
    ];


}
