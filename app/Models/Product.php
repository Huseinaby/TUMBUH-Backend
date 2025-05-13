<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ProductCategories;

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
}
