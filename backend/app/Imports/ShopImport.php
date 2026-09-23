<?php

namespace App\Imports;

use App\Hours\DeclaredHours;
use App\Hours\InvalidHours;
use App\Models\Shop;
use App\Support\Phones;
use Illuminate\Support\Facades\DB;

/**
 * The customer list from a spreadsheet. Every row is checked the same way for the preview and
 * for the real import; rows with errors are skipped, the rest create shops or update the one
 * with the same code (or, without a code, the same name and address).
 */
class ShopImport
{
    public const REQUIRED = ['name', 'address', 'town', 'lat', 'lng'];

    private const DAYS = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];

    public const ALIASES = [
        'code' => ['kodi', 'kod', 'id', 'customer code', 'customer id'],
        'name' => ['emri', 'shop', 'dyqani', 'shop name', 'customer'],
        'address' => ['adresa', 'rruga', 'street'],
        'town' => ['city', 'qyteti', 'qytet', 'fshati', 'zona'],
        'lat' => ['latitude', 'gjeresia', 'gjerësia'],
        'lng' => ['lon', 'long', 'longitude', 'gjatesia', 'gjatësia'],
        'phone' => ['telefoni', 'telefon', 'tel', 'celulari', 'mobile'],
        'contact' => ['contact name', 'kontakti', 'personi', 'pronari', 'owner'],
        'notes' => ['access notes', 'shenime', 'shënime', 'notes for the driver'],
        'order' => ['usual order', 'order eur', 'order value', 'porosia', 'vlera'],
        'mon' => ['monday', 'hene', 'hënë', 'e hene', 'e hënë'],
        'tue' => ['tuesday', 'marte', 'martë', 'e marte', 'e martë'],
        'wed' => ['wednesday', 'merkure', 'mërkurë', 'e merkure', 'e mërkurë'],
        'thu' => ['thursday', 'enjte', 'e enjte'],
        'fri' => ['friday', 'premte', 'e premte'],
        'sat' => ['saturday', 'shtune', 'shtunë', 'e shtune', 'e shtunë'],
        'sun' => ['sunday', 'diel', 'e diel'],
    ];

    /** Checks every row without writing anything. */
    public function preview(string $csv): array
    {
        return $this->run($csv, commit: false);
    }

    /** Writes the valid rows. */
    public function import(string $csv): array
    {
        return DB::transaction(fn () => $this->run($csv, commit: true));
    }

    private function run(string $csv, bool $commit): array
    {
        $table = CsvTable::parse($csv, self::ALIASES);
        $missing = $table->missing(self::REQUIRED);
        if ($missing) {
            return ['missingColumns' => $missing, 'rows' => [], 'counts' => ['rows' => count($table->rows), 'valid' => 0, 'invalid' => 0, 'create' => 0, 'update' => 0]];
        }

        $existing = Shop::all();
        $byCode = $existing->filter(fn (Shop $s) => $s->code !== null)->keyBy(fn (Shop $s) => mb_strtolower($s->code));
        $byNameAddress = $existing->keyBy(fn (Shop $s) => $this->identity($s->name, $s->address));
        $codesSeen = [];

        $rows = [];
        $counts = ['rows' => 0, 'valid' => 0, 'invalid' => 0, 'create' => 0, 'update' => 0];
        foreach ($table->rows as ['line' => $line, 'cells' => $cells]) {
            [$data, $errors] = $this->validate($cells, $table->columns);
            $code = $data['code'] ?? null;
            if ($code === null) {
                unset($data['code']);
            }
            if ($code !== null) {
                $lower = mb_strtolower($code);
                if (isset($codesSeen[$lower])) {
                    $errors['code'] = __('imports.duplicate_code', ['line' => $codesSeen[$lower]]);
                }
                $codesSeen[$lower] ??= $line;
            }
            $match = $code !== null
                ? $byCode[mb_strtolower($code)] ?? null
                : $byNameAddress[$this->identity($data['name'] ?? '', $data['address'] ?? '')] ?? null;
            $action = $match ? 'update' : 'create';

            $counts['rows']++;
            if ($errors) {
                $counts['invalid']++;
            } else {
                $counts['valid']++;
                $counts[$action]++;
                if ($commit) {
                    $match ? $match->update($data) : Shop::create($data + ['active' => true]);
                }
            }
            $rows[] = [
                'line' => $line,
                'name' => $cells['name'] ?? '',
                'address' => $cells['address'] ?? '',
                'town' => $cells['town'] ?? '',
                'action' => $action,
                'errors' => array_map(fn (string $field, string $message) => ['field' => $field, 'message' => $message], array_keys($errors), $errors),
            ];
        }

        return ['missingColumns' => [], 'rows' => $rows, 'counts' => $counts];
    }

    /**
     * Optional columns the file does not have are left out, so an update keeps what the shop had.
     *
     * @param  list<string>  $columns
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $cells, array $columns): array
    {
        $errors = [];
        $data = [];
        foreach (['name' => 160, 'address' => 200, 'town' => 80] as $field => $max) {
            $value = $cells[$field] ?? '';
            if ($value === '') {
                $errors[$field] = __('imports.required');
            } elseif (mb_strlen($value) > $max) {
                $errors[$field] = __('imports.too_long');
            }
            $data[$field] = $value;
        }
        foreach (['lat' => 90, 'lng' => 180] as $field => $limit) {
            $value = str_replace(',', '.', $cells[$field] ?? '');
            if ($value === '') {
                $errors[$field] = __('imports.required');
            } elseif (! is_numeric($value) || abs((float) $value) > $limit) {
                $errors[$field] = __('imports.coordinate');
            } else {
                $data[$field] = round((float) $value, 6);
            }
        }

        $has = fn (string $column) => in_array($column, $columns, true);
        $text = fn (string $column, int $max) => ($cells[$column] ?? '') !== '' ? mb_substr($cells[$column], 0, $max) : null;

        if ($has('code')) {
            $data['code'] = $text('code', 40);
        }
        if ($has('contact')) {
            $data['contact_name'] = $text('contact', 120);
        }
        if ($has('notes')) {
            $data['access_notes'] = $text('notes', 1000);
        }
        if ($has('phone')) {
            $data['phone'] = Phones::normalize($cells['phone']);
            if ($data['phone'] !== null && ! Phones::isPlausible($data['phone'])) {
                $errors['phone'] = __('imports.phone');
            }
        }
        if ($has('order')) {
            $order = str_replace([' ', '€', 'EUR', 'eur', ','], ['', '', '', '', '.'], $cells['order']);
            if ($order !== '' && (! is_numeric($order) || (float) $order < 0)) {
                $errors['order'] = __('imports.money');
            }
            $data['order_value_cents'] = $order === '' || ! is_numeric($order) ? null : (int) round((float) $order * 100);
        }

        $dayColumns = array_filter(array_keys(self::DAYS), $has);
        if ($dayColumns) {
            $hours = [];
            foreach ($dayColumns as $column) {
                try {
                    $hours[(string) self::DAYS[$column]] = DeclaredHours::parseDayText($cells[$column]);
                } catch (InvalidHours) {
                    $errors[$column] = __('imports.hours');
                }
            }
            $data['declared_hours'] = DeclaredHours::fromArray($hours)->toArray();
        }

        return [$data, $errors];
    }

    private function identity(string $name, string $address): string
    {
        return mb_strtolower(trim($name)).'|'.mb_strtolower(trim($address));
    }
}
