<!DOCTYPE html>
<html>
<body>
<p>Hi {{ $recipient->name }},</p>

<p>It's your turn in your {{ $game->board_size }}×{{ $game->board_size }} correspondence game on Tesuji.</p>

<p>
    @if ($game->black_player_id === $recipient->id)
        You are playing Black.
    @else
        You are playing White.
    @endif
</p>

<p><a href="{{ env('FRONTEND_URL') }}">Open Tesuji</a></p>
</body>
</html>
