<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->enum('mode', ['realtime', 'correspondence']);
            $table->smallInteger('board_size');
            $table->string('ruleset', 32);
            $table->enum('time_control_type', ['absolute', 'byoyomi', 'correspondence']);
            $table->jsonb('time_control_config');
            $table->enum('proposed_color', ['black', 'white', 'random'])->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired', 'cancelled'])->default('pending');
            $table->foreignId('game_id')->nullable()->constrained('games');
            $table->timestampTz('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_invitations');
    }
};
