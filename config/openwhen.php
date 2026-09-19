<?php

return [
    'locales' => [
        'sq' => 'Shqip',
        'en' => 'English',
    ],

    'defaults' => [
        'locale' => env('OPENWHEN_DEFAULT_LOCALE', 'sq'),
        'timezone' => env('OPENWHEN_DEFAULT_TIMEZONE', 'Europe/Tirane'),
    ],

    /*
    |---------------------------------------------------------------------------
    | The thresholds
    |---------------------------------------------------------------------------
    | These live as constants in App\Services\OpenWindowEstimator, because they
    | are the promise the product makes rather than something to tune per
    | install, and every one of them is pinned in a test. Repeated here only so
    | that they are findable.
    |
    |   min_per_hour     2     below this, an hour bucket says nothing
    |   min_per_weekday  4     below this, the whole weekday says nothing
    |   open_at          0.60  an hour counts as open above this
    |   shut_at          0.25  an hour counts as reliably shut below this
    |   rule_evidence    3     visits needed before a rule is stated out loud
    |   prior            1.0   Laplace: one imaginary open and one closed visit
    |
    | What is deliberately absent: any table that could join one distributor's
    | visits to another's. A shared open-hours graph is an obvious later
    | product and an obvious later temptation, and the moment it exists what is
    | being shared stops being opening hours and becomes "who calls on which
    | shop, how often". That belongs to the distributor who paid for the diesel.
    */
];
