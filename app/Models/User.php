<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'role',
        'password',
        'email_verified_at',
        'gauth_id',
        'gauth_type',
        'photo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function modul()
    {
        return $this->hasMany(modul::class);
    }

    public function product()
    {
        return $this->hasMany(Product::class);
    }

    public function transaction()
    {
        return $this->hasMany(transaction::class);
    }

    public function cartItem()
    {
        return $this->hasMany(cartItem::class);
    }

    public function favoriteModul()
    {
        return $this->belongsToMany(modul::class, 'favorite_moduls')->withTimestamps();
    }

    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    public function quizProgress()
    {
        return $this->hasMany(QuizProgress::class);
    }
}
