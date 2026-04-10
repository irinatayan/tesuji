<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$this->user()->id])],
            'board_size' => ['required', 'integer', 'in:9,13,19'],
            'mode' => ['required', 'string', 'in:realtime,correspondence'],
            'time_control_type' => ['required', 'string', 'in:absolute,byoyomi,correspondence'],
            'time_control_config' => ['required', 'array'],
            'proposed_color' => ['required', 'string', 'in:black,white,random'],
        ];
    }
}
