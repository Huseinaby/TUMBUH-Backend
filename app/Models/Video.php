<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $guarded = ['id'];

    public function modul(){
        return $this->belongsTo(Modul::class);
    }
}
