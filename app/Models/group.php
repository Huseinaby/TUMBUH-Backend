<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class group extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'created_by',
    ];
}
