<!DOCTYPE html>
<html>
<body>
<p>{{ __('messages.mail_your_turn_greeting', ['name' => $recipient->name]) }}</p>

<p>{{ __('messages.mail_your_turn_body', ['size' => $game->board_size]) }}</p>

<p>
    @if ($game->black_player_id === $recipient->id)
        {{ __('messages.mail_your_turn_black') }}
    @else
        {{ __('messages.mail_your_turn_white') }}
    @endif
</p>

<p><a href="{{ env('FRONTEND_URL') }}">{{ __('messages.mail_open') }}</a></p>
</body>
</html>
