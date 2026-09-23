<?php

namespace App\Hours;

/** One moment somebody saw a shop open or shut, in the organisation's local time. */
final class Sighting
{
    public function __construct(
        public readonly int $weekday,
        public readonly int $minute,
        public readonly bool $open,
    ) {}
}
