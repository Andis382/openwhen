<?php

namespace App\Imports;

use App\Models\Observation;
use App\Models\Shop;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Visit history from before OpenWhen (a driver's notebook typed into a sheet): one row per
 * visit with the shop, date, time and whether it was open. Importing the same file twice does
 * not count the same visits twice.
 */
class ObservationImport
{
    public const REQUIRED = ['shop', 'date', 'time', 'open'];

    public const ALIASES = [
        'shop' => ['shop code', 'code', 'kodi', 'shop name', 'dyqani', 'emri'],
        'date' => ['data', 'dita', 'day'],
        'time' => ['ora', 'hour', 'koha'],
        'open' => ['was open', 'hapur', 'status', 'gjendja'],
    ];

    private const YES = ['yes', 'y', '1', 'open', 'true', 'po', 'hapur', 'p'];

    private const NO = ['no', 'n', '0', 'closed', 'false', 'jo', 'mbyllur', 'j'];

    public function __construct(private readonly LocalTime $clock) {}

    public function preview(string $csv): array
    {
        return $this->run($csv, commit: false);
    }

    public function import(string $csv): array
    {
        return DB::transaction(fn () => $this->run($csv, commit: true));
    }

    private function run(string $csv, bool $commit): array
    {
        $table = CsvTable::parse($csv, self::ALIASES);
        $counts = ['rows' => count($table->rows), 'valid' => 0, 'invalid' => 0, 'duplicate' => 0];
        $missing = $table->missing(self::REQUIRED);
        if ($missing) {
            return ['missingColumns' => $missing, 'rows' => [], 'counts' => $counts];
        }

        $shops = Shop::all(['id', 'code', 'name']);
        $byCode = $shops->filter(fn (Shop $s) => $s->code !== null)->keyBy(fn (Shop $s) => mb_strtolower($s->code));
        $byName = $shops->groupBy(fn (Shop $s) => mb_strtolower($s->name));
        $now = $this->clock->now();

        $rows = [];
        $seen = [];
        foreach ($table->rows as ['line' => $line, 'cells' => $cells]) {
            $errors = [];
            $key = mb_strtolower($cells['shop']);
            $shop = $byCode[$key] ?? null;
            if ($shop === null) {
                $named = $byName[$key] ?? collect();
                $shop = $named->count() === 1 ? $named->first() : null;
                if ($named->count() > 1) {
                    $errors['shop'] = __('imports.shop_ambiguous');
                } elseif ($shop === null) {
                    $errors['shop'] = $cells['shop'] === '' ? __('imports.required') : __('imports.shop_unknown');
                }
            }
            $at = $this->localTime($cells['date'], $cells['time']);
            if ($at === null) {
                $errors['date'] = __('imports.datetime');
            } elseif ($at->greaterThan($now)) {
                $errors['date'] = __('imports.future');
            }
            $open = mb_strtolower($cells['open']);
            if (! in_array($open, self::YES, true) && ! in_array($open, self::NO, true)) {
                $errors['open'] = __('imports.yes_no');
            }

            $duplicate = false;
            if (! $errors) {
                $identity = $shop->id.'|'.$at->utc()->toIso8601String();
                $duplicate = isset($seen[$identity]) || Observation::where('shop_id', $shop->id)->where('observed_at', $at->utc())->exists();
                $seen[$identity] = true;
            }

            if ($errors) {
                $counts['invalid']++;
            } elseif ($duplicate) {
                $counts['duplicate']++;
            } else {
                $counts['valid']++;
                if ($commit) {
                    Observation::create([
                        'shop_id' => $shop->id,
                        'observed_at' => $at->utc(),
                        'weekday' => $at->dayOfWeekIso,
                        'minute_of_day' => $at->hour * 60 + $at->minute,
                        'is_open' => in_array($open, self::YES, true),
                        'source' => Observation::FROM_IMPORT,
                    ]);
                }
            }
            $rows[] = [
                'line' => $line,
                'shop' => $shop?->name ?? $cells['shop'],
                'date' => $cells['date'],
                'time' => $cells['time'],
                'open' => in_array($open, self::YES, true) ? true : (in_array($open, self::NO, true) ? false : null),
                'duplicate' => $duplicate,
                'errors' => array_map(fn (string $field, string $message) => ['field' => $field, 'message' => $message], array_keys($errors), $errors),
            ];
        }

        return ['missingColumns' => [], 'rows' => $rows, 'counts' => $counts];
    }

    /** "2026-09-14" or "14.09.2026" or "14/9/2026", with "08:40" — read on the distributor's clock. */
    private function localTime(string $date, string $time): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{1,2})[:.](\d{2})$/', $time, $t) || (int) $t[1] > 23 || (int) $t[2] > 59) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $d)) {
            [$year, $month, $day] = [(int) $d[1], (int) $d[2], (int) $d[3]];
        } elseif (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $date, $d)) {
            [$day, $month, $year] = [(int) $d[1], (int) $d[2], (int) $d[3]];
        } else {
            return null;
        }
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, (int) $t[1], (int) $t[2], 0, $this->clock->timezone);
    }
}
