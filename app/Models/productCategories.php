<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class productCategories extends Model
{
    //
    protected $guarded = ['id'];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
