<?php

namespace App\Hours;

/**
 * Opening hours as the shop states them, or as somebody typed them in once: for each ISO weekday
 * a list of [open, close] intervals in minutes, an empty list for "closed all day", or null when
 * nobody knows. Stored and sent as {"1": [["07:00","13:00"],["15:00","20:00"]], "5": [], "7": null}.
 */
final class DeclaredHours
{
    /** @param array<int, list<array{0: int, 1: int}>|null> $days */
    private function __construct(private readonly array $days) {}

    public static function unknown(): self
    {
        return new self(array_fill(1, 7, null));
    }

    /**
     * Missing weekdays are unknown.
     *
     * @throws InvalidHours
     */
    public static function fromArray(?array $raw): self
    {
        $days = array_fill(1, 7, null);
        foreach ($raw ?? [] as $key => $intervals) {
            $day = filter_var($key, FILTER_VALIDATE_INT);
            if ($day === false || $day < 1 || $day > 7) {
                throw new InvalidHours(InvalidHours::SHAPE);
            }
            if ($intervals === null) {
                continue;
            }
            if (! is_array($intervals)) {
                throw new InvalidHours(InvalidHours::SHAPE);
            }
            $days[$day] = self::parseIntervals($intervals);
        }

        return new self($days);
    }

    /**
     * One day as people write it in a spreadsheet: "07:00-13:00, 15:00-20:00", "closed" / "mbyllur",
     * or blank for "don't know". Returns the stored shape for that day.
     *
     * @return list<array{0: string, 1: string}>|null
     *
     * @throws InvalidHours
     */
    public static function parseDayText(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || $text === '?') {
            return null;
        }
        if (in_array(mb_strtolower($text), ['closed', 'mbyllur', 'x', '-', '–'], true)) {
            return [];
        }
        $intervals = [];
        foreach (preg_split('/\s*[,\/]\s*/', $text) as $part) {
            $times = preg_split('/\s*[-–—]\s*/u', $part);
            if (count($times) !== 2) {
                throw new InvalidHours(InvalidHours::TIME);
            }
            $intervals[] = [$times[0], $times[1]];
        }
        $minutes = self::parseIntervals($intervals);

        return array_map(fn (array $i) => [Slots::format($i[0]), Slots::format($i[1])], $minutes);
    }

    /** @throws InvalidHours */
    public static function parseTime(mixed $value): int
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2})[:.](\d{2})$/', trim($value), $m)) {
            throw new InvalidHours(InvalidHours::TIME);
        }
        $hours = (int) $m[1];
        $minutes = (int) $m[2];
        if ($minutes > 59 || $hours > 24 || ($hours === 24 && $minutes !== 0)) {
            throw new InvalidHours(InvalidHours::TIME);
        }

        return $hours * 60 + $minutes;
    }

    /** @return array<string, list<array{0: string, 1: string}>|null> */
    public function toArray(): array
    {
        $out = [];
        foreach ($this->days as $day => $intervals) {
            $out[(string) $day] = $intervals === null
                ? null
                : array_map(fn (array $i) => [Slots::format($i[0]), Slots::format($i[1])], $intervals);
        }

        return $out;
    }

    /** @return list<array{0: int, 1: int}>|null */
    public function intervals(int $weekday): ?array
    {
        return $this->days[$weekday] ?? null;
    }

    public function isKnown(int $weekday): bool
    {
        return $this->intervals($weekday) !== null;
    }

    /** True or false when the day is declared, null when nobody said. */
    public function isOpenAt(int $weekday, int $minuteOfDay): ?bool
    {
        $intervals = $this->intervals($weekday);
        if ($intervals === null) {
            return null;
        }
        foreach ($intervals as [$open, $close]) {
            if ($minuteOfDay >= $open && $minuteOfDay < $close) {
                return true;
            }
        }

        return false;
    }

    public function knownDays(): int
    {
        return count(array_filter($this->days, fn ($d) => $d !== null));
    }

    /**
     * @param  array<mixed>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    private static function parseIntervals(array $intervals): array
    {
        $parsed = [];
        foreach ($intervals as $interval) {
            if (! is_array($interval) || count($interval) !== 2) {
                throw new InvalidHours(InvalidHours::SHAPE);
            }
            [$open, $close] = [self::parseTime(array_values($interval)[0]), self::parseTime(array_values($interval)[1])];
            if ($close <= $open) {
                throw new InvalidHours(InvalidHours::ORDER);
            }
            $parsed[] = [$open, $close];
        }
        usort($parsed, fn (array $a, array $b) => $a[0] <=> $b[0]);
        for ($i = 1; $i < count($parsed); $i++) {
            if ($parsed[$i][0] < $parsed[$i - 1][1]) {
                throw new InvalidHours(InvalidHours::OVERLAP);
            }
        }

        return $parsed;
    }
}
