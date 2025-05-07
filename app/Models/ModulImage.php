<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModulImage extends Model
{
    //
    protected $guarded = ['id'];

    protected $append = ['image_url'];

    public function modul()
    {
        return $this->belongsTo(modul::class);
    }
}
