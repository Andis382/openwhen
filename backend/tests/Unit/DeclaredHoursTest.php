<?php

namespace Tests\Unit;

use App\Hours\DeclaredHours;
use App\Hours\InvalidHours;
use PHPUnit\Framework\TestCase;

class DeclaredHoursTest extends TestCase
{
    public function test_reads_and_writes_the_stored_shape(): void
    {
        $hours = DeclaredHours::fromArray(['1' => [['15:00', '20:00'], ['7:30', '13:00']], '5' => [], '7' => null]);

        $this->assertSame([[450, 780], [900, 1200]], $hours->intervals(1), 'sorted, in minutes');
        $this->assertSame([], $hours->intervals(5));
        $this->assertNull($hours->intervals(7));
        $this->assertNull($hours->intervals(3), 'missing days are unknown');
        $this->assertSame(2, $hours->knownDays());
        $this->assertSame([['07:30', '13:00'], ['15:00', '20:00']], $hours->toArray()['1']);
    }

    public function test_open_at(): void
    {
        $hours = DeclaredHours::fromArray(['1' => [['08:00', '13:00'], ['15:00', '20:00']], '5' => []]);

        $this->assertTrue($hours->isOpenAt(1, 480));
        $this->assertFalse($hours->isOpenAt(1, 780), 'closing time is exclusive');
        $this->assertFalse($hours->isOpenAt(1, 840));
        $this->assertFalse($hours->isOpenAt(5, 600));
        $this->assertNull($hours->isOpenAt(2, 600));
    }

    public function test_reads_a_day_the_way_people_type_it(): void
    {
        $this->assertSame([['07:00', '13:00'], ['15:00', '20:30']], DeclaredHours::parseDayText('7:00-13:00, 15.00 – 20:30'));
        $this->assertSame([['08:00', '24:00']], DeclaredHours::parseDayText('08:00-24:00'));
        $this->assertSame([], DeclaredHours::parseDayText('Mbyllur'));
        $this->assertSame([], DeclaredHours::parseDayText('closed'));
        $this->assertNull(DeclaredHours::parseDayText('  '));
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function badHours(): array
    {
        return [
            'not a time' => [['1' => [['8am', '13:00']]], InvalidHours::TIME],
            'minutes over 59' => [['1' => [['08:75', '13:00']]], InvalidHours::TIME],
            'closes before it opens' => [['1' => [['13:00', '08:00']]], InvalidHours::ORDER],
            'overlapping intervals' => [['1' => [['08:00', '13:00'], ['12:00', '18:00']]], InvalidHours::OVERLAP],
            'weekday out of range' => [['8' => [['08:00', '13:00']]], InvalidHours::SHAPE],
            'interval without a close' => [['1' => [['08:00']]], InvalidHours::SHAPE],
        ];
    }

    /** @dataProvider badHours */
    public function test_rejects_hours_it_cannot_read(array $raw, string $reason): void
    {
        try {
            DeclaredHours::fromArray($raw);
            $this->fail('expected InvalidHours');
        } catch (InvalidHours $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_rejects_unreadable_spreadsheet_text(): void
    {
        $this->expectException(InvalidHours::class);
        DeclaredHours::parseDayText('from eight until one');
    }
}
