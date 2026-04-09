<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', fn (Request $r) => $r->user());

    Route::post('games', [GameController::class, 'store']);
    Route::get('games/{game}', [GameController::class, 'show']);
    Route::post('games/{game}/moves', [GameController::class, 'move']);
    Route::post('games/{game}/pass', [GameController::class, 'pass']);
    Route::post('games/{game}/resign', [GameController::class, 'resign']);
    Route::post('games/{game}/dead-stones', [GameController::class, 'markDead']);
    Route::post('games/{game}/dead-stones/confirm', [GameController::class, 'confirmDead']);
    Route::post('games/{game}/dead-stones/dispute', [GameController::class, 'disputeDead']);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
