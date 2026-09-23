<?php

namespace App\Hours;

use InvalidArgumentException;

/** Declared hours that cannot be read. The reason maps to a translated message (hours.<reason>). */
final class InvalidHours extends InvalidArgumentException
{
    public const TIME = 'time';

    public const ORDER = 'order';

    public const OVERLAP = 'overlap';

    public const SHAPE = 'shape';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Invalid opening hours: {$reason}");
    }
}
