<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'created_by',
    ];

    public function members()
    {
        return $this->hasMany(GroupMember::class);
    }
}
