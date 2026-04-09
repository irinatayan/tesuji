<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('black_player_id')->constrained('users');
            $table->foreignId('white_player_id')->constrained('users');
            $table->enum('mode', ['realtime', 'correspondence']);
            $table->string('ruleset', 32);
            $table->smallInteger('board_size');
            $table->enum('status', ['waiting', 'playing', 'scoring', 'finished', 'aborted'])->default('waiting');
            $table->enum('current_turn', ['black', 'white'])->nullable();
            $table->enum('time_control_type', ['absolute', 'byoyomi', 'correspondence']);
            $table->jsonb('time_control_config');
            $table->jsonb('black_clock')->nullable();
            $table->jsonb('white_clock')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->string('result', 16)->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users');
            $table->jsonb('dead_stones')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('last_move_at')->nullable();
            $table->timestamps();
        });

        DB::statement(
            "CREATE INDEX games_expires_at_idx ON games(expires_at)
             WHERE status = 'playing' AND expires_at IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS games_expires_at_idx');
        Schema::dropIfExists('games');
    }
};
