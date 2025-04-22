<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $guarded = ['id'];

    public function modul(){
        return $this->belongsTo(Modul::class);
    }
}
