<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class modul extends Model
{
    //
    public $guarded = ['id'];

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
}
