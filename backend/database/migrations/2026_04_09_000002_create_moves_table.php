<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->smallInteger('move_number');
            $table->enum('color', ['black', 'white']);
            $table->enum('type', ['play', 'pass', 'resign']);
            $table->smallInteger('x')->nullable();
            $table->smallInteger('y')->nullable();
            $table->jsonb('captures')->default('[]');
            $table->char('position_hash', 64);
            $table->binary('board_state');
            $table->timestampTz('played_at');

            $table->unique(['game_id', 'move_number']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moves');
    }
};
