<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class Game extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'time_control_config' => 'array',
            'black_clock' => 'array',
            'white_clock' => 'array',
            'dead_stones' => 'array',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_move_at' => 'datetime',
        ];
    }

    public function blackPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'black_player_id');
    }

    public function whitePlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'white_player_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(Move::class)->orderBy('move_number');
    }

    public function scopeWithUnreadCount(Builder $query, int $userId): Builder
    {
        $sub = DB::table('messages as m')
            ->leftJoin('game_read_states as r', function ($join) use ($userId): void {
                $join->on('r.game_id', '=', 'm.game_id')->where('r.user_id', '=', $userId);
            })
            ->whereColumn('m.game_id', 'games.id')
            ->where('m.user_id', '!=', $userId)
            ->where(function ($q): void {
                $q->whereNull('r.last_read_message_id')
                    ->orWhereColumn('m.id', '>', 'r.last_read_message_id');
            })
            ->selectRaw('count(*)');

        return $query->addSelect(['unread_count' => $sub]);
    }
}
