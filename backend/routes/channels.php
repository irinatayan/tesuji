<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('game.{gameId}', function (User $user, int $gameId): ?array {
    if (! Game::where('id', $gameId)->exists()) {
        return null;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
