<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Rules\ChineseRuleset;
use PHPUnit\Framework\TestCase;

class ChineseRulesetTest extends TestCase
{
    public function test_default_komi_by_board_size(): void
    {
        $rules = new ChineseRuleset;

        $this->assertSame(5.5, $rules->komi(9));
        $this->assertSame(6.5, $rules->komi(13));
        $this->assertSame(7.5, $rules->komi(19));
    }

    public function test_komi_with_handicap_zero_equals_default_komi(): void
    {
        $rules = new ChineseRuleset;

        $this->assertSame(5.5, $rules->komiWithHandicap(9, 0));
        $this->assertSame(7.5, $rules->komiWithHandicap(19, 0));
    }

    public function test_komi_with_handicap_one_is_not_half_point(): void
    {
        // Handicap=1 is not supported by our rules (treated as "no handicap" here).
        $rules = new ChineseRuleset;

        $this->assertSame(5.5, $rules->komiWithHandicap(9, 1));
    }

    public function test_komi_with_handicap_two_or_more_is_half_point(): void
    {
        $rules = new ChineseRuleset;

        foreach ([2, 3, 4, 5, 9] as $h) {
            $this->assertSame(0.5, $rules->komiWithHandicap(19, $h), "h={$h}");
        }
    }
}
