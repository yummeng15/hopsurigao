<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownload
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function stream(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public static function fromRecords(string $filename, array $records): StreamedResponse
    {
        $keys = [];

        foreach ($records as $record) {
            foreach (array_keys($record) as $key) {
                $keys[$key] = true;
            }
        }

        $keys = array_keys($keys);
        $headings = array_map(
            fn (string $key): string => ucwords(str_replace('_', ' ', $key)),
            $keys,
        );

        $rows = array_map(function (array $record) use ($keys): array {
            return array_map(
                fn (string $key): string => self::cell($record[$key] ?? null),
                $keys,
            );
        }, $records);

        if ($headings === []) {
            $headings = ['Message'];
            $rows = [['No records found']];
        }

        return self::stream($filename, $headings, $rows);
    }

    private static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            $flat = array_is_list($value);

            return $flat
                ? implode('; ', array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : json_encode($item), $value))
                : (string) json_encode($value);
        }

        return (string) $value;
    }
}
