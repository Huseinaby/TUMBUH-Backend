<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'group_id',
        'title',
        'content',
        'image',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'post_likes')->withTimestamps();
    }
}
