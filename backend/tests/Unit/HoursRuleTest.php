<?php

namespace Tests\Unit;

use App\Hours\HoursRule;
use PHPUnit\Framework\TestCase;

class HoursRuleTest extends TestCase
{
    public function test_a_rule_matters_to_arrivals_near_its_shut_stretch(): void
    {
        $opensLate = new HoursRule(HoursRule::OPENS_AFTER, [1], 600, null, 0, 5);
        $lunch = new HoursRule(HoursRule::CLOSED_WINDOW, [1, 2, 3, 4, 5], 780, 900, 0, 12);
        $friday = new HoursRule(HoursRule::CLOSED_DAY, [5], null, null, 0, 6);

        $this->assertTrue($opensLate->isNear(520), 'arriving at 08:40, inside the shut stretch');
        $this->assertTrue($opensLate->isNear(680), '11:20 is still within 90 minutes of opening');
        $this->assertFalse($opensLate->isNear(700));
        $this->assertTrue($lunch->isNear(700), '11:40: lunch is coming');
        $this->assertFalse($lunch->isNear(600));
        $this->assertFalse($lunch->isNear(1000));
        $this->assertTrue($friday->isNear(420));
        $this->assertTrue($friday->appliesTo(5));
        $this->assertFalse($friday->appliesTo(4));
    }
}
