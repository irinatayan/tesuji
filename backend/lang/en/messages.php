<?php

return [
    'not_participant' => 'You are not a participant of this game.',

    'invitation_duplicate' => 'You already have a pending invitation to this player.',
    'invitation_active_game' => 'You already have an active realtime game. Finish it first.',
    'invitation_not_for_you' => 'This invitation is not addressed to you.',
    'invitation_not_pending' => 'This invitation is no longer pending.',
    'invitation_accept_self_active' => 'You already have an active realtime game.',
    'invitation_accept_opponent_active' => 'Your opponent already has an active realtime game.',
    'invitation_declined' => 'Invitation declined.',

    'illegal_occupied' => 'Cell is already occupied.',
    'illegal_suicide' => 'Suicide moves are not allowed.',
    'illegal_ko' => 'Move violates the ko rule.',
    'illegal_wrong_turn' => "It is :expected's turn, got :got.",
    'illegal_not_in_progress' => 'Game is not in progress.',

    'mail_your_turn_subject' => 'Your turn — Tesuji',
    'mail_your_turn_greeting' => 'Hi :name,',
    'mail_your_turn_body' => "It's your turn in your :size×:size correspondence game on Tesuji.",
    'mail_your_turn_black' => 'You are playing Black.',
    'mail_your_turn_white' => 'You are playing White.',
    'mail_open' => 'Open Tesuji',

    'mail_finished_subject' => 'Game finished — Tesuji',
    'mail_finished_body' => 'Your :size×:size correspondence game on Tesuji has finished.',
    'mail_finished_result' => 'Result: :result',

    'mail_timeout_subject' => 'Game timed out — Tesuji',
    'mail_timeout_body' => 'Your :size×:size correspondence game on Tesuji has ended due to timeout.',

    'tg_opponent_moved' => 'Your turn in a game against :opponent (:size×:size)',
    'tg_invitation' => ':from invites you to a :size×:size game',
    'tg_invitation_details' => ':mode · :time',
    'tg_mode_realtime' => 'Realtime',
    'tg_mode_correspondence' => 'Correspondence',
    'tg_time_absolute' => ':duration per side for the whole game',
    'tg_time_correspondence' => ':days day per move (play at your own pace)|:days days per move (play at your own pace)',
    'tg_new_message' => ':sender sent you a message',
    'tg_new_messages' => ':count new messages from :opponent',
    'tg_game_finished' => 'Your :size×:size game against :opponent has finished. Result: :result',
    'tg_game_timeout' => 'Your :size×:size game against :opponent has ended due to timeout.',
    'tg_timeout_won' => 'You won — :opponent ran out of time',
    'tg_timeout_lost' => 'You lost — your time ran out (game against :opponent)',
];
