<!DOCTYPE html>
<html>
<body>
<p>{{ __('messages.mail_your_turn_greeting', ['name' => $recipient->name]) }}</p>

<p>{{ __('messages.mail_timeout_body', ['size' => $game->board_size]) }}</p>

<p>{{ __('messages.mail_finished_result', ['result' => $game->result]) }}</p>

<p><a href="{{ env('FRONTEND_URL') }}">{{ __('messages.mail_open') }}</a></p>
</body>
</html>
