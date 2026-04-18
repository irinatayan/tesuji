<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Webhook\TelegramWebhookController;
use Illuminate\Http\Request;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', fn (Request $r) => $r->user());

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::get('users/{user}/games', [UserController::class, 'games']);
    Route::get('profile', [UserController::class, 'profile']);

    Route::post('invitations', [InvitationController::class, 'store']);
    Route::get('invitations/incoming', [InvitationController::class, 'incoming']);
    Route::get('invitations/outgoing', [InvitationController::class, 'outgoing']);
    Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept']);
    Route::post('invitations/{invitation}/decline', [InvitationController::class, 'decline']);

    Route::get('games', [GameController::class, 'index']);
    Route::get('games/live', [GameController::class, 'live']);
    Route::post('games', [GameController::class, 'store']);
    Route::post('games/vs-bot', [GameController::class, 'createVsBot']);
    Route::get('games/{game}', [GameController::class, 'show']);
    Route::get('games/{game}/sgf', [GameController::class, 'sgf']);
    Route::post('games/{game}/moves', [GameController::class, 'move']);
    Route::post('games/{game}/pass', [GameController::class, 'pass']);
    Route::post('games/{game}/resign', [GameController::class, 'resign']);
    Route::post('games/{game}/dead-stones', [GameController::class, 'markDead']);
    Route::post('games/{game}/dead-stones/confirm', [GameController::class, 'confirmDead']);
    Route::post('games/{game}/dead-stones/dispute', [GameController::class, 'disputeDead']);

    Route::get('games/{game}/messages', [MessageController::class, 'index']);
    Route::post('games/{game}/messages', [MessageController::class, 'store']);
    Route::post('games/{game}/messages/read', [MessageController::class, 'markRead']);

    Route::post('telegram/pair', [TelegramController::class, 'pair']);
    Route::delete('telegram/unlink', [TelegramController::class, 'unlink']);
});

Route::post('webhooks/telegram', [TelegramWebhookController::class, 'handle']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('google', [AuthController::class, 'googleRedirect']);
    Route::get('google/callback', [AuthController::class, 'googleCallback']);
});
