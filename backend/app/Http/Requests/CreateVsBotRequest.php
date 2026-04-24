<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Game\Handicap;
use Illuminate\Contracts\Validation\ValidationRule;
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
            'handicap' => ['nullable', 'integer', $this->handicapRule()],
            'handicap_placement' => ['nullable', 'string', 'in:fixed'],
        ];
    }

    private function handicapRule(): ValidationRule
    {
        $boardSize = (int) $this->input('board_size', 9);

        return new class($boardSize) implements ValidationRule
        {
            public function __construct(private readonly int $boardSize) {}

            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if ($value === null) {
                    return;
                }
                if (! Handicap::isValid($this->boardSize, (int) $value)) {
                    $fail("Invalid handicap {$value} for {$this->boardSize}x{$this->boardSize} board.");
                }
            }
        };
    }
}
