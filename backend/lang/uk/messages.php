<?php

return [
    'not_participant' => 'Ви не є учасником цієї гри.',

    'invitation_duplicate' => 'Ви вже маєте активне запрошення для цього гравця.',
    'invitation_active_game' => 'У вас вже є активна realtime-гра. Спочатку завершіть її.',
    'invitation_not_for_you' => 'Це запрошення не адресоване вам.',
    'invitation_not_pending' => 'Це запрошення вже не очікує відповіді.',
    'invitation_accept_self_active' => 'У вас вже є активна realtime-гра.',
    'invitation_accept_opponent_active' => 'У вашого суперника вже є активна realtime-гра.',
    'invitation_declined' => 'Запрошення відхилено.',

    'illegal_occupied' => 'Клітинка вже зайнята.',
    'illegal_suicide' => 'Суїцидні ходи заборонені.',
    'illegal_ko' => 'Хід порушує правило ко.',
    'illegal_wrong_turn' => 'Зараз хід :expected, отримано :got.',
    'illegal_not_in_progress' => 'Гра не в процесі.',

    'mail_your_turn_subject' => 'Ваш хід — Tesuji',
    'mail_your_turn_greeting' => 'Привіт, :name!',
    'mail_your_turn_body' => 'Ваш хід у кореспондентській грі :size×:size на Tesuji.',
    'mail_your_turn_black' => 'Ви граєте чорними.',
    'mail_your_turn_white' => 'Ви граєте білими.',
    'mail_open' => 'Відкрити Tesuji',

    'mail_finished_subject' => 'Гра завершена — Tesuji',
    'mail_finished_body' => 'Ваша кореспондентська гра :size×:size на Tesuji завершилась.',
    'mail_finished_result' => 'Результат: :result',

    'mail_timeout_subject' => 'Час вийшов — Tesuji',
    'mail_timeout_body' => 'Ваша кореспондентська гра :size×:size на Tesuji завершилась через тайм-аут.',
];
