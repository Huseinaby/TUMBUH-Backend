<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategories extends Model
{
    //
    protected $guarded = ['id'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
