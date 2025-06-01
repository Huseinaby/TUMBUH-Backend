<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class kecamatan extends Model
{
    protected $fillable = [
        'id',
        'name',
        'kabupaten_id',
    ];

    public $incrementing = false;

    public function kabupaten()
    {
        return $this->belongsTo(kabupaten::class, 'kabupaten_id');
    }
}
