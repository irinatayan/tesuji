<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Game\Handicap;
use Illuminate\Contracts\Validation\ValidationRule;
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
