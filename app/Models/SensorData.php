<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorData extends Model
{
    protected $fillable = [
        'device_id',
        'time_slot',
        'temperature',
        'humidity',
        'soil_moisture',
        'pump_status',
        'status',
        'date',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
