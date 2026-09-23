<?php

namespace App\Hours;

/**
 * Something the history says for certain, e.g. "Mondays: never open before 10:00 (0 of 5 visits)".
 * The web app turns the structured form into a sentence in the reader's language.
 */
final class HoursRule
{
    /** Shut until `from` on these days. */
    public const OPENS_AFTER = 'opens_after';

    /** Shut from `from` to the end of the day. */
    public const CLOSED_AFTER = 'closed_after';

    /** Shut between `from` and `to` although open before and after (lunch, prayer, the bank). */
    public const CLOSED_WINDOW = 'closed_window';

    /** Shut at every visit on these days. */
    public const CLOSED_DAY = 'closed_day';

    /** @param list<int> $weekdays */
    public function __construct(
        public readonly string $type,
        public readonly array $weekdays,
        public readonly ?int $from,
        public readonly ?int $to,
        public readonly int $open,
        public readonly int $total,
    ) {}

    public function appliesTo(int $weekday): bool
    {
        return in_array($weekday, $this->weekdays, true);
    }

    /**
     * Whether the shut stretch this rule describes lies within $margin minutes of an arrival:
     * "never open before 07:00" says nothing useful about a stop planned for 09:30.
     */
    public function isNear(int $minuteOfDay, int $margin = 90): bool
    {
        [$shutFrom, $shutTo] = match ($this->type) {
            self::OPENS_AFTER => [Slots::FIRST_MINUTE, $this->from],
            self::CLOSED_AFTER => [$this->from, Slots::LAST_MINUTE],
            self::CLOSED_WINDOW => [$this->from, $this->to],
            default => [0, 24 * 60],
        };

        return $minuteOfDay >= $shutFrom - $margin && $minuteOfDay < $shutTo + $margin;
    }

    /** @return array{type: string, weekdays: list<int>, from: ?string, to: ?string, open: int, total: int} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'weekdays' => $this->weekdays,
            'from' => $this->from === null ? null : Slots::format($this->from),
            'to' => $this->to === null ? null : Slots::format($this->to),
            'open' => $this->open,
            'total' => $this->total,
        ];
    }
}
