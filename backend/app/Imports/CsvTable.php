<?php

namespace App\Imports;

/**
 * A spreadsheet export read into rows keyed by our own column names. Accepts comma, semicolon
 * (European Excel) or tab separated text, with or without a byte-order mark, and English or
 * Albanian headers through the alias list the importer passes in.
 */
final class CsvTable
{
    /**
     * @param  list<string>  $columns  canonical names found in the header
     * @param  list<array{line: int, cells: array<string, string>}>  $rows
     */
    private function __construct(
        public readonly array $columns,
        public readonly array $rows,
    ) {}

    /** @param array<string, list<string>> $aliases canonical name => accepted header spellings (lower case) */
    public static function parse(string $text, array $aliases): self
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $firstLine = strtok($text, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $d) => substr_count($firstLine, $d))->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $columns = null;
        $rows = [];
        $line = 0;
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', $cells)) === '') {
                continue;
            }
            if ($columns === null) {
                $columns = array_map(fn (string $h) => self::canonical($h, $aliases), $cells);

                continue;
            }
            $row = [];
            foreach ($columns as $i => $name) {
                if ($name !== null) {
                    $row[$name] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $rows[] = ['line' => $line, 'cells' => $row];
        }
        fclose($stream);

        return new self(array_values(array_filter($columns ?? [])), $rows);
    }

    /** @param list<string> $required @return list<string> */
    public function missing(array $required): array
    {
        return array_values(array_diff($required, $this->columns));
    }

    /** @param array<string, list<string>> $aliases */
    private static function canonical(string $header, array $aliases): ?string
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $header))));
        foreach ($aliases as $name => $spellings) {
            if ($key === $name || in_array($key, $spellings, true)) {
                return $name;
            }
        }

        return null;
    }
}
