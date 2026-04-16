<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class BotUsersSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'bot+gnugo@tesuji.local'],
            [
                'name' => 'GnuGo',
                'password' => null,
                'is_bot' => true,
            ],
        );
    }
}
