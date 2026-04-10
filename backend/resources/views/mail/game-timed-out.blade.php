<!DOCTYPE html>
<html>
<body>
<p>Hi {{ $recipient->name }},</p>

<p>Your {{ $game->board_size }}×{{ $game->board_size }} correspondence game on Tesuji has ended due to timeout.</p>

<p>Result: <strong>{{ $game->result }}</strong></p>

<p><a href="{{ env('FRONTEND_URL') }}">Open Tesuji</a></p>
</body>
</html>
