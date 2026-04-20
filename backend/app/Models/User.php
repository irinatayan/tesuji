<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\Channels\TelegramChannel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'provider', 'provider_id', 'is_bot', 'telegram_chat_id', 'notification_preferences'])]
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
            'is_bot' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /** @return list<string> */
    public function channelsFor(string $event): array
    {
        $prefs = $this->notification_preferences ?? [];
        $eventPrefs = $prefs[$event] ?? [];

        $channels = [];

        if (($eventPrefs['telegram'] ?? false) && $this->telegram_chat_id) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
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
