<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $guarded = ['id'];

    public function modul()
    {
        return $this->belongsTo(Modul::class);
    }

    public function quizProgress()
    {
        return $this->hasMany(QuizProgress::class);
    }
}
