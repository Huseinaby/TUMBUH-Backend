<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $guarded = ['id'];

    public function getImagePathAttribute($value)
    {
        return 'storage/' . $value;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
