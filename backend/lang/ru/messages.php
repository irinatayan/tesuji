<?php

return [
    'not_participant' => 'Вы не являетесь участником этой игры.',

    'invitation_duplicate' => 'У вас уже есть активное приглашение для этого игрока.',
    'invitation_active_game' => 'У вас уже есть активная realtime-игра. Сначала завершите её.',
    'invitation_not_for_you' => 'Это приглашение не адресовано вам.',
    'invitation_not_pending' => 'Это приглашение уже не ожидает ответа.',
    'invitation_accept_self_active' => 'У вас уже есть активная realtime-игра.',
    'invitation_accept_opponent_active' => 'У вашего соперника уже есть активная realtime-игра.',
    'invitation_declined' => 'Приглашение отклонено.',

    'illegal_occupied' => 'Клетка уже занята.',
    'illegal_suicide' => 'Суицидные ходы запрещены.',
    'illegal_ko' => 'Ход нарушает правило ко.',
    'illegal_wrong_turn' => 'Сейчас ход :expected, получено :got.',
    'illegal_not_in_progress' => 'Игра не в процессе.',

    'mail_your_turn_subject' => 'Ваш ход — Tesuji',
    'mail_your_turn_greeting' => 'Привет, :name!',
    'mail_your_turn_body' => 'Ваш ход в корреспондентской игре :size×:size на Tesuji.',
    'mail_your_turn_black' => 'Вы играете чёрными.',
    'mail_your_turn_white' => 'Вы играете белыми.',
    'mail_open' => 'Открыть Tesuji',

    'mail_finished_subject' => 'Игра завершена — Tesuji',
    'mail_finished_body' => 'Ваша корреспондентская игра :size×:size на Tesuji завершилась.',
    'mail_finished_result' => 'Результат: :result',

    'mail_timeout_subject' => 'Время вышло — Tesuji',
    'mail_timeout_body' => 'Ваша корреспондентская игра :size×:size на Tesuji завершилась из-за тайм-аута.',
];
