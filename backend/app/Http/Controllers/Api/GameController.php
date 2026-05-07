<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\Game\DeadStonesMarked;
use App\Events\Game\GameFinished;
use App\Game\Exceptions\IllegalMoveException;
use App\Game\Handicap;
use App\Game\Move as DomainMove;
use App\Game\Persistence\GameMapper;
use App\Game\Position;
use App\Game\Rules\ChineseRuleset;
use App\Game\Stone;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGameRequest;
use App\Http\Requests\CreateVsBotRequest;
use App\Http\Requests\MarkDeadStonesRequest;
use App\Http\Requests\PlayMoveRequest;
use App\Http\Resources\GameResource;
use App\Jobs\BotMoveJob;
use App\Models\Game;
use App\Models\User;
use App\Notifications\GameFinishedNotification;
use App\Services\GameService;
use App\Services\SgfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GameController extends Controller
{
    public function __construct(
        private readonly GameService $gameService,
        private readonly GameMapper $mapper,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $games = Game::where(function ($query) use ($userId): void {
            $query->where('black_player_id', $userId)
                ->orWhere('white_player_id', $userId);
        })
            ->where('status', 'playing')
            ->with(['blackPlayer', 'whitePlayer', 'moves'])
            ->withUnreadCount($userId)
            ->latest()
            ->get();

        return GameResource::collection($games);
    }

    public function live(): AnonymousResourceCollection
    {
        $games = Game::where('status', 'playing')
            ->with(['blackPlayer', 'whitePlayer'])
            ->latest('started_at')
            ->limit(50)
            ->get();

        return GameResource::collection($games);
    }

    public function store(CreateGameRequest $request): JsonResponse
    {
        $color = $request->color === 'random'
            ? (rand(0, 1) === 0 ? 'black' : 'white')
            : $request->color;

        $opponent = User::findOrFail($request->opponent_id);

        [$blackId, $whiteId] = $color === 'black'
            ? [$request->user()->id, $opponent->id]
            : [$opponent->id, $request->user()->id];

        [$blackClock, $whiteClock, $expiresAt] = $this->initClocks(
            $request->time_control_type,
            $request->time_control_config,
        );

        $rules = new ChineseRuleset;

        $game = Game::create([
            'black_player_id' => $blackId,
            'white_player_id' => $whiteId,
            'mode' => $request->mode,
            'ruleset' => 'chinese',
            'board_size' => $request->board_size,
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => $request->time_control_type,
            'time_control_config' => $request->time_control_config,
            'komi' => $rules->komi($request->board_size),
            'started_at' => now(),
            'black_clock' => $blackClock,
            'white_clock' => $whiteClock,
            'expires_at' => $expiresAt,
        ]);

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return (new GameResource($game))->response()->setStatusCode(201);
    }

    public function createVsBot(CreateVsBotRequest $request): JsonResponse
    {
        $bot = User::where('is_bot', true)->firstOrFail();

        $color = $request->color === 'random'
            ? (rand(0, 1) === 0 ? 'black' : 'white')
            : $request->color;

        [$blackId, $whiteId] = $color === 'black'
            ? [$request->user()->id, $bot->id]
            : [$bot->id, $request->user()->id];

        $timeControlType = $request->input('time_control_type', 'absolute');
        $timeControlConfig = $request->input('time_control_config', ['main_time' => 600]);
        $mode = $request->input('mode', 'realtime');

        [$blackClock, $whiteClock, $expiresAt] = $this->initClocks($timeControlType, $timeControlConfig);

        $rules = new ChineseRuleset;
        $handicap = (int) $request->input('handicap', 0);
        $handicapStones = Handicap::fixedPositions($request->board_size, $handicap);
        $handicapStonesJson = array_map(
            fn (Position $p) => ['x' => $p->x, 'y' => $p->y],
            $handicapStones
        );
        $currentTurn = $handicap >= 2 ? 'white' : 'black';

        $game = Game::create([
            'black_player_id' => $blackId,
            'white_player_id' => $whiteId,
            'mode' => $mode,
            'ruleset' => 'chinese',
            'board_size' => $request->board_size,
            'status' => 'playing',
            'current_turn' => $currentTurn,
            'time_control_type' => $timeControlType,
            'time_control_config' => $timeControlConfig,
            'handicap' => $handicap,
            'handicap_stones' => $handicapStonesJson,
            'handicap_placement' => $request->input('handicap_placement', 'fixed'),
            'komi' => $rules->komiWithHandicap($request->board_size, $handicap),
            'started_at' => now(),
            'black_clock' => $blackClock,
            'white_clock' => $whiteClock,
            'expires_at' => $expiresAt,
        ]);

        // Bot moves first if it holds the color whose turn it is now.
        $botPlayerId = $currentTurn === 'black' ? $blackId : $whiteId;
        if ($botPlayerId === $bot->id) {
            BotMoveJob::dispatch($game->id);
        }

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return (new GameResource($game))->response()->setStatusCode(201);
    }

    public function sgf(Game $game, SgfExporter $exporter): Response
    {
        if ($game->status !== 'finished') {
            abort(403, 'Game is not finished yet.');
        }

        $sgf = $exporter->export($game);
        $filename = "game-{$game->id}.sgf";

        return response($sgf, 200, [
            'Content-Type' => 'application/x-go-sgf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function show(Request $request, Game $game): GameResource
    {
        $game = Game::withUnreadCount($request->user()->id)->findOrFail($game->id);
        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function move(PlayMoveRequest $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => __('messages.not_participant')], 403);
        }

        $move = DomainMove::play($stone, new Position($request->x, $request->y));

        try {
            $game = $this->gameService->applyMove($game, $move);
        } catch (IllegalMoveException $e) {
            return response()->json(['message' => __('messages.illegal_'.$e->getMessage(), $e->params)], 422);
        }

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function pass(Request $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => __('messages.not_participant')], 403);
        }

        try {
            $game = $this->gameService->applyMove($game, DomainMove::pass($stone));
        } catch (IllegalMoveException $e) {
            return response()->json(['message' => __('messages.illegal_'.$e->getMessage(), $e->params)], 422);
        }

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function resign(Request $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => __('messages.not_participant')], 403);
        }

        try {
            $game = $this->gameService->applyMove($game, DomainMove::resign($stone));
        } catch (IllegalMoveException $e) {
            return response()->json(['message' => __('messages.illegal_'.$e->getMessage(), $e->params)], 422);
        }

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function markDead(MarkDeadStonesRequest $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => __('messages.not_participant')], 403);
        }

        $positions = array_map(
            fn (array $p) => new Position($p['x'], $p['y']),
            $request->stones
        );

        try {
            $domainGame = $this->mapper->restore($game);
            $domainGame = $domainGame->markDead($positions, $stone);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $deadStones = array_map(fn (Position $p) => ['x' => $p->x, 'y' => $p->y], $positions);
        $game->update(['dead_stones' => $deadStones, 'status' => 'scoring']);

        event(new DeadStonesMarked(
            gameId: $game->id,
            by: strtolower($stone->name),
            stones: $deadStones,
        ));

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function confirmDead(Request $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => __('messages.not_participant')], 403);
        }

        try {
            $domainGame = $this->mapper->restore($game);
            $domainGame = $domainGame->confirmDead($stone);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $result = $domainGame->score?->result();

        $game->update([
            'status' => 'finished',
            'result' => $result,
            'finished_at' => now(),
        ]);

        event(new GameFinished(
            gameId: $game->id,
            result: $result ?? '',
            score: $domainGame->score !== null ? [
                'black' => $domainGame->score->black,
                'white' => $domainGame->score->white,
            ] : null,
        ));

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        $game->blackPlayer->notify(new GameFinishedNotification($game));
        $game->whitePlayer->notify(new GameFinishedNotification($game));

        return new GameResource($game);
    }

    public function disputeDead(Request $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => __('messages.not_participant')], 403);
        }

        try {
            $domainGame = $this->mapper->restore($game);
            $domainGame->disputeDead($stone);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $game->update([
            'status' => 'playing',
            'current_turn' => strtolower($stone->name),
            'dead_stones' => null,
        ]);

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    private function resolvePlayerStone(Request $request, Game $game): ?Stone
    {
        $userId = $request->user()->id;

        if ($userId === $game->black_player_id) {
            return Stone::Black;
        }

        if ($userId === $game->white_player_id) {
            return Stone::White;
        }

        return null;
    }

    /** @return array{?array, ?array, ?\Illuminate\Support\Carbon} */
    private function initClocks(string $type, array $config): array
    {
        if ($type === 'absolute') {
            $mainTimeMs = ($config['main_time'] ?? 600) * 1000;

            return [
                ['remaining_ms' => $mainTimeMs],
                ['remaining_ms' => $mainTimeMs],
                now()->addMilliseconds($mainTimeMs),
            ];
        }

        return [
            null,
            null,
            now()->addDays($config['days_per_move'] ?? 3),
        ];
    }
}
