<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class kabupaten extends Model
{
    protected $fillable = [
        'id',
        'name',
        'province_id',
    ];

    public $incrementing = false;
}
