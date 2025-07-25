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

}
