<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkDeadStonesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stones' => ['present', 'array'],
            'stones.*.x' => ['required', 'integer', 'min:0'],
            'stones.*.y' => ['required', 'integer', 'min:0'],
        ];
    }
}
