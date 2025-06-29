<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['full_image_path'];

    public function getFullImagePathAttribute()
    {
        return 'storage/' . $this->image_path;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
