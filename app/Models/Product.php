<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use app\Models\User;
use app\Models\productCategories;

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
        return $this->belongsTo(productCategories::class, 'category_id');
    }
}
