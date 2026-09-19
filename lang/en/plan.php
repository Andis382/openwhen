<?php

/*
 * Why a stop is where it is in the list.
 *
 * A driver who cannot see the reason stops trusting the order, and a driver
 * who does not trust the order drives his old one and taps nothing. So every
 * moved stop says why it moved, and every stop that was left alone says that
 * too — including, out loud, when the reason is "we do not know yet".
 *
 * tests/Unit/PlanToneTest.php holds these to it: never an instruction, never
 * a claim of certainty the arithmetic has not earned.
 */

return [
    'reason' => [
        'window'    => 'moved to when it is usually open',
        'unchanged' => 'already in a good place',
        'unknown'   => 'not enough visits yet — left where you had it',
    ],

    'rule' => [
        'not_before'  => 'rarely open before :time',
        'not_after'   => 'rarely open after :time',
        'shut_around' => 'often shut around :time',
    ],
];
