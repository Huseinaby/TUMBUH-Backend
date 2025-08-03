<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;

class User extends Authenticatable implements HasName, FilamentUser
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
        'storeName',
        'password',
        'email_verified_at',
        'gauth_id',
        'gauth_type',
        'photo',
        'fcm_token',
        'coins',
        'scheduled_deletion_at',
    ];

    protected $dates = [
        'scheduled_deletion_at',
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

    public function sellerDetail()
    {
        return $this->hasOne(SellerDetail::class);
    }

    public function userAddress()
    {
        return $this->hasMany(UserAddress::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function getFilamentName(): string
    {
        return $this->username;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function walletHistory()
    {
        return $this->hasMany(WalletHistory::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function postComments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function groupMember()
    {
        return $this->hasMany(GroupMember::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function likedPosts()
    {
        return $this->belongsToMany(Post::class, 'post_likes')
            ->withTimestamps();
    }
}
