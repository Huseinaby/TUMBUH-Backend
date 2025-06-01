<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $fillable = [
        'id',
        'name',
        'code',
    ];

    public $incrementing = false;

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function kabupatens()
    {
        return $this->hasMany(kabupaten::class, 'province_id', 'id');
    }
}
