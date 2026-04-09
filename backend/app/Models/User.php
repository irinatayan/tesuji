<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function gamesAsBlack(): HasMany
    {
        return $this->hasMany(Game::class, 'black_player_id');
    }

    public function gamesAsWhite(): HasMany
    {
        return $this->hasMany(Game::class, 'white_player_id');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(GameInvitation::class, 'from_user_id');
    }

    public function receivedInvitations(): HasMany
    {
        return $this->hasMany(GameInvitation::class, 'to_user_id');
    }
}
