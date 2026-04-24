<?php

declare(strict_types=1);

use App\Game\Rules\ChineseRuleset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->smallInteger('handicap')->default(0)->after('board_size');
            $table->jsonb('handicap_stones')->default('[]')->after('handicap');
            $table->string('handicap_placement', 8)->default('fixed')->after('handicap_stones');
            $table->decimal('komi', 3, 1)->nullable()->after('handicap_placement');
        });

        $rules = new ChineseRuleset;
        DB::table('games')->orderBy('id')->chunkById(500, function ($games) use ($rules): void {
            foreach ($games as $game) {
                DB::table('games')
                    ->where('id', $game->id)
                    ->update(['komi' => $rules->komi((int) $game->board_size)]);
            }
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->decimal('komi', 3, 1)->nullable(false)->change();
        });

        Schema::table('game_invitations', function (Blueprint $table): void {
            $table->smallInteger('handicap')->default(0)->after('board_size');
            $table->string('handicap_placement', 8)->default('fixed')->after('handicap');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['handicap', 'handicap_stones', 'handicap_placement', 'komi']);
        });

        Schema::table('game_invitations', function (Blueprint $table): void {
            $table->dropColumn(['handicap', 'handicap_placement']);
        });
    }
};
