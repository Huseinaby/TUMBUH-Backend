<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'user_id',
        'api_key',
        'serial_number',
        'device_name',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
