<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ProductCategories;
use App\Models\cartItem;
use App\Models\OrderItem;
use App\Models\Province;
use App\Models\ProductImage;
use App\Models\Review;


class Product extends Model
{
    //
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productCategories()
    {
        return $this->belongsTo(ProductCategories::class, 'product_category_id');
    }

    public function cartItem()
    {
        return $this->hasMany(cartItem::class);
    }

    public function orderItem()
    {
        return $this->hasMany(orderItem::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
