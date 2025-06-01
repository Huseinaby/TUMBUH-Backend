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

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id', 'id');
    }

    public function kecamatans()
    {
        return $this->hasMany(kecamatan::class, 'kabupaten_id', 'id');
    }
}
