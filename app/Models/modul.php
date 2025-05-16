<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class modul extends Model
{
    //
    public $guarded = ['id'];

    protected $casts = [
        'image' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function video()
    {
        return $this->hasMany(Video::class);
    }

    public function article()
    {
        return $this->hasMany(Article::class);
    }

    public function quiz()
    {
        return $this->hasMany(Quiz::class);
    }

    public function modulImage()
    {
        return $this->hasMany(ModulImage::class);
    }

    protected $appends = ['images'];
    protected $hidden = ['modul_image'];

    public function getImagesAttribute()
    {
        return $this->modulImage;
    }

    public function favoriteModul(){
        return $this->belongsToMany(User::class, 'favorite_moduls')
            ->withTimestamps();
    }
}
