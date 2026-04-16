<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVsBotRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'board_size' => ['required', 'integer', 'in:9,13,19'],
            'color' => ['required', 'string', 'in:black,white,random'],
            'mode' => ['sometimes', 'string', 'in:realtime,correspondence'],
            'time_control_type' => ['sometimes', 'string', 'in:absolute,byoyomi,correspondence'],
            'time_control_config' => ['sometimes', 'array'],
        ];
    }
}
